<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paths.php';
startSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign-in Blocked — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= url('css/security-auth.css') ?>">
</head>
<body class="security-auth-page">
    <div class="security-auth-panel">
        <div class="auth-icon" aria-hidden="true">🛡️</div>
        <h1>Sign-in blocked</h1>
        <p>You reported an unauthorized sign-in attempt. The login was denied, your account was notified, and the source was flagged.</p>
        <p style="font-size: 0.875rem;">If this was a mistake, try signing in again. After multiple denials, you may need to reset your password.</p>
        <a href="<?= url('login.php') ?>" class="btn btn-accent">Return to sign in</a>
        <p class="auth-sub-link"><a href="<?= url('forgot-password.php') ?>">Reset password</a></p>
    </div>
</body>
</html>
