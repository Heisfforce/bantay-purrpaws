<?php
/**
 * auth/google-callback.php
 * Google OAuth 2.0 callback — exchange code, then OTP (new/link) or sign in (returning).
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/google-oauth.php';

googleOAuthDebugErrors();
startSession();

if (!isGoogleOAuthConfigured()) {
    flash('error', 'Google Sign-In is not configured on this server.');
    header('Location: ' . url('login.php'));
    exit;
}

googleOAuthLoadDeps();

if (isLoggedIn()) {
    header('Location: ' . url(isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$error = '';

do {
    $state         = (string) ($_GET['state'] ?? '');
    $expectedState = (string) ($_SESSION['oauth_state'] ?? '');
    unset($_SESSION['oauth_state']);

    $parsed  = googleOAuthStateParse($state);
    $stateOk = ($parsed !== null && $parsed['valid'])
        || ($expectedState !== '' && hash_equals($expectedState, $state));

    if (!$stateOk) {
        $error = 'Invalid OAuth state. Please try again.';
        break;
    }

    $oauthRedirectUri = ($parsed['redirect_uri'] ?? '') !== ''
        ? $parsed['redirect_uri']
        : googleRedirectUri();

    if (!empty($_GET['error'])) {
        $error = 'Google sign-in was cancelled or denied.';
        break;
    }

    $code = $_GET['code'] ?? '';
    if (!$code) {
        $error = 'No authorization code received from Google.';
        break;
    }

    $exchange = googleExchangeCodeResult($code, $oauthRedirectUri);
    if (!$exchange['ok']) {
        $error = $exchange['error'] ?? ('Failed to exchange authorization code. Redirect URI used: ' . $oauthRedirectUri);
        break;
    }
    $tokens = $exchange['tokens'];

    $googleUser = googleUserInfo($tokens['access_token']);
    if (!$googleUser) {
        $error = 'Failed to retrieve Google account information.';
        break;
    }

    $googleId = $googleUser['sub']   ?? '';
    $email    = $googleUser['email'] ?? '';
    $name     = $googleUser['name']  ?? 'Google User';

    if (!$googleId || !$email) {
        $error = 'Invalid Google account data.';
        break;
    }

    // Returning user with this Google account — no OTP needed
    $existing = findUserByGoogleId($googleId);

    if ($existing) {
        finalizeGoogleSession($existing);
        header('Location: ' . googlePostLoginUrl($existing));
        exit;
    }

    // New account or link to existing email — Google already verified this address
    $localUser = findUserByEmail($email);
    $result    = handleGoogleLogin($googleUser);
    if (!$result['success']) {
        $error = $result['error'] ?? 'Could not complete Google sign-in. Please try again.';
        break;
    }

    $user = $result['user'];
    $msg  = $localUser
        ? 'Your Google account was linked successfully.'
        : 'Welcome! Your Google account is verified.';

    finalizeGoogleSession($user, $msg);
    flash('success', $msg);
    header('Location: ' . googlePostLoginUrl($user));
    exit;

} while (false);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Sign-In — BantayPurrPaws</title>
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-panel fade-in">
        <div class="auth-logo">
            <img src="<?= url('assets/logo.png') ?>" alt="Bantay PurrPaws" class="auth-logo-img">
        </div>
        <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
        <div class="auth-footer">
            <a href="<?= url('login.php') ?>">← Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>
