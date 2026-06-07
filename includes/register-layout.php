<?php
/**
 * Shared layout for registration verification pages (matches login.php design).
 */

function registerPageHead(string $cardTitle, string $cardSubtitle = '', int $activeStep = 0): void {
    $heroTitle = 'Create your account.<br><em>Join the rescue.</em>';
    $heroText  = 'Report strays, adopt pets, and make a difference in your community.';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cardTitle) ?> — BantayPurrPaws</title>
    <?php require_once __DIR__ . '/security/csrf.php'; echo csrfMetaTag(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/auth-page.css') ?>">
    <link rel="stylesheet" href="<?= url('css/register-verify.css') ?>">
</head>
<body class="auth-page-body">

<div class="login-bg">
    <img src="<?= url('assets/dog.jpg') ?>" alt="" class="login-bg-img">
    <div class="login-bg-overlay"></div>
</div>

<div class="login-hero" aria-hidden="true">
    <span class="login-hero-paws">🐾</span>
    <h1><?= $heroTitle ?></h1>
    <p><?= htmlspecialchars($heroText) ?></p>
    <div class="login-hero-badges">
        <span class="login-hero-badge">✓ Secure verification</span>
        <span class="login-hero-badge">✓ Email ownership</span>
    </div>
</div>

<div class="login-panel">
    <div class="login-card">

        <a href="<?= url('register.php') ?>" class="card-brand">
            <?php if (is_file(dirname(__DIR__) . '/assets/logo.png')): ?>
                <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="card-brand-img">
            <?php else: ?>
                <span class="card-brand-mark">🐾</span>
            <?php endif; ?>
            <span class="card-brand-name">BantayPurrPaws</span>
        </a>

        <div class="card-welcome">
            <h2><?= htmlspecialchars($cardTitle) ?></h2>
            <?php if ($cardSubtitle !== ''): ?>
                <p><?= htmlspecialchars($cardSubtitle) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($activeStep > 0) {
            registerStepIndicator($activeStep);
        } ?>

        <div class="register-content">
    <?php
}

function registerStepIndicator(int $activeStep): void {
    $steps = [
        1 => 'Register',
        2 => 'Email',
        3 => 'Code',
    ];
    ?>
    <div class="card-mfa-steps" aria-label="Registration progress">
        <?php foreach ($steps as $num => $label): ?>
            <?php if ($num > 1): ?>
                <div class="card-mfa-connector<?= $num <= $activeStep ? ' done' : '' ?>"></div>
            <?php endif; ?>
            <div class="card-mfa-step<?= $num < $activeStep ? ' done' : ($num === $activeStep ? ' active' : '') ?>">
                <div class="card-mfa-step-dot"><?= $num < $activeStep ? '✓' : $num ?></div>
                <span><?= htmlspecialchars($label) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function registerStatusIcon(string $type, string $symbol): void {
    ?>
    <div class="reg-status-icon reg-status-<?= htmlspecialchars($type) ?>" aria-hidden="true"><?= $symbol ?></div>
    <?php
}

function registerPageFoot(array $extraScripts = []): void {
    ?>
        </div><!-- .register-content -->
    </div><!-- .login-card -->
</div><!-- .login-panel -->

<script src="<?= url('js/register-verify.js') ?>"></script>
<?php foreach ($extraScripts as $script): ?>
<script src="<?= url($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
    <?php
}

function validateRegistrationPassword(string $password, string $confirm): ?array {
    if ($password !== $confirm) {
        return ['field' => 'confirm_password', 'message' => 'Passwords do not match.'];
    }
    $pwErrors = [];
    if (strlen($password) < 12)                                            $pwErrors[] = 'at least 12 characters';
    if (!preg_match('/[A-Z]/', $password))                                 $pwErrors[] = 'an uppercase letter (A-Z)';
    if (!preg_match('/[a-z]/', $password))                                 $pwErrors[] = 'a lowercase letter (a-z)';
    if (!preg_match('/[0-9]/', $password))                                 $pwErrors[] = 'a number (0-9)';
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:,\.?\/]/', $password))  $pwErrors[] = 'a special character';
    if ($pwErrors !== []) {
        return ['field' => 'password', 'message' => 'Password must contain: ' . implode(', ', $pwErrors) . '.'];
    }
    return null;
}
