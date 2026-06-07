<?php
/**
 * BantayPurrPaws — Email Helper
 *
 * Sends mail via the Brevo (Sendinblue) Transactional Email API over HTTPS.
 * Works on InfinityFree and other shared hosts where outbound SMTP may be blocked.
 *
 * Setup: add BREVO_API_KEY and MAIL_FROM to your .env file.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/logger.php';

$c = smtp_config();
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', $c['brevo_api_key']);
}
if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', $c['from_email']);
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', $c['from_name']);
}
if (!defined('MAIL_DRIVER')) {
    define('MAIL_DRIVER', $c['driver']);
}
if (!defined('MAIL_APP_COLOR')) {
    define('MAIL_APP_COLOR', '#7c6f5b');
}

define('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email');

/**
 * Send one email through Brevo. Returns true on success, false on failure.
 */
function sendRawEmail(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        bpp_log('mailer', 'error', 'Invalid recipient email address.', ['to' => $to]);
        return false;
    }

    if (!mail_is_configured()) {
        bpp_log('mailer', 'error', 'Email not configured. Set BREVO_API_KEY and MAIL_FROM in hosting Variables or .env.');
        return false;
    }

    if (MAIL_FROM === '' || !filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
        bpp_log('mailer', 'error', 'Invalid or missing MAIL_FROM in .env');
        return false;
    }

    // Primary: Brevo Transactional API (HTTPS — works on InfinityFree)
    if (BREVO_API_KEY !== '') {
        if (sendViaBrevoApi($to, $subject, $htmlBody, $toName)) {
            return true;
        }
        bpp_log('mailer', 'warning', 'Brevo API send failed; trying fallback if configured.', ['to' => $to]);
    }

    $driver = defined('MAIL_DRIVER') ? MAIL_DRIVER : 'brevo';

    // Fallback: SMTP
    if ($driver === 'smtp' || smtp_config()['smtp_host'] !== '') {
        if (send_via_smtp($to, $subject, $htmlBody, $toName)) {
            bpp_log('mailer', 'info', 'Email sent via SMTP fallback.', ['to' => $to]);
            return true;
        }
    }

    // Fallback: PHP mail()
    if ($driver === 'mail' || $driver === 'smtp') {
        if (send_via_php_mail($to, $subject, $htmlBody, $toName)) {
            bpp_log('mailer', 'info', 'Email sent via PHP mail() fallback.', ['to' => $to]);
            return true;
        }
    }

    bpp_log('mailer', 'error', 'All email delivery methods failed.', ['to' => $to]);
    return false;
}

/**
 * Send one email through Brevo API.
 */
function sendViaBrevoApi(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
    if (BREVO_API_KEY === '') {
        return false;
    }

    $recipient = ['email' => $to];
    if ($toName !== '') {
        $recipient['name'] = $toName;
    }

    $payload = [
        'sender'      => ['email' => MAIL_FROM, 'name' => MAIL_FROM_NAME],
        'to'          => [$recipient],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody))),
    ];

    $ch = curl_init(BREVO_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        bpp_log('mailer', 'error', 'Brevo cURL error.', ['error' => $curlError, 'to' => $to]);
        return false;
    }

    if ($httpCode >= 400) {
        $hint = '';
        if ($httpCode === 401 && is_string($response) && str_contains($response, 'IP address')) {
            $hint = 'Add your server IP in Brevo → Security → Authorised IPs, or disable IP restriction.';
        } elseif ($httpCode === 401) {
            $hint = 'Check BREVO_API_KEY in hosting Variables and that MAIL_FROM is verified in Brevo.';
        }
        bpp_log('mailer', 'error', 'Brevo API error.' . ($hint !== '' ? ' ' . $hint : ''), [
            'http_code' => $httpCode,
            'response'  => $response,
            'to'        => $to,
        ]);
        return false;
    }

    bpp_log('mailer', 'info', 'Email sent.', ['to' => $to, 'subject' => $subject]);
    return true;
}

/**
 * Wraps content in a branded email shell.
 */
function emailShell(string $title, string $innerHtml): string {
    $color  = MAIL_APP_COLOR;
    $name   = APP_NAME;
    $year   = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f5f2ef;font-family:Georgia,'Times New Roman',serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f2ef;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr>
          <td style="background:{$color};padding:28px 36px;text-align:center;">
            <span style="font-size:22px;font-weight:700;color:#fff;letter-spacing:.5px;">{$name}</span>
            <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px;letter-spacing:1px;text-transform:uppercase;">Stray Animal Rescue &amp; Adoption System</div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 40px;color:#3d3530;font-size:15px;line-height:1.65;">
            {$innerHtml}
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#faf8f6;border-top:1px solid #ede9e4;padding:20px 40px;text-align:center;font-size:12px;color:#9c8f84;">
            &copy; {$year} {$name} &mdash; This is an automated message, please do not reply.<br>
            If you did not request this email, you can safely ignore it.
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Send a 6-digit OTP email.
 *
 * @param string $to      Recipient email
 * @param string $name    Recipient display name
 * @param string $otp     6-digit code
 * @param string $purpose 'registration' | 'password_reset' | 'google_link'
 */
function sendOtpEmail(string $to, string $name, string $otp, string $purpose = 'registration'): bool {
    $purposeLabel = match ($purpose) {
        'password_reset' => 'reset your password',
        'google_link'    => 'link your Google account',
        'login'          => 'sign in to your account',
        'login_security' => 'complete your secure sign-in',
        default          => 'verify your email address',
    };

    $ttlLabel = match ($purpose) {
        'login_security' => '5 minutes',
        default          => '15 minutes',
    };

    $color = MAIL_APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 24px;color:#6b5f56;">
  You requested to {$purposeLabel} on <strong style="color:{$color};">BantayPurrPaws</strong>.
  Use the one-time code below — it expires in <strong>{$ttlLabel}</strong>.
</p>

<!-- OTP Code Box -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
  <tr>
    <td align="center">
      <div style="display:inline-block;background:#faf5f0;border:2px dashed {$color};border-radius:10px;padding:20px 40px;">
        <span style="font-family:'Courier New',monospace;font-size:38px;font-weight:700;letter-spacing:12px;color:{$color};">{$otp}</span>
      </div>
    </td>
  </tr>
</table>

<p style="margin:0 0 8px;font-size:13px;color:#9c8f84;">
  &bull; Do <strong>not</strong> share this code with anyone.<br>
  &bull; If you didn&rsquo;t request this, please ignore this email.
</p>
HTML;

    $subject = match ($purpose) {
        'password_reset' => APP_NAME . ' — Password Reset OTP',
        'google_link'    => APP_NAME . ' — Google Account Link OTP',
        'login'          => APP_NAME . ' — Login Verification OTP',
        'login_security' => APP_NAME . ' — Secure Sign-In OTP',
        default          => APP_NAME . ' — Email Verification OTP',
    };

    return sendRawEmail($to, $subject, emailShell($subject, $inner), $name);
}

function sendReportApprovedEmail(string $to, string $name, string $reportCode): bool {
    $color = MAIL_APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your rescue report <strong style="color:{$color};">{$reportCode}</strong> has been marked as
  <strong>rescued</strong>. Thank you for helping us save a stray animal.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  You can sign in to BantayPurrPaws anytime to view your report history.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Rescue Report Approved', emailShell('Rescue Report Approved', $inner), $name);
}

function sendReportRejectedEmail(string $to, string $name, string $reportCode): bool {
    $color = MAIL_APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your rescue report <strong style="color:{$color};">{$reportCode}</strong> could not be completed
  and has been marked as <strong>failed</strong>.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  If you have questions, please contact the BantayPurrPaws team.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Rescue Report Update', emailShell('Rescue Report Update', $inner), $name);
}

function sendPetSubmissionApprovedEmail(string $to, string $name, string $petName): bool {
    $color = MAIL_APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Congratulations, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your adoption application for <strong style="color:{$color};">{$petName}</strong> has been
  <strong>approved</strong>. Our team will contact you with next steps.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  Thank you for choosing adoption through BantayPurrPaws.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Adoption Application Approved', emailShell('Adoption Approved', $inner), $name);
}

function sendPetSubmissionRejectedEmail(string $to, string $name, string $petName): bool {
    $color = MAIL_APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Thank you for your interest in adopting <strong style="color:{$color};">{$petName}</strong>.
  After review, your application was <strong>not approved</strong> at this time.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  You may browse other pets available for adoption on BantayPurrPaws.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Adoption Application Update', emailShell('Adoption Application Update', $inner), $name);
}

function sendAnnouncementEmail(string $to, string $name, string $message, string $linkUrl = 'announcements.php'): bool {
    $color   = MAIL_APP_COLOR;
    $safeMsg = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $link    = absolute_url(ltrim($linkUrl, '/'));
    $inner   = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  The BantayPurrPaws team has posted a new announcement:
</p>
<div style="background:#faf8f6;border-left:4px solid {$color};padding:16px 20px;margin:0 0 24px;border-radius:4px;">
  {$safeMsg}
</div>
<p style="margin:0 0 16px;color:#6b5f56;">
  <a href="{$link}" style="color:{$color};font-weight:600;">View on BantayPurrPaws</a>
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — New Announcement', emailShell('New Announcement', $inner), $name);
}
function sendStaffInviteEmail(string $to, array $permissions, string $setupLink): bool {
    $color = MAIL_APP_COLOR;
    $permLabels = [
        'manage_reports'     => 'Manage Rescue Reports',
        'manage_pets'        => 'Manage Pet Listings',
        'review_adoptions'   => 'Review Adoption Applications',
        'view_adoptions'     => 'View Adoption Queue',
        'manage_users'       => 'Manage Regular Users',
        'post_announcements' => 'Post Announcements',
    ];
    $permList = '';
    if (!empty($permissions)) {
        $items = '';
        foreach ($permissions as $key) {
            $label = $permLabels[$key] ?? $key;
            $items .= "<li style=\"margin-bottom:4px;\">{$label}</li>";
        }
        $permList = "<p style=\"margin:0 0 8px;color:#6b5f56;\">You have been granted access to:</p><ul style=\"margin:0 0 24px;padding-left:20px;color:#6b5f56;\">{$items}</ul>";
    }
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">You've been invited to join BantayPurrPaws!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  An administrator has created a staff account for you on <strong style="color:{$color};">BantayPurrPaws</strong>. Click the button below to complete your account setup.
</p>
{$permList}
<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
  <tr><td align="center">
    <a href="{$setupLink}" style="display:inline-block;background:{$color};color:#fff;font-weight:600;font-size:15px;padding:14px 32px;border-radius:8px;text-decoration:none;">
      Complete My Staff Account
    </a>
  </td></tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#9c8f84;">
  &bull; This invitation link expires in <strong>24 hours</strong>.<br>
  &bull; If you did not expect this email, you can safely ignore it.
</p>
HTML;
    return sendRawEmail($to, APP_NAME . ' — Staff Account Invitation', emailShell('Staff Invitation', $inner), '');
}

function sendPasswordChangedEmail(string $to, string $name): bool {
    $color = MAIL_APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your <strong style="color:{$color};">BantayPurrPaws</strong> account password was recently changed.
</p>
<p style="margin:0 0 8px;color:#6b5f56;">
  If you made this change, no further action is needed.
</p>
<p style="margin:0;color:#ef4444;font-size:13px;">
  If you did <strong>not</strong> make this change, please contact us immediately or reset your password.
</p>
HTML;
    return sendRawEmail($to, APP_NAME . ' — Your Password Was Changed', emailShell('Password Changed', $inner), $name);
}

function sendRegistrationLinkEmail(string $to, string $name, string $verificationToken): bool {
    $color = MAIL_APP_COLOR;
    $link  = url('auth/verify-registration.php?token=' . urlencode($verificationToken));
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Welcome to <strong style="color:{$color};">BantayPurrPaws</strong>! Please verify your email address to continue creating your account.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
  <tr><td align="center">
    <a href="{$link}" style="display:inline-block;background:{$color};color:#fff;font-weight:600;font-size:15px;padding:14px 32px;border-radius:8px;text-decoration:none;">
      Verify Email Address
    </a>
  </td></tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#9c8f84;">
  &bull; This verification link expires in <strong>15 minutes</strong>.<br>
  &bull; If you did not create an account, you can safely ignore this email.
</p>
<p style="margin:16px 0 0;font-size:12px;color:#9c8f84;word-break:break-all;">
  Or copy this link: {$link}
</p>
HTML;
    return sendRawEmail($to, APP_NAME . ' — Verify Your Email', emailShell('Email Verification', $inner), $name);
}

function sendRegistrationCodeEmail(string $to, string $name, string $code): bool {
    $color = MAIL_APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 24px;color:#6b5f56;">
  Your email link was verified. Enter this code on the registration page to activate your
  <strong style="color:{$color};">BantayPurrPaws</strong> account. It expires in <strong>5 minutes</strong>.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
  <tr>
    <td align="center">
      <div style="display:inline-block;background:#faf5f0;border:2px dashed {$color};border-radius:10px;padding:20px 40px;">
        <span style="font-family:'Courier New',monospace;font-size:38px;font-weight:700;letter-spacing:12px;color:{$color};">{$code}</span>
      </div>
    </td>
  </tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#9c8f84;">
  &bull; Do <strong>not</strong> share this code with anyone.<br>
  &bull; You have a maximum of <strong>3 attempts</strong> before verification is locked.
</p>
HTML;
    return sendRawEmail($to, APP_NAME . ' — Registration Verification Code', emailShell('Verification Code', $inner), $name);
}


/**
 * Verify that an email address is reachable by performing:
 *   1. PHP syntax validation
 *   2. DNS MX / A record check (no real email sent)
 *   3. Brevo contact lookup to detect blacklisted / invalid addresses
 *
 * Returns true if the address looks deliverable, or an error string.
 */
function verifyEmailDeliverability(string $email): bool|string
{
    // 1. Syntax guard
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    // 2. MX / DNS check
    $domain = substr(strrchr($email, '@'), 1);
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return 'The email domain does not exist or cannot receive mail. Please check the address.';
    }

    // 3. Brevo contact lookup (non-destructive — no email sent)
    if (BREVO_API_KEY === '') {
        // Brevo not configured — DNS check is sufficient; allow through
        return true;
    }

    $encodedEmail = rawurlencode($email);
    $ch = curl_init("https://api.brevo.com/v3/contacts/{$encodedEmail}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        // Network error — fall back to DNS-passed result rather than blocking sign-ups
        bpp_log('mailer', 'warning', 'Email deliverability check cURL error; falling back to DNS result.', ['error' => $curlError]);
        return true;
    }

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        // Brevo marks hard-bounced / unsubscribed contacts as blacklisted
        if (!empty($data['emailBlacklisted'])) {
            return 'This email address has been flagged as undeliverable. Please use a different address.';
        }
        return true;
    }

    if ($httpCode === 404) {
        // Unknown to Brevo but DNS passed — brand-new valid address
        return true;
    }

    if ($httpCode === 400) {
        return 'That email address appears to be invalid or undeliverable. Please use a different address.';
    }

    // Unexpected HTTP code — log and allow (don't block sign-ups on Brevo downtime)
    bpp_log('mailer', 'warning', 'Email deliverability check returned unexpected code; allowing.', ['code' => $httpCode]);
    return true;
}

/**
 * Notify account owner of a new sign-in attempt with Yes/No actions.
 */
function sendLoginAttemptEmail(string $to, string $name, string $challengeToken, array $context, array $risk): bool {
    require_once __DIR__ . '/paths.php';
    $color   = MAIL_APP_COLOR;
    $yesUrl  = absolute_url('auth/login-email-response.php?token=' . urlencode($challengeToken) . '&action=yes');
    $noUrl   = absolute_url('auth/login-email-response.php?token=' . urlencode($challengeToken) . '&action=no');
    $time    = date('M j, Y g:i A T');
    $device  = htmlspecialchars(($context['browser'] ?? 'Browser') . ' on ' . ($context['os'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
    $loc     = htmlspecialchars($context['location_label'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
    $riskLbl = htmlspecialchars(ucfirst($risk['risk_level'] ?? 'low'), ENT_QUOTES, 'UTF-8');

    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">New sign-in attempt</h2>
<p style="margin:0 0 16px;color:#6b5f56;">Hello {$name}, someone is trying to sign in to your <strong style="color:{$color};">BantayPurrPaws</strong> account.</p>
<table width="100%" cellpadding="8" cellspacing="0" style="background:#faf8f6;border-radius:8px;margin:0 0 24px;font-size:14px;color:#44403c;">
<tr><td><strong>Time</strong></td><td>{$time}</td></tr>
<tr><td><strong>Device</strong></td><td>{$device}</td></tr>
<tr><td><strong>Location</strong></td><td>{$loc}</td></tr>
<tr><td><strong>Risk</strong></td><td>{$riskLbl}</td></tr>
</table>
<p style="margin:0 0 20px;color:#6b5f56;">Was this you?</p>
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td align="center" style="padding:8px;">
<a href="{$yesUrl}" style="display:inline-block;background:#10b981;color:#fff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;">Yes, It's Me</a>
</td>
<td align="center" style="padding:8px;">
<a href="{$noUrl}" style="display:inline-block;background:#dc2626;color:#fff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;">No, It's Not Me</a>
</td>
</tr></table>
<p style="margin:20px 0 0;font-size:13px;color:#9c8f84;">If you did not attempt to sign in, click <strong>No</strong> to block the attempt.</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Sign-in attempt detected', emailShell('Sign-in attempt', $inner), $name);
}

function sendNumberMatchEmail(string $to, string $name, array $numbers, int $displayNumber, string $deviceLabel): bool {
    $color = MAIL_APP_COLOR;
    $nums  = implode(', ', array_map('intval', $numbers));
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Confirm your sign-in</h2>
<p style="margin:0 0 16px;color:#6b5f56;">Hello {$name}, on your sign-in screen you should see the number <strong style="font-size:24px;color:{$color};">{$displayNumber}</strong>.</p>
<p style="margin:0 0 16px;color:#6b5f56;">Select the matching number from these options on your device ({$deviceLabel}):</p>
<p style="font-size:28px;font-weight:700;letter-spacing:8px;color:{$color};text-align:center;margin:24px 0;">{$nums}</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">Only continue if you initiated this sign-in.</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Number matching verification', emailShell('Number verification', $inner), $name);
}

function sendLoginBlockedEmail(string $to, string $name, array $attempt): bool {
    $device = htmlspecialchars(($attempt['browser'] ?? '') . ' on ' . ($attempt['os'] ?? ''), ENT_QUOTES, 'UTF-8');
    $loc    = htmlspecialchars($attempt['location_label'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
    $inner  = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#991b1b;">Sign-in blocked</h2>
<p style="margin:0 0 16px;color:#6b5f56;">Hello {$name}, a sign-in attempt to your account was blocked because you reported it as unauthorized.</p>
<table width="100%" cellpadding="8" cellspacing="0" style="background:#fef2f2;border-radius:8px;margin:0 0 16px;font-size:14px;">
<tr><td><strong>Device</strong></td><td>{$device}</td></tr>
<tr><td><strong>Location</strong></td><td>{$loc}</td></tr>
</table>
<p style="margin:0;font-size:13px;color:#9c8f84;">If you suspect compromise, reset your password immediately.</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Unauthorized sign-in blocked', emailShell('Sign-in blocked', $inner), $name);
}
