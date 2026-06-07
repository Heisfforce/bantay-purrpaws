<?php
/**
 * Deployment diagnostics: Google OAuth + email delivery.
 * Visit after upload: /auth/oauth-setup.php
 * Remove or protect this file when everything works.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/google-oauth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

header('Content-Type: text/html; charset=UTF-8');

$diag        = googleOAuthDiagnostics();
$redirectUri = $diag['redirect_uri'];
$authSample  = $diag['configured']
    ? googleAuthUrl(googleOAuthStateCreate($redirectUri), $redirectUri)
    : '';

$mail        = smtp_config();
$mailReady   = mail_is_configured();
$sodium      = extension_loaded('sodium');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deployment setup — BantayPurrPaws</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 16px; line-height: 1.5; }
        code, pre { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; word-break: break-all; }
        pre { padding: 12px; overflow-x: auto; }
        .uri { font-size: 15px; font-weight: 600; color: #0b57d0; }
        .ok { color: #0a0; font-weight: 600; }
        .warn { color: #b45309; font-weight: 600; }
        .bad { color: #c00; font-weight: 600; }
        table { border-collapse: collapse; width: 100%; margin: 12px 0; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        th { width: 38%; font-weight: 600; }
        nav { margin: 0 0 24px; font-size: 14px; }
        nav a { margin-right: 16px; }
        hr { border: 0; border-top: 1px solid #e5e5e5; margin: 32px 0; }
    </style>
</head>
<body>
    <h1>Deployment setup</h1>
    <nav>
        <a href="#google">Google OAuth</a>
        <a href="#email">Email (Brevo)</a>
    </nav>

    <h2 id="google">Google OAuth</h2>

    <table>
        <tr>
            <th><code>GOOGLE_CLIENT_ID</code></th>
            <td><?= $diag['client_id_set'] ? '<span class="ok">set</span>' : '<span class="bad">missing</span> — required' ?></td>
        </tr>
        <tr>
            <th><code>GOOGLE_CLIENT_SECRET</code></th>
            <td><?= $diag['client_secret_set'] ? '<span class="ok">set</span>' : '<span class="bad">missing</span> — required' ?></td>
        </tr>
        <tr>
            <th><code>APP_URL</code></th>
            <td><?= $diag['app_url'] !== '' ? '<span class="ok">set</span> — <code>' . htmlspecialchars($diag['app_url']) . '</code>' : '<span class="warn">not set</span> — redirect falls back to request host' ?></td>
        </tr>
        <tr>
            <th><code>GOOGLE_REDIRECT_URI</code></th>
            <td><?= $diag['redirect_uri_source'] === 'GOOGLE_REDIRECT_URI' ? '<span class="ok">set (explicit override)</span>' : '<span class="warn">not set</span> — derived from APP_URL or auto-detected host' ?></td>
        </tr>
    </table>

    <?php if (!$diag['configured']): ?>
    <p><span class="bad">Google Sign-In will fail</span> until <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> are set in Railway Variables.</p>
    <?php endif; ?>

    <h3>Redirect URI (Google Cloud Console)</h3>
    <p>Copy this <strong>exact</strong> value into Google Cloud → Credentials → OAuth client → <em>Authorized redirect URIs</em>:</p>
    <p class="uri"><code><?= htmlspecialchars($redirectUri) ?></code></p>
    <p><small>Source: <code><?= htmlspecialchars($diag['redirect_uri_source']) ?></code></small></p>

    <?php if ($diag['mismatch']): ?>
    <p class="warn">Warning: <code>GOOGLE_REDIRECT_URI</code> differs from the auto-detected callback
        (<code><?= htmlspecialchars($diag['redirect_uri_auto']) ?></code>).</p>
    <?php endif; ?>

    <?php if ($authSample !== ''): ?>
    <h3>Sample auth URL</h3>
    <pre><?= htmlspecialchars($authSample) ?></pre>
    <?php endif; ?>

    <hr>

    <h2 id="email">Email (Brevo)</h2>

    <table>
        <tr>
            <th><code>BREVO_API_KEY</code></th>
            <td><?= $mail['brevo_api_key'] !== '' ? '<span class="ok">set</span>' : '<span class="bad">missing</span> — OTP and login emails will fail' ?></td>
        </tr>
        <tr>
            <th><code>MAIL_FROM</code></th>
            <td><?= $mail['from_email'] !== '' ? '<span class="ok">set</span> — <code>' . htmlspecialchars($mail['from_email']) . '</code>' : '<span class="bad">missing</span>' ?></td>
        </tr>
        <tr>
            <th><code>MAIL_DRIVER</code></th>
            <td><code><?= htmlspecialchars($mail['driver']) ?></code></td>
        </tr>
        <tr>
            <th><code>DATA_ENCRYPTION_KEY</code></th>
            <td><?= env_value('DATA_ENCRYPTION_KEY', '') !== '' ? '<span class="ok">set</span>' : '<span class="warn">not set</span> — using derived fallback key' ?></td>
        </tr>
        <tr>
            <th>PHP sodium extension</th>
            <td><?= $sodium ? '<span class="ok">enabled</span>' : '<span class="bad">missing</span> — encrypted user emails cannot be decrypted' ?></td>
        </tr>
        <tr>
            <th>Ready to send mail</th>
            <td><?= $mailReady ? '<span class="ok">yes</span>' : '<span class="bad">no</span>' ?></td>
        </tr>
    </table>

    <?php if (!$mailReady): ?>
    <p><span class="bad">Sign-in and OTP emails will fail</span> until <code>BREVO_API_KEY</code> and <code>MAIL_FROM</code> are set in Railway Variables.</p>
    <?php endif; ?>

    <?php if (!$sodium): ?>
    <p><span class="bad">Sodium extension missing.</span> Redeploy with the updated Dockerfile so encrypted emails can be read. Login may still work if you sign in with the same email used at registration.</p>
    <?php endif; ?>

    <h3>Brevo checklist</h3>
    <ol>
        <li><code>MAIL_FROM</code> must be a <strong>verified sender</strong> in Brevo → Senders &amp; Domains.</li>
        <li>If Brevo IP restriction is enabled, add your Railway outbound IP under Security → Authorised IPs (or disable the restriction).</li>
        <li>API key must have transactional email permission.</li>
    </ol>

    <hr>

    <h2>Required Railway Variables</h2>
    <pre>APP_URL=https://bantay-purrpaws-production-9d10.up.railway.app
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://bantay-purrpaws-production-9d10.up.railway.app/auth/google-callback.php
BREVO_API_KEY=xkeysib-...
MAIL_FROM=you@verified-domain.com
MAIL_FROM_NAME=BantayPurrPaws
DATA_ENCRYPTION_KEY=base64:...</pre>
    <p><small>The <code>.env</code> file is not deployed — set every value above in Railway → Variables, then redeploy.</small></p>
</body>
</html>
