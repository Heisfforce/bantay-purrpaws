<?php
/**
 * Shows the redirect URI your app sends to Google.
 * Visit after upload: /auth/oauth-setup.php
 * Remove or protect this file when OAuth works.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/google-oauth.php';

header('Content-Type: text/html; charset=UTF-8');

$diag        = googleOAuthDiagnostics();
$redirectUri = $diag['redirect_uri'];
$authSample  = $diag['configured'] ? googleAuthUrl('test-state-only') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google OAuth setup</title>
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
    </style>
</head>
<body>
    <h1>Google OAuth setup</h1>

    <h2>Environment variables</h2>
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
    <p><span class="bad">Sign-in will fail</span> until <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> are set.</p>
    <p>On <strong>Railway</strong>, open your service → <em>Variables</em> and add all Google OAuth variables. The <code>.env</code> file is not deployed with git.</p>
    <?php endif; ?>

    <h2>Redirect URI (use in Google Cloud)</h2>
    <p>Copy this <strong>exact</strong> value into Google Cloud Console → Credentials → your OAuth client → <em>Authorized redirect URIs</em>:</p>
    <p class="uri"><code><?= htmlspecialchars($redirectUri) ?></code></p>
    <p><small>Source: <code><?= htmlspecialchars($diag['redirect_uri_source']) ?></code></small></p>

    <?php if ($diag['mismatch']): ?>
    <p class="warn">Warning: <code>GOOGLE_REDIRECT_URI</code> differs from the auto-detected callback
        (<code><?= htmlspecialchars($diag['redirect_uri_auto']) ?></code>). Google Console must match the explicit value above.</p>
    <?php endif; ?>

    <h2>Auto-detected (for comparison)</h2>
    <ul>
        <li>Host: <code><?= htmlspecialchars(request_host()) ?></code></li>
        <li>Scheme: <code><?= htmlspecialchars(request_scheme()) ?></code></li>
        <li>App base path: <code><?= htmlspecialchars(app_base() ?: '/') ?></code></li>
        <li>Request callback: <code><?= htmlspecialchars($diag['redirect_uri_auto']) ?></code></li>
        <?php if ($diag['app_url'] !== ''): ?>
        <li>From <code>APP_URL</code>: <code><?= htmlspecialchars(googleRedirectUriFromAppUrl()) ?></code></li>
        <?php endif; ?>
        <li>Canonical origin: <code><?= htmlspecialchars(app_origin()) ?></code></li>
    </ul>

    <?php if ($authSample !== ''): ?>
    <h2>Sample auth URL</h2>
    <pre><?= htmlspecialchars($authSample) ?></pre>
    <?php endif; ?>

    <h2>Required hosting variables (Railway / production)</h2>
    <pre>GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
APP_URL=https://your-domain.example.com
GOOGLE_REDIRECT_URI=https://your-domain.example.com/auth/google-callback.php</pre>
    <p><small><code>GOOGLE_REDIRECT_URI</code> is optional if <code>APP_URL</code> is set; it must still match Google Console exactly.</small></p>

    <h2>Steps in Google Cloud</h2>
    <ol>
        <li>Open <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">APIs &amp; Credentials</a>.</li>
        <li>Edit your <strong>Web application</strong> OAuth 2.0 Client ID.</li>
        <li>Under <strong>Authorized redirect URIs</strong>, add the URI in the blue box above (one line, no trailing slash on the path).</li>
        <li>Save. Wait 1–5 minutes, then try Sign in with Google again.</li>
    </ol>
</body>
</html>
