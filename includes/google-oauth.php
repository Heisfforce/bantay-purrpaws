<?php
/**
 * BantayPurrPaws — Google OAuth 2.0 Helper
 *
 * No Composer required. Uses PHP's file_get_contents / curl for HTTP.
 *
 * Setup (Google Cloud Console):
 *   1. Create an OAuth 2.0 Client ID (Web application).
 *   2. Add Authorized redirect URIs for each environment you use, e.g.:
 *        https://your-production-domain.com/auth/google-callback.php
 *        http://localhost:8000/auth/google-callback.php
 *      Visit /auth/oauth-setup.php on each host to copy the exact URI Google expects.
 *   3. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, APP_URL, and optionally
 *      GOOGLE_REDIRECT_URI in hosting env / .env (see .env.example)
 */

if (!defined('APP_ENV')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
require_once __DIR__ . '/paths.php';

/** Load DB/user helpers only when handling sign-in (keeps oauth-setup lightweight). */
function googleOAuthLoadDeps(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/notifications.php';
    require_once __DIR__ . '/sensitive-data.php';
    require_once __DIR__ . '/users.php';
    $loaded = true;
}

/** Set APP_DEBUG=1 in hosting env to surface PHP errors on OAuth pages. */
function googleOAuthDebugErrors(): void {
    $debug = (string) env_value('APP_DEBUG', '');
    if (in_array(strtolower($debug), ['1', 'true', 'yes', 'on'], true)) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }
}

// ── Configuration (set in hosting env / .env — see auth/oauth-setup.php)
define('GOOGLE_OAUTH_CALLBACK_PATH', 'auth/google-callback.php');
define('GOOGLE_CLIENT_ID', (string) env_value('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', (string) env_value('GOOGLE_CLIENT_SECRET', ''));

/** Whether Google Sign-In can run (client id + secret present in env). */
function isGoogleOAuthConfigured(): bool {
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

/**
 * Redirect URI derived from APP_URL (second-priority fallback).
 */
function googleRedirectUriFromAppUrl(): string {
    $appUrl = rtrim((string) env_value('APP_URL', ''), '/');
    if ($appUrl === '') {
        return '';
    }
    return $appUrl . '/' . GOOGLE_OAUTH_CALLBACK_PATH;
}

/**
 * Redirect URI from the current HTTP request (ignores APP_URL override).
 */
function googleRedirectUriFromRequest(): string {
    $relative = url(GOOGLE_OAUTH_CALLBACK_PATH);
    if ($relative === '' || $relative[0] !== '/') {
        $relative = '/' . ltrim($relative, '/');
    }
    return request_origin() . $relative;
}

/**
 * Redirect URI from the current HTTP request (lowest-priority fallback).
 */
function googleRedirectUriAutoDetected(): string {
    if (!empty($_SERVER['HTTP_HOST'])) {
        return googleRedirectUriFromRequest();
    }
    return absolute_url(GOOGLE_OAUTH_CALLBACK_PATH);
}

/**
 * OAuth redirect URI sent to Google (must match Google Cloud Console exactly).
 *
 * Priority: GOOGLE_REDIRECT_URI → APP_URL + callback path → auto-detected host.
 */
function googleRedirectUri(): string {
    $explicit = rtrim((string) env_value('GOOGLE_REDIRECT_URI', ''), '/');
    if ($explicit !== '') {
        return $explicit;
    }

    $fromAppUrl = googleRedirectUriFromAppUrl();
    if ($fromAppUrl !== '') {
        return $fromAppUrl;
    }

    return googleRedirectUriAutoDetected();
}

/**
 * Snapshot for /auth/oauth-setup.php and deployment checks.
 *
 * @return array{
 *   configured: bool,
 *   client_id_set: bool,
 *   client_secret_set: bool,
 *   redirect_uri: string,
 *   redirect_uri_source: 'GOOGLE_REDIRECT_URI'|'APP_URL'|'auto',
 *   redirect_uri_auto: string,
 *   app_url: string,
 *   mismatch: bool
 * }
 */
function googleOAuthDiagnostics(): array {
    $explicit = rtrim((string) env_value('GOOGLE_REDIRECT_URI', ''), '/');
    $fromAppUrl = googleRedirectUriFromAppUrl();
    $auto = googleRedirectUriAutoDetected();
    $redirectUri = googleRedirectUri();

    $source = 'auto';
    if ($explicit !== '') {
        $source = 'GOOGLE_REDIRECT_URI';
    } elseif ($fromAppUrl !== '') {
        $source = 'APP_URL';
    }

    return [
        'configured'          => isGoogleOAuthConfigured(),
        'client_id_set'       => GOOGLE_CLIENT_ID !== '',
        'client_secret_set'   => GOOGLE_CLIENT_SECRET !== '',
        'redirect_uri'        => $redirectUri,
        'redirect_uri_source' => $source,
        'redirect_uri_auto'   => $auto,
        'app_url'             => rtrim((string) env_value('APP_URL', ''), '/'),
        'mismatch'            => $explicit !== '' && $explicit !== $auto,
    ];
}

define('GOOGLE_AUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_INFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');
define('GOOGLE_OAUTH_STATE_TTL', 900);

/**
 * Secret for signing OAuth state (session-independent — survives Google redirect).
 */
function googleOAuthStateSecret(): string {
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }

    $raw = function_exists('data_encryption_key_raw') ? data_encryption_key_raw() : '';
    if ($raw !== '') {
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            $secret  = ($decoded !== false && $decoded !== '') ? $decoded : $raw;
        } else {
            $secret = $raw;
        }
        return $secret;
    }

    $secret = (string) env_value('GOOGLE_CLIENT_SECRET', '')
        . '|'
        . (string) env_value('BREVO_API_KEY', 'bpp-oauth-state');
    return $secret;
}

/**
 * Base64url encode/decode for OAuth state tokens.
 */
function googleOAuthBase64UrlEncode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function googleOAuthBase64UrlDecode(string $state): ?string {
    if ($state === '') {
        return null;
    }
    $pad = strlen($state) % 4;
    $b64 = $state . ($pad ? str_repeat('=', 4 - $pad) : '');
    $raw = base64_decode(strtr($b64, '-_', '+/'), true);
    return $raw === false ? null : $raw;
}

/**
 * Create a signed OAuth state token (embeds redirect URI for token exchange).
 */
function googleOAuthStateCreate(string $redirectUri): string {
    $redirectUri = rtrim($redirectUri, '/');
    $nonce         = bin2hex(random_bytes(16));
    $exp           = (string) (time() + GOOGLE_OAUTH_STATE_TTL);
    $payload       = $nonce . '|' . $exp . '|' . $redirectUri;
    $sig           = hash_hmac('sha256', $payload, googleOAuthStateSecret());
    return googleOAuthBase64UrlEncode($payload . '|' . $sig);
}

/**
 * Parse and verify OAuth state. Returns null when invalid.
 *
 * @return array{valid: bool, redirect_uri: string}|null
 */
function googleOAuthStateParse(string $state): ?array {
    $raw = googleOAuthBase64UrlDecode($state);
    if ($raw === null) {
        return null;
    }

    $parts = explode('|', $raw);
    if (count($parts) === 4) {
        [$nonce, $exp, $redirectUri, $sig] = $parts;
    } elseif (count($parts) === 3) {
        // Legacy state (no embedded redirect URI)
        [$nonce, $exp, $sig] = $parts;
        $redirectUri = '';
    } else {
        return null;
    }

    if ($nonce === '' || $exp === '' || $sig === '' || !ctype_xdigit($nonce)) {
        return null;
    }
    if ((int) $exp < time()) {
        return null;
    }

    $payload = $redirectUri !== ''
        ? $nonce . '|' . $exp . '|' . $redirectUri
        : $nonce . '|' . $exp;
    $expected = hash_hmac('sha256', $payload, googleOAuthStateSecret());
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    return [
        'valid'         => true,
        'redirect_uri'  => $redirectUri,
    ];
}

/** Verify OAuth state from Google's callback. */
function googleOAuthStateVerify(string $state): bool {
    $parsed = googleOAuthStateParse($state);
    return $parsed !== null && $parsed['valid'];
}

/**
 * Build the Google sign-in URL the user should be redirected to.
 */
function googleAuthUrl(string $state = '', ?string $redirectUri = null): string {
    if (!isGoogleOAuthConfigured()) {
        throw new RuntimeException(
            'Google OAuth is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your hosting environment variables (Railway Variables, or .env locally).'
        );
    }

    $redirectUri = rtrim($redirectUri ?? googleRedirectUri(), '/');

    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    if ($state !== '') {
        $params['state'] = $state;
    }
    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Map Google token endpoint errors to user-facing messages.
 */
function googleTokenErrorMessage(string $error, string $description, string $redirectUri): string {
    return match ($error) {
        'redirect_uri_mismatch' => 'Redirect URI mismatch. In Google Cloud Console, add exactly: ' . $redirectUri,
        'invalid_client'        => 'Invalid Google OAuth client secret. Check GOOGLE_CLIENT_SECRET in Railway Variables matches Google Cloud Console.',
        'invalid_grant'         => 'Authorization code expired or already used. Please click Sign in with Google again.',
        default                 => 'Google sign-in failed (' . $error . '). ' . ($description !== '' ? $description : 'Please try again.'),
    };
}

/**
 * Exchange an auth code for tokens.
 *
 * @return array{ok: bool, tokens?: array, error?: string, google_error?: string}
 */
function googleExchangeCodeResult(string $code, ?string $redirectUri = null): array {
    if (!isGoogleOAuthConfigured()) {
        return ['ok' => false, 'error' => 'Google OAuth is not configured on this server.', 'google_error' => 'not_configured'];
    }

    $redirectUri = rtrim($redirectUri ?? googleRedirectUri(), '/');

    $postData = http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]);

    $response = googleHttpPost(
        GOOGLE_TOKEN_URL,
        $postData,
        ['Content-Type: application/x-www-form-urlencoded']
    );

    $data = json_decode($response ?: '', true);
    if (!empty($data['access_token'])) {
        return ['ok' => true, 'tokens' => $data];
    }

    $googleError = is_array($data) ? (string) ($data['error'] ?? 'unknown') : 'unknown';
    $googleDesc  = is_array($data) ? (string) ($data['error_description'] ?? '') : '';
    error_log('Google token exchange failed: ' . ($response ?: 'empty response'));

    return [
        'ok'           => false,
        'error'        => googleTokenErrorMessage($googleError, $googleDesc, $redirectUri),
        'google_error' => $googleError,
    ];
}

/**
 * Exchange an auth code for tokens.
 * Returns the token array or null on failure.
 */
function googleExchangeCode(string $code, ?string $redirectUri = null): ?array {
    $result = googleExchangeCodeResult($code, $redirectUri);
    return $result['ok'] ? $result['tokens'] : null;
}

function googleHttpPost(string $url, string $body, array $headers = []): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response !== false ? $response : null;
    }

    $headerLines = implode("\r\n", $headers);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => $headerLines,
            'content' => $body,
            'timeout' => 15,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return $response !== false ? $response : null;
}

function googleHttpGet(string $url, array $headers = []): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response !== false ? $response : null;
    }

    $headerLines = implode("\r\n", $headers);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => $headerLines,
            'timeout' => 15,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return $response !== false ? $response : null;
}

/**
 * Fetch the Google user-info using an access token.
 * Returns ['sub', 'email', 'name', 'picture'] or null.
 */
function googleUserInfo(string $accessToken): ?array {
    $response = googleHttpGet(GOOGLE_INFO_URL, ['Authorization: Bearer ' . $accessToken]);
    $data = json_decode($response ?: '', true);
    return (!empty($data['sub'])) ? $data : null;
}

/**
 * Find a user by Google subject id.
 */
function findUserByGoogleId(string $googleId): ?array {
    googleOAuthLoadDeps();
    $user = db_select('users', 'google_id=eq.' . urlencode($googleId), true);
    return $user ? hydrateUserSensitiveFields($user) : null;
}

/**
 * Main entry point called from the OAuth callback.
 *
 * Given a Google user-info array this function:
 *   a) If a local user with the same google_id exists → log them in.
 *   b) If a local user with the same email exists but no google_id → link and log in.
 *   c) If no user exists → create a new 'user' account and log in.
 *
 * Returns ['success' => true, 'user' => [...]] or ['success' => false, 'error' => '...'].
 */
function handleGoogleLogin(array $googleUser): array {
    googleOAuthLoadDeps();
    $googleId  = $googleUser['sub']     ?? '';
    $email     = $googleUser['email']   ?? '';
    $name      = $googleUser['name']    ?? 'Google User';
    $avatar    = $googleUser['picture'] ?? null;

    if (!$googleId || !$email) {
        return ['success' => false, 'error' => 'Invalid Google account data.'];
    }

    $user = findUserByGoogleId($googleId);

    if (!$user) {
        $user = findUserByEmail($email);

        if ($user) {
            db_update('users', [
                'google_id'       => $googleId,
                'avatar_url'      => $avatar,
                'email_verified'  => true,
                'auth_provider'   => 'google',
            ], 'id=eq.' . (int) $user['id']);

            $user['google_id']      = $googleId;
            $user['avatar_url']     = $avatar;
            $user['email_verified'] = true;
            $user['auth_provider']  = 'google';

            createSystemNotification(
                'system',
                'Your Google account was linked to your BantayPurrPaws profile.',
                null,
                null,
                (int) $user['id']
            );
        } else {
            $user = insertUserRecord([
                'full_name'      => $name,
                'email'          => $email,
                'password'       => null,
                'role'           => 'user',
                'google_id'      => $googleId,
                'avatar_url'     => $avatar,
                'email_verified' => true,
                'auth_provider'  => 'google',
            ]);

            if (!$user) {
                return ['success' => false, 'error' => 'Could not create your account. Please try again.'];
            }

            createSystemNotification(
                'system',
                'Welcome to BantayPurrPaws! Your account has been created via Google.',
                null,
                null,
                (int) $user['id']
            );
        }
    }

    if ($avatar && ($user['avatar_url'] ?? '') !== $avatar) {
        db_update('users', ['avatar_url' => $avatar], 'id=eq.' . (int) $user['id']);
        $user['avatar_url'] = $avatar;
    }

    return ['success' => true, 'user' => $user];
}

/**
 * Start session and notify after Google auth is complete.
 */
function finalizeGoogleSession(array $user, string $message = 'You signed in with Google.'): void {
    googleOAuthLoadDeps();
    refreshUserSession($user);

    createSystemNotification(
        'system',
        $message,
        null,
        null,
        (int) $user['id']
    );
}

/**
 * Dashboard URL for a user role after Google sign-in.
 */
function googlePostLoginUrl(array $user): string {
    return in_array($user['role'] ?? 'user', ['admin', 'staff'], true)
        ? absolute_url('admin/dashboard.php')
        : absolute_url('dashboard.php');
}
