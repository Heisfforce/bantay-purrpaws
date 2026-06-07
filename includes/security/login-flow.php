<?php
/**
 * Enterprise login flow orchestrator.
 *
 * Flow: credentials → email Yes/No → number match → OTP → session
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../users.php';
require_once __DIR__ . '/../otp.php';
require_once __DIR__ . '/device.php';
require_once __DIR__ . '/rate-limit.php';
require_once __DIR__ . '/risk.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/sessions.php';

define('LOGIN_ATTEMPT_TTL', 900);
define('LOGIN_OTP_TTL', 300);
define('LOGIN_OTP_MAX_ATTEMPTS', 5);
define('LOGIN_NUMBER_MAX_ATTEMPTS', 3);
define('SUSPICIOUS_DENIALS_BEFORE_RESET', 3);

function securityToken(): string {
    return bin2hex(random_bytes(32));
}

function findLoginAttempt(string $attemptToken): ?array {
    return db_select(
        'login_attempts',
        'attempt_token=eq.' . urlencode($attemptToken) . '&limit=1',
        true
    ) ?: null;
}

function findLoginAttemptByChallenge(string $challengeToken): ?array {
    return db_select(
        'login_attempts',
        'challenge_token=eq.' . urlencode($challengeToken) . '&limit=1',
        true
    ) ?: null;
}

function isAttemptExpired(array $attempt): bool {
    return strtotime($attempt['expires_at']) < time()
        || in_array($attempt['status'], ['expired', 'denied', 'failed', 'completed', 'email_denied'], true);
}

function startLoginAttempt(string $email, string $password, ?string $clientFingerprint = null): array {
    $ip = clientIpAddress();
    $rateError = checkLoginRateLimit($ip);
    if ($rateError) {
        return ['success' => false, 'message' => $rateError];
    }

    $user = findUserByEmail($email);
    if (!$user) {
        logSecurityEvent('login_password_failed', 'warning', null, $ip, null, ['email' => $email]);
        return ['success' => false, 'message' => 'Invalid email or password. Please try again.'];
    }

    if (empty($user['password']) && ($user['auth_provider'] ?? '') === 'google') {
        return ['success' => false, 'message' => 'This account uses Google Sign-In. Please click "Sign in with Google".'];
    }

    if (isAccountLocked($user)) {
        return ['success' => false, 'message' => 'Account temporarily locked due to repeated failed attempts. Try again in 15 minutes.'];
    }

    $resetMsg = requirePasswordResetIfFlagged($user);
    if ($resetMsg) {
        return ['success' => false, 'message' => $resetMsg];
    }

    if (!password_verify($password, $user['password'])) {
        recordFailedPasswordAttempt((int) $user['id']);
        incrementFingerprintRisk(buildFingerprintHash($clientFingerprint ?? ''), true);
        logSecurityEvent('login_password_failed', 'warning', (int) $user['id'], $ip, null, []);
        return ['success' => false, 'message' => 'Invalid email or password. Please try again.'];
    }

    $context = collectClientContext($clientFingerprint);
    $trusted = isTrustedDevice((int) $user['id'], $context['fingerprint_hash']);
    $risk    = calculateLoginRisk($context, (int) $user['id'], $trusted);

    $attemptToken   = securityToken();
    $challengeToken = securityToken();
    $expiresAt      = date('Y-m-d H:i:s', time() + LOGIN_ATTEMPT_TTL);

    db_insert('login_attempts', [
        'attempt_token'    => $attemptToken,
        'challenge_token'  => $challengeToken,
        'user_id'          => (int) $user['id'],
        'ip_address'       => $context['ip_address'],
        'user_agent'       => $context['user_agent'],
        'browser'          => $context['browser'],
        'os'               => $context['os'],
        'device_type'      => $context['device_type'],
        'fingerprint_hash' => $context['fingerprint_hash'],
        'location_label'   => $context['location_label'],
        'location_city'    => $context['location_city'],
        'location_country' => $context['location_country'],
        'risk_level'       => $risk['risk_level'],
        'risk_score'       => $risk['risk_score'],
        'status'           => 'pending_verification',
        'expires_at'       => $expiresAt,
    ]);

    incrementFingerprintRisk($context['fingerprint_hash'], false);

    $plainEmail = resolveDeliverableEmail($user, $email, true);
    if ($plainEmail === '') {
        db_update('login_attempts', ['status' => 'failed'], 'attempt_token=eq.' . urlencode($attemptToken));
        return ['success' => false, 'message' => 'Could not resolve your email address. Please contact support.'];
    }

    repairUserEmailIfNeeded((int) $user['id'], $user, $plainEmail);

    if (!mail_is_configured()) {
        db_update('login_attempts', ['status' => 'failed'], 'attempt_token=eq.' . urlencode($attemptToken));
        return ['success' => false, 'message' => 'Email service is not configured on this server. Contact the administrator.'];
    }

    $sent = sendLoginAttemptEmail(
        $plainEmail,
        $user['full_name'],
        $challengeToken,
        $context,
        $risk
    );

    if (!$sent) {
        db_update('login_attempts', ['status' => 'failed'], 'attempt_token=eq.' . urlencode($attemptToken));
        return ['success' => false, 'message' => 'Could not send verification email. Please try again.'];
    }

    logSecurityEvent('login_attempt_started', 'info', (int) $user['id'], $ip, $context['fingerprint_hash'], [
        'risk_level' => $risk['risk_level'],
        'trusted'    => $trusted,
    ]);

    startSession();
    $_SESSION['pending_login_attempt'] = $attemptToken;
    $_SESSION['pending_login_email']   = $plainEmail;

    return [
        'success'       => true,
        'attempt_token' => $attemptToken,
        'step'          => 'awaiting_email',
        'message'       => 'We sent a security alert to your email. Confirm the sign-in attempt to continue.',
        'risk_level'    => $risk['risk_level'],
    ];
}

function deliverableEmailForUser(int $userId, string $fallbackEmail = ''): string {
    $user = getUserById($userId);
    if (!$user) {
        return '';
    }
    if ($fallbackEmail === '') {
        startSession();
        $fallbackEmail = (string) ($_SESSION['pending_login_email'] ?? '');
    }

    $resolved = resolveDeliverableEmail($user, $fallbackEmail);
    if ($resolved !== '') {
        return $resolved;
    }

    $fallbackEmail = strtolower(trim($fallbackEmail));
    if (filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
        return $fallbackEmail;
    }

    return '';
}

function approveEmailChallenge(string $challengeToken): array {
    $attempt = findLoginAttemptByChallenge($challengeToken);
    if (!$attempt || isAttemptExpired($attempt)) {
        return ['success' => false, 'message' => 'This verification link is invalid or has expired.'];
    }

    if ($attempt['status'] !== 'pending_verification') {
        return ['success' => false, 'message' => 'This sign-in attempt was already processed.'];
    }

    $numbers = [];
    while (count($numbers) < 3) {
        $n = random_int(10, 99);
        if (!in_array($n, $numbers, true)) {
            $numbers[] = $n;
        }
    }
    $displayIndex = random_int(0, 2);
    $displayNumber = $numbers[$displayIndex];

    db_update('login_attempts', [
        'status'           => 'email_approved',
        'display_number'   => $displayNumber,
        'number_options'   => implode(',', $numbers),
        'correct_number'   => $displayNumber,
        'email_verified_at'=> date('Y-m-d H:i:s'),
    ], 'id=eq.' . (int) $attempt['id']);

    $attempt = findLoginAttempt($attempt['attempt_token']);
    $userId  = (int) $attempt['user_id'];
    $plainEmail = deliverableEmailForUser($userId);
    $user    = getUserById($userId);

    sendNumberMatchEmail(
        $plainEmail,
        $user['full_name'] ?? 'User',
        $numbers,
        $displayNumber,
        deviceLabel($attempt)
    );

    logSecurityEvent('login_email_approved', 'info', (int) $attempt['user_id'], $attempt['ip_address'], $attempt['fingerprint_hash']);

    return [
        'success'       => true,
        'attempt_token' => $attempt['attempt_token'],
        'step'          => 'number_match',
        'display_number'=> $displayNumber,
        'message'       => 'Email confirmed. Complete the number matching step on your sign-in screen.',
    ];
}

function denyEmailChallenge(string $challengeToken): array {
    $attempt = findLoginAttemptByChallenge($challengeToken);
    if (!$attempt) {
        return ['success' => false, 'message' => 'Invalid verification link.'];
    }

    db_update('login_attempts', [
        'status' => 'email_denied',
    ], 'id=eq.' . (int) $attempt['id']);

    $userId = (int) $attempt['user_id'];
    incrementUserRiskScore($userId, 15);
    incrementFingerprintRisk($attempt['fingerprint_hash'] ?? '', true);

    if ($attempt['fingerprint_hash']) {
        try {
            getDB()->prepare(
                'UPDATE device_fingerprints SET risk_score = LEAST(100, risk_score + 20), failed_count = failed_count + 1 WHERE fingerprint_hash = ?'
            )->execute([$attempt['fingerprint_hash']]);
        } catch (Throwable $e) {
            // ignore
        }
    }

    logSecurityEvent('login_email_denied', 'critical', $userId, $attempt['ip_address'], $attempt['fingerprint_hash']);

    $denialCount = countRecentDenials($userId);
    if ($denialCount >= SUSPICIOUS_DENIALS_BEFORE_RESET) {
        getDB()->prepare('UPDATE users SET require_password_reset = 1 WHERE id = ?')->execute([$userId]);
        logSecurityEvent('password_reset_required', 'critical', $userId, $attempt['ip_address'], null, [
            'reason' => 'multiple_login_denials',
        ]);
    }

    if ($denialCount >= 2) {
        blockIpAddress($attempt['ip_address'], 'Repeated denied login attempts', 24);
    }

    $user = getUserById($userId);
    sendLoginBlockedEmail(
        deliverableEmailForUser($userId),
        $user['full_name'] ?? 'User',
        $attempt
    );

    return [
        'success' => true,
        'message' => 'Sign-in attempt blocked and your account has been notified.',
    ];
}

function countRecentDenials(int $userId): int {
    try {
        $since = date('Y-m-d H:i:s', time() - 86400);
        $stmt = getDB()->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE user_id = ? AND status = 'email_denied' AND created_at >= ?"
        );
        $stmt->execute([$userId, $since]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function verifyNumberMatch(string $attemptToken, int $selected): array {
    $attempt = findLoginAttempt($attemptToken);
    if (!$attempt || isAttemptExpired($attempt)) {
        return ['success' => false, 'message' => 'Session expired. Please sign in again.'];
    }

    if ($attempt['status'] !== 'email_approved') {
        return ['success' => false, 'message' => 'Complete email verification first.', 'step' => $attempt['status']];
    }

    $attempts = (int) $attempt['number_attempts'] + 1;
    $correct  = (int) $attempt['correct_number'];

    if ($selected !== $correct) {
        db_update('login_attempts', [
            'number_attempts' => $attempts,
            'status'          => $attempts >= LOGIN_NUMBER_MAX_ATTEMPTS ? 'failed' : 'email_approved',
        ], 'id=eq.' . (int) $attempt['id']);

        logSecurityEvent('login_number_failed', 'warning', (int) $attempt['user_id'], $attempt['ip_address'], $attempt['fingerprint_hash'], [
            'attempt' => $attempts,
        ]);

        if ($attempts >= LOGIN_NUMBER_MAX_ATTEMPTS) {
            return ['success' => false, 'message' => 'Too many incorrect attempts. Please sign in again.', 'step' => 'failed'];
        }
        return ['success' => false, 'message' => 'Incorrect number. Try again.', 'attempts_left' => LOGIN_NUMBER_MAX_ATTEMPTS - $attempts];
    }

    db_update('login_attempts', [
        'status'             => 'number_passed',
        'number_verified_at' => date('Y-m-d H:i:s'),
    ], 'id=eq.' . (int) $attempt['id']);

    $userId     = (int) $attempt['user_id'];
    $plainEmail = deliverableEmailForUser($userId);
    $user       = getUserById($userId);

    if ($plainEmail === '') {
        return ['success' => false, 'message' => 'Could not resolve your email address. Please sign in again.'];
    }

    if (!mail_is_configured()) {
        return ['success' => false, 'message' => 'Email service is not configured on this server. Contact the administrator.'];
    }

    $otp = createOtpWithTtl($plainEmail, 'login_security', LOGIN_OTP_TTL);
    if ($otp === false) {
        return ['success' => false, 'message' => 'Too many OTP requests. Please wait and try again.'];
    }

    if (!sendOtpEmail($plainEmail, $user['full_name'] ?? 'User', $otp, 'login_security')) {
        return ['success' => false, 'message' => 'Failed to send OTP email. Check Brevo configuration or try again later.'];
    }

    logSecurityEvent('login_number_passed', 'info', (int) $attempt['user_id'], $attempt['ip_address'], $attempt['fingerprint_hash']);

    return [
        'success'       => true,
        'step'          => 'otp',
        'attempt_token' => $attemptToken,
        'message'       => 'Number verified. Enter the OTP sent to your email (expires in 5 minutes).',
    ];
}

function verifyLoginOtpAndComplete(string $attemptToken, string $otpCode, bool $trustDevice = false): array {
    $attempt = findLoginAttempt($attemptToken);
    if (!$attempt || isAttemptExpired($attempt)) {
        return ['success' => false, 'message' => 'Session expired. Please sign in again.'];
    }

    if ($attempt['status'] !== 'number_passed' && $attempt['status'] !== 'otp_passed') {
        return ['success' => false, 'message' => 'Complete previous verification steps first.', 'step' => $attempt['status']];
    }

    $userId     = (int) $attempt['user_id'];
    $plainEmail = deliverableEmailForUser($userId);
    $user       = getUserById($userId);
    if (!$user || $plainEmail === '') {
        return ['success' => false, 'message' => 'Account not found.'];
    }

    $otpAttempts = (int) $attempt['otp_attempts'] + 1;
    $result = verifyOtp($plainEmail, $otpCode, 'login_security');

    if ($result !== 'valid') {
        db_update('login_attempts', [
            'otp_attempts' => $otpAttempts,
            'status'       => $otpAttempts >= LOGIN_OTP_MAX_ATTEMPTS ? 'failed' : $attempt['status'],
        ], 'id=eq.' . (int) $attempt['id']);

        $msg = $result === 'expired' ? 'OTP expired. Please sign in again.' : 'Invalid OTP.';
        if ($otpAttempts >= LOGIN_OTP_MAX_ATTEMPTS) {
            $msg = 'Too many OTP attempts. Please sign in again.';
        }
        logSecurityEvent('login_otp_failed', 'warning', (int) $user['id'], $attempt['ip_address'], $attempt['fingerprint_hash']);
        return ['success' => false, 'message' => $msg, 'step' => $otpAttempts >= LOGIN_OTP_MAX_ATTEMPTS ? 'failed' : 'otp'];
    }

    db_update('login_attempts', [
        'status'       => 'completed',
        'otp_attempts' => $otpAttempts,
        'completed_at' => date('Y-m-d H:i:s'),
    ], 'id=eq.' . (int) $attempt['id']);

    clearFailedPasswordAttempts((int) $user['id']);

    $context = [
        'fingerprint_hash' => $attempt['fingerprint_hash'],
        'ip_address'       => $attempt['ip_address'],
        'user_agent'       => $attempt['user_agent'],
        'browser'          => $attempt['browser'],
        'os'               => $attempt['os'],
    ];

    refreshUserSession($user);
    createUserSession((int) $user['id'], $context);

    if ($trustDevice && !empty($attempt['fingerprint_hash'])) {
        trustDevice((int) $user['id'], $attempt);
    }

    createSystemNotificationSafe('system', 'You logged in successfully.', null, null, (int) $user['id']);
    logSecurityEvent('login_completed', 'info', (int) $user['id'], $attempt['ip_address'], $attempt['fingerprint_hash']);

    startSession();
    unset($_SESSION['pending_login_attempt'], $_SESSION['pending_login_email']);

    return [
        'success'      => true,
        'step'         => 'completed',
        'redirect_url' => dashboardHomeUrl(),
        'user'         => normalizeUserRecord($user),
    ];
}

function getLoginAttemptStatus(string $attemptToken): array {
    $attempt = findLoginAttempt($attemptToken);
    if (!$attempt) {
        return ['success' => false, 'message' => 'Attempt not found.'];
    }

    if (isAttemptExpired($attempt) && !in_array($attempt['status'], ['completed', 'email_denied', 'failed'], true)) {
        db_update('login_attempts', ['status' => 'expired'], 'id=eq.' . (int) $attempt['id']);
        $attempt['status'] = 'expired';
    }

    $payload = [
        'success'       => true,
        'status'        => $attempt['status'],
        'step'          => mapStatusToStep($attempt['status']),
        'attempt_token' => $attemptToken,
        'risk_level'    => $attempt['risk_level'],
    ];

    if ($attempt['status'] === 'email_approved' || $attempt['status'] === 'number_passed') {
        $payload['display_number'] = (int) $attempt['display_number'];
        $payload['number_options'] = array_map('intval', explode(',', $attempt['number_options'] ?? ''));
    }

    return $payload;
}

function mapStatusToStep(string $status): string {
    return match ($status) {
        'pending_verification' => 'awaiting_email',
        'email_approved'       => 'number_match',
        'number_passed'        => 'otp',
        'completed'            => 'completed',
        'email_denied'         => 'denied',
        'denied', 'failed', 'expired' => 'failed',
        default                => $status,
    };
}

function trustDevice(int $userId, array $attempt): void {
    $label = deviceLabel($attempt);
    try {
        getDB()->prepare(
            'INSERT INTO trusted_devices (user_id, fingerprint_hash, device_label, browser, os, ip_address_last, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY))
             ON DUPLICATE KEY UPDATE last_used_at = NOW(), ip_address_last = VALUES(ip_address_last), expires_at = VALUES(expires_at)'
        )->execute([
            $userId,
            $attempt['fingerprint_hash'],
            $label,
            $attempt['browser'],
            $attempt['os'],
            $attempt['ip_address'],
        ]);
        logSecurityEvent('device_trusted', 'info', $userId, $attempt['ip_address'], $attempt['fingerprint_hash'], ['label' => $label]);
    } catch (Throwable $e) {
        error_log('trustDevice: ' . $e->getMessage());
    }
}

function revokeTrustedDevice(int $userId, int $deviceId): bool {
    $row = db_select('trusted_devices', 'id=eq.' . $deviceId . '&user_id=eq.' . $userId . '&limit=1', true);
    if (!$row) {
        return false;
    }
    db_delete('trusted_devices', 'id=eq.' . $deviceId);
    logSecurityEvent('device_untrusted', 'info', $userId, null, $row['fingerprint_hash']);
    return true;
}

function createSystemNotificationSafe(string $type, string $message, ?string $link, ?int $appId, ?int $userId): void {
    if (!function_exists('createSystemNotification')) {
        require_once __DIR__ . '/../notifications.php';
    }
    createSystemNotification($type, $message, $link, $appId, $userId);
}

function unlockUserAccount(int $userId): bool {
    try {
        getDB()->prepare(
            'UPDATE users SET account_locked_until = NULL, failed_login_count = 0, require_password_reset = 0 WHERE id = ?'
        )->execute([$userId]);
        logSecurityEvent('account_unlocked', 'info', $userId, clientIpAddress(), null, ['by' => 'admin']);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function getLoginHistory(int $userId, int $limit = 50): array {
    return db_select(
        'login_attempts',
        'user_id=eq.' . $userId . '&order=created_at.desc&limit=' . min($limit, 100)
    ) ?: [];
}

function getTrustedDevices(int $userId): array {
    return db_select(
        'trusted_devices',
        'user_id=eq.' . $userId . '&order=last_used_at.desc'
    ) ?: [];
}
