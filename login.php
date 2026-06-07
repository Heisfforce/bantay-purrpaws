<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/google-oauth.php';
require_once __DIR__ . '/includes/security/csrf.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . dashboardHomeUrl());
    exit;
}

$error = flash('error') ?? '';
if (!$error && !empty($_SESSION['force_relogin_msg'])) {
    $error = $_SESSION['force_relogin_msg'];
    unset($_SESSION['force_relogin_msg']);
}

if (isset($_GET['google'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    header('Location: ' . googleAuthUrl($state));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Legacy direct POST disabled — login uses api/auth/* secure flow.
    $error = 'Please use the secure sign-in form.';
}

$initialAttempt = sanitize($_GET['attempt'] ?? '');
$initialStep    = sanitize($_GET['step'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/auth-page.css') ?>">
    <link rel="stylesheet" href="<?= url('css/security-auth.css') ?>">
    <?= csrfMetaTag() ?>
    <style>
        /* Login-specific overrides (shell in auth-page.css) */
        body { font-family: 'DM Sans', system-ui, sans-serif; }

        .login-card .security-panel {
            display: none;
            flex-direction: column;
            gap: 14px;
        }

        .login-card .security-panel.active { display: flex; }

        .otp-input {
            letter-spacing: 0.3em;
            font-size: 1.3rem !important;
            text-align: center;
            font-weight: 600;
        }

        .card-pw-modal {
            display: flex;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(10px);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        .card-pw-modal.visible {
            opacity: 1;
            pointer-events: all;
        }

        .card-pw-modal-content {
            width: min(100%, 420px);
            padding: 36px;
            border-radius: 20px;
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
            box-shadow: 0 32px 80px rgba(0,0,0,0.3), 0 8px 24px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            gap: 16px;
            transform: scale(0.94) translateY(12px);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .card-pw-modal.visible .card-pw-modal-content {
            transform: scale(1) translateY(0);
        }

        .card-pw-modal-content h3 {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.4rem;
            color: #1c1917;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .card-mfa-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 4px 12px;
            border-radius: 999px;
            width: fit-content;
        }

        .card-pw-modal-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 4px;
        }

        .card-pw-modal-btns {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .card-pw-modal-content .card-btn-ghost {
            width: auto;
            padding: 9px 18px;
            font-size: 0.845rem;
        }

        .card-pw-modal-content .card-btn-primary {
            width: auto;
            padding: 9px 22px;
            font-size: 0.875rem;
        }

        .auth-modal-link {
            font-size: 0.8rem;
            color: #78716c;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .auth-modal-link:hover { color: #8B3A3A; }

        #loginError .login-error-text { flex: 1; }
    </style>
</head>
<body class="auth-page-body">

<!-- Full-screen background -->
<div class="login-bg">
    <img src="<?= url('assets/dog.jpg') ?>" alt="" class="login-bg-img">
    <div class="login-bg-overlay"></div>
</div>

<!-- Left hero text (desktop only) -->
<div class="login-hero" aria-hidden="true">
    <span class="login-hero-paws">❤️</span>
    <h1>Give a pet a<br>loving home.</h1>
    <p>Report strays, connect with rescuers, and find your next furry family member — all in one place.</p>
</div>

<!-- Right login panel -->
<div class="login-panel">
    <div class="login-card">

        <!-- Brand -->
        <a href="<?= url('login.php') ?>" class="card-brand">
            <?php if (is_file(__DIR__ . '/assets/logo.png')): ?>
                <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="card-brand-img">
            <?php else: ?>
                <span class="card-brand-mark">🐾</span>
            <?php endif; ?>
            <span class="card-brand-name">BantayPurrPaws</span>
        </a>

        <!-- Welcome -->
        <div class="card-welcome">
            <h2>Welcome back</h2>
            <p>Sign in to report strays, adopt pets, and stay updated.</p>
        </div>

        <!-- Google Sign In -->
        <a href="?google=1" class="card-btn-google">
            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/>
                <path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z"/>
                <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.58z"/>
            </svg>
            Sign in with Google
        </a>

        <div class="card-divider"><span>or with email</span></div>

        <!-- Security Steps -->
        <div class="card-mfa-steps" id="mfaSteps">
            <div class="card-mfa-step active" id="step1"><div class="card-mfa-step-dot">1</div><span>Sign in</span></div>
            <div class="card-mfa-connector"></div>
            <div class="card-mfa-step" id="step2"><div class="card-mfa-step-dot">2</div><span>Email</span></div>
            <div class="card-mfa-connector"></div>
            <div class="card-mfa-step" id="step3"><div class="card-mfa-step-dot">3</div><span>Number</span></div>
            <div class="card-mfa-connector"></div>
            <div class="card-mfa-step" id="step4"><div class="card-mfa-step-dot">4</div><span>OTP</span></div>
            <div class="card-mfa-connector"></div>
            <div class="card-mfa-step" id="step5"><div class="card-mfa-step-dot">5</div><span>Done</span></div>
        </div>

        <div id="loginError" class="card-alert-error" style="display:<?= $error ? 'flex' : 'none' ?>;">
            <span aria-hidden="true">✕</span>
            <span class="login-error-text" id="loginErrorMsg"><?= sanitize($error) ?></span>
        </div>

        <!-- Step 1: Credentials -->
        <div class="security-panel active" id="panelCredentials">
            <div class="form-group">
                <label class="form-label" for="email">Email <span class="req">*</span></label>
                <input type="email" id="email" class="form-control" placeholder="you@example.com" autocomplete="email">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password <span class="req">*</span></label>
                <input type="password" id="password" class="form-control" placeholder="Your password" autocomplete="current-password">
            </div>
            <button type="button" id="btnStartLogin" class="card-btn-primary">Continue</button>
            <p class="card-hint">We will email you to confirm this sign-in before sending an OTP.</p>
        </div>

        <!-- Step 2: Awaiting email confirmation -->
        <div class="security-panel" id="panelAwaiting">
            <div class="security-wait-icon">📧</div>
            <p class="card-hint"><strong>Check your email</strong><br>We sent a security alert with <em>Yes, It's Me</em> and <em>No, It's Not Me</em> buttons. Confirm the attempt to continue.</p>
            <button type="button" id="btnBackCredentials" class="card-btn-ghost">← Start over</button>
        </div>

        <!-- Step 3: Number matching -->
        <div class="security-panel" id="panelNumber">
            <p class="card-hint">Select the number shown below from the options. The same numbers were sent to your email.</p>
            <div class="display-number-box">
                <div class="label">Your number</div>
                <div class="value" id="displayNumber">—</div>
            </div>
            <div class="number-grid" id="numberGrid"></div>
        </div>

        <!-- Step 4: OTP -->
        <div class="security-panel" id="panelOtp">
            <p class="card-hint">Enter the 6-digit code sent to your email. Expires in <strong>5 minutes</strong>.</p>
            <div class="form-group">
                <label class="form-label" for="otpCode">One-Time Password</label>
                <input type="text" id="otpCode" class="form-control otp-input" maxlength="6" autocomplete="one-time-code" placeholder="000000">
            </div>
            <label class="trust-device-row">
                <input type="checkbox" id="trustDevice"> Trust this device for 90 days
            </label>
            <button type="button" id="btnSubmitOtp" class="card-btn-primary">Verify &amp; Sign In</button>
        </div>

        <!-- Footer -->
        <p class="card-footer">
            Don't have an account? <a href="<?= url('register.php') ?>">Sign Up</a>
            &nbsp;·&nbsp; <a href="<?= url('forgot-password.php') ?>">Forgot password?</a>
        </p>
    </div>
</div>

<script>
window.BPP_LOGIN = {
    initialAttempt: <?= json_encode($initialAttempt) ?>,
    initialStep: <?= json_encode($initialStep) ?>,
    urls: {
        start: <?= json_encode(url('api/auth/login-start.php')) ?>,
        status: <?= json_encode(url('api/auth/login-status.php')) ?>,
        number: <?= json_encode(url('api/auth/number-match.php')) ?>,
        otp: <?= json_encode(url('api/auth/verify-otp.php')) ?>,
        denied: <?= json_encode(url('security/login-denied.php')) ?>,
        dashboard: <?= json_encode(url('dashboard.php')) ?>,
    }
};
</script>
<script src="<?= url('js/device-fingerprint.js') ?>"></script>
<script src="<?= url('js/login-security.js') ?>"></script>
<script src="<?= url('js/pw-toggle.js') ?>"></script>
</body>
</html>