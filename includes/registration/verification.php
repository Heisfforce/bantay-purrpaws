<?php
/**
 * Multi-step registration email verification service.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../users.php';
require_once __DIR__ . '/../sensitive-data.php';
require_once __DIR__ . '/../security/audit.php';
require_once __DIR__ . '/../security/device.php';
require_once __DIR__ . '/../security/rate-limit.php';

define('REG_LINK_TTL', 900);       // 15 minutes
define('REG_CODE_TTL', 300);       // 5 minutes
define('REG_CODE_MAX_ATTEMPTS', 3);
define('REG_RESEND_COOLDOWN', 60); // seconds

function regToken(): string {
    return bin2hex(random_bytes(32));
}

function regCode(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function findRegistrationByToken(string $registrationToken): ?array {
    return db_select(
        'registration_verifications',
        'registration_token=eq.' . urlencode($registrationToken) . '&limit=1',
        true
    ) ?: null;
}

function findRegistrationByVerificationToken(string $verificationToken): ?array {
    return db_select(
        'registration_verifications',
        'verification_token=eq.' . urlencode($verificationToken) . '&limit=1',
        true
    ) ?: null;
}

function registrationPlainEmail(array $row): string {
    $email = (string) ($row['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return strtolower($email);
    }
    return '';
}

function validateRegistrationEmail(string $email): ?string {
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid email format.';
    }

    if (emailExists($email)) {
        return 'Email already registered.';
    }

    $pending = db_select(
        'registration_verifications',
        'email_hash=eq.' . urlencode(sensitiveLookupHash($email))
        . '&verification_status=in.(pending_link,link_verified,pending_code)'
        . '&limit=1',
        true
    );
    if ($pending) {
        return 'Email already registered.';
    }

    $deliverable = verifyEmailDeliverability($email);
    if ($deliverable !== true) {
        return 'This email address does not exist or cannot receive emails.';
    }

    return null;
}

function checkRegistrationRateLimit(?string $ip = null): ?string {
    $ip = $ip ?? clientIpAddress();
    if (isIpBlocked($ip)) {
        return 'Too many requests. Please try again later.';
    }
    $starts = countRecentEvents('registration_started', $ip, 3600);
    if ($starts >= 20) {
        return 'Too many registration attempts. Please try again later.';
    }
    return null;
}

function startRegistrationVerification(string $fullName, string $email, string $passwordHash): array {
    $ip = clientIpAddress();
    $rateErr = checkRegistrationRateLimit($ip);
    if ($rateErr) {
        return ['success' => false, 'message' => $rateErr];
    }

    $emailErr = validateRegistrationEmail($email);
    if ($emailErr) {
        logSecurityEvent('registration_email_invalid', 'warning', null, $ip, null, ['email' => $email, 'reason' => $emailErr]);
        return ['success' => false, 'message' => $emailErr];
    }

    $email = strtolower(trim($email));
    $registrationToken = regToken();
    $verificationToken   = regToken();
    $linkExpiry          = date('Y-m-d H:i:s', time() + REG_LINK_TTL);
    $now                 = date('Y-m-d H:i:s');

    db_insert('registration_verifications', [
        'registration_token'           => $registrationToken,
        'full_name'                    => trim($fullName),
        'email'                        => $email,
        'email_hash'                   => sensitiveLookupHash($email),
        'password_hash'                => $passwordHash,
        'verification_token'           => $verificationToken,
        'verification_token_expiry'    => $linkExpiry,
        'verification_status'          => 'pending_link',
        'last_verification_email_sent' => $now,
        'ip_address'                   => $ip,
    ]);

    if (!sendRegistrationLinkEmail($email, trim($fullName), $verificationToken)) {
        db_delete('registration_verifications', 'registration_token=eq.' . urlencode($registrationToken));
        return ['success' => false, 'message' => 'Could not send verification email. Please try again.'];
    }

    logSecurityEvent('registration_started', 'info', null, $ip, null, ['email_hash' => sensitiveLookupHash($email)]);

    return [
        'success'             => true,
        'registration_token'=> $registrationToken,
        'message'             => 'Verification email sent. Please check your inbox.',
        'redirect'            => url('register-pending.php?reg=' . urlencode($registrationToken)),
    ];
}

function resendRegistrationLink(string $registrationToken): array {
    $row = findRegistrationByToken($registrationToken);
    if (!$row) {
        return ['success' => false, 'message' => 'Registration session not found. Please start over.'];
    }

    if (in_array($row['verification_status'], ['activated', 'locked'], true)) {
        return ['success' => false, 'message' => 'This registration is already complete.'];
    }

    if (!empty($row['last_verification_email_sent'])
        && (time() - strtotime($row['last_verification_email_sent'])) < REG_RESEND_COOLDOWN) {
        $wait = REG_RESEND_COOLDOWN - (time() - strtotime($row['last_verification_email_sent']));
        return ['success' => false, 'message' => "Please wait {$wait} seconds before resending.", 'cooldown' => $wait];
    }

    if (emailExists(registrationPlainEmail($row))) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    $verificationToken = regToken();
    $linkExpiry        = date('Y-m-d H:i:s', time() + REG_LINK_TTL);
    $now               = date('Y-m-d H:i:s');

    db_update('registration_verifications', [
        'verification_token'           => $verificationToken,
        'verification_token_expiry'    => $linkExpiry,
        'verification_status'          => 'pending_link',
        'email_link_verified'          => 0,
        'email_link_verified_at'       => null,
        'verification_code'            => null,
        'verification_code_expiry'     => null,
        'verification_attempts'        => 0,
        'last_verification_email_sent' => $now,
    ], 'id=eq.' . (int) $row['id']);

    $email = registrationPlainEmail($row);
    if (!sendRegistrationLinkEmail($email, $row['full_name'], $verificationToken)) {
        return ['success' => false, 'message' => 'Could not send verification email.'];
    }

    logSecurityEvent('registration_link_resent', 'info', null, $row['ip_address'] ?? null, null);

    return ['success' => true, 'message' => 'A new verification link was sent to your email.'];
}

function verifyRegistrationLink(string $verificationToken): array {
    $row = findRegistrationByVerificationToken($verificationToken);
    if (!$row) {
        logSecurityEvent('registration_link_invalid', 'warning', null, clientIpAddress(), null);
        return ['success' => false, 'message' => 'Invalid verification link.', 'expired' => false];
    }

    if ($row['verification_status'] === 'activated') {
        return ['success' => false, 'message' => 'Account already activated.', 'redirect' => url('login.php')];
    }

    if ($row['verification_status'] === 'locked') {
        return ['success' => false, 'message' => 'Maximum verification attempts reached.', 'expired' => true];
    }

    if (strtotime($row['verification_token_expiry']) < time()) {
        db_update('registration_verifications', ['verification_status' => 'expired'], 'id=eq.' . (int) $row['id']);
        logSecurityEvent('registration_link_expired', 'warning', null, $row['ip_address'] ?? null, null);
        return [
            'success'             => false,
            'message'             => 'Verification link expired.',
            'expired'             => true,
            'registration_token'  => $row['registration_token'],
        ];
    }

    if (emailExists(registrationPlainEmail($row))) {
        return ['success' => false, 'message' => 'Email already registered.', 'expired' => false];
    }

    $code       = regCode();
    $codeExpiry = date('Y-m-d H:i:s', time() + REG_CODE_TTL);
    $now        = date('Y-m-d H:i:s');

    db_update('registration_verifications', [
        'email_link_verified'       => 1,
        'email_link_verified_at'    => $now,
        'verification_code'         => $code,
        'verification_code_expiry'  => $codeExpiry,
        'verification_attempts'     => 0,
        'verification_status'       => 'pending_code',
        'last_code_sent'            => $now,
        'verification_token'        => regToken(),
    ], 'id=eq.' . (int) $row['id']);

    $email = registrationPlainEmail($row);
    if (!sendRegistrationCodeEmail($email, $row['full_name'], $code)) {
        return ['success' => false, 'message' => 'Link verified but code email failed. Use resend code.'];
    }

    logSecurityEvent('registration_link_verified', 'info', null, $row['ip_address'] ?? null, null);

    return [
        'success'             => true,
        'message'             => 'Email verification successful.',
        'registration_token'  => $row['registration_token'],
        'redirect'            => url('register-verify-code.php?reg=' . urlencode($row['registration_token'])),
    ];
}

function resendRegistrationCode(string $registrationToken): array {
    $row = findRegistrationByToken($registrationToken);
    if (!$row) {
        return ['success' => false, 'message' => 'Registration session not found.'];
    }

    if (!(int) ($row['email_link_verified'] ?? 0)) {
        return ['success' => false, 'message' => 'Please verify the email link first.'];
    }

    if ($row['verification_status'] === 'locked') {
        return ['success' => false, 'message' => 'Maximum verification attempts reached.'];
    }

    if (!empty($row['last_code_sent'])
        && (time() - strtotime($row['last_code_sent'])) < REG_RESEND_COOLDOWN) {
        $wait = REG_RESEND_COOLDOWN - (time() - strtotime($row['last_code_sent']));
        return ['success' => false, 'message' => "Please wait {$wait} seconds before resending.", 'cooldown' => $wait];
    }

    $code       = regCode();
    $codeExpiry = date('Y-m-d H:i:s', time() + REG_CODE_TTL);
    $now        = date('Y-m-d H:i:s');

    db_update('registration_verifications', [
        'verification_code'        => $code,
        'verification_code_expiry' => $codeExpiry,
        'verification_attempts'    => 0,
        'last_code_sent'           => $now,
        'verification_status'      => 'pending_code',
    ], 'id=eq.' . (int) $row['id']);

    $email = registrationPlainEmail($row);
    if (!sendRegistrationCodeEmail($email, $row['full_name'], $code)) {
        return ['success' => false, 'message' => 'Could not send verification code.'];
    }

    logSecurityEvent('registration_code_resent', 'info', null, $row['ip_address'] ?? null, null);

    return ['success' => true, 'message' => 'A new verification code was sent.'];
}

function verifyRegistrationCode(string $registrationToken, string $code): array {
    $row = findRegistrationByToken($registrationToken);
    if (!$row) {
        return ['success' => false, 'message' => 'Registration session not found.'];
    }

    if ($row['verification_status'] === 'activated') {
        return ['success' => true, 'message' => 'Account successfully activated.', 'redirect' => url('login.php')];
    }

    if ($row['verification_status'] === 'locked') {
        return ['success' => false, 'message' => 'Maximum verification attempts reached.', 'locked' => true];
    }

    if (!(int) ($row['email_link_verified'] ?? 0)) {
        return ['success' => false, 'message' => 'Please verify the email link first.'];
    }

    if (empty($row['verification_code_expiry']) || strtotime($row['verification_code_expiry']) < time()) {
        logSecurityEvent('registration_code_expired', 'warning', null, $row['ip_address'] ?? null, null);
        return ['success' => false, 'message' => 'Verification code expired.', 'expired' => true];
    }

    $attempts = (int) ($row['verification_attempts'] ?? 0) + 1;
    $stored   = trim((string) ($row['verification_code'] ?? ''));
    $entered  = trim($code);

    if (!hash_equals($stored, $entered)) {
        $status = $attempts >= REG_CODE_MAX_ATTEMPTS ? 'locked' : 'pending_code';
        db_update('registration_verifications', [
            'verification_attempts' => $attempts,
            'verification_status'   => $status,
        ], 'id=eq.' . (int) $row['id']);

        logSecurityEvent('registration_code_failed', 'warning', null, $row['ip_address'] ?? null, null, [
            'attempt' => $attempts,
        ]);

        if ($attempts >= REG_CODE_MAX_ATTEMPTS) {
            return ['success' => false, 'message' => 'Maximum verification attempts reached.', 'locked' => true];
        }
        return ['success' => false, 'message' => 'Invalid verification code.', 'attempts_left' => REG_CODE_MAX_ATTEMPTS - $attempts];
    }

    $email = registrationPlainEmail($row);
    if (emailExists($email)) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    $user = insertUserRecord([
        'full_name'      => $row['full_name'],
        'email'          => $email,
        'password'       => $row['password_hash'],
        'role'           => 'user',
        'email_verified' => true,
        'auth_provider'  => 'local',
    ]);

    if (!$user) {
        return ['success' => false, 'message' => 'Could not create account. Please try again.'];
    }

    $userId = (int) $user['id'];

    db_update('registration_verifications', [
        'verification_status' => 'activated',
        'verification_code'   => null,
    ], 'id=eq.' . (int) $row['id']);

    require_once __DIR__ . '/../notifications.php';
    createSystemNotification('system', 'Welcome to BantayPurrPaws! Your email was verified.', null, null, $userId);

    logSecurityEvent('registration_activated', 'info', $userId, $row['ip_address'] ?? null, null);

    return [
        'success'  => true,
        'message'  => 'Account successfully activated.',
        'redirect' => url('register-success.php'),
        'user_id'  => $userId,
    ];
}

function registrationStatusPayload(?array $row): array {
    if (!$row) {
        return ['found' => false];
    }

    $linkExpired = strtotime($row['verification_token_expiry'] ?? '') < time();
    $codeExpired = !empty($row['verification_code_expiry']) && strtotime($row['verification_code_expiry']) < time();

    return [
        'found'               => true,
        'status'              => $row['verification_status'],
        'email_link_verified' => (bool) ($row['email_link_verified'] ?? false),
        'link_expired'        => $linkExpired && !(int) ($row['email_link_verified'] ?? 0),
        'code_expired'        => $codeExpired,
        'attempts_left'       => max(0, REG_CODE_MAX_ATTEMPTS - (int) ($row['verification_attempts'] ?? 0)),
        'email_masked'        => maskEmail(registrationPlainEmail($row)),
    ];
}

function maskEmail(string $email): string {
    if (!str_contains($email, '@')) {
        return '***';
    }
    [$local, $domain] = explode('@', $email, 2);
    $visible = substr($local, 0, min(2, strlen($local)));
    return $visible . '***@' . $domain;
}
