<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/registration/verification.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$regToken = trim($_GET['reg'] ?? '');
$row = $regToken ? findRegistrationByToken($regToken) : null;
$status = registrationStatusPayload($row);

if (!$status['found'] || ($status['status'] ?? '') === 'activated') {
    header('Location: ' . url('register.php'));
    exit;
}

require_once __DIR__ . '/includes/register-layout.php';
registerPageHead('Check Your Email', 'We sent a verification link to your inbox', 2);
?>
<div data-api-base="<?= url('api/register/') ?>" data-reg-token="<?= htmlspecialchars($regToken) ?>">

<?php registerStatusIcon('pending', '✉'); ?>

<p class="card-hint">
    Click the verification link we sent to activate your account.
    The link expires in <strong>15 minutes</strong>.
</p>

<?php if (!empty($status['email_masked'])): ?>
    <p class="card-hint">
        Sent to <span class="card-email-chip"><?= htmlspecialchars($status['email_masked']) ?></span>
    </p>
<?php endif; ?>

<div class="card-action-stack">
    <a href="<?= url('register-resend.php?reg=' . urlencode($regToken)) ?>" class="card-btn-primary">
        Resend Verification Link
    </a>
    <a href="<?= url('register.php') ?>" class="card-btn-ghost">Start Over</a>
</div>

<p class="card-footer">
    Already verified? <a href="<?= url('register-verify-code.php?reg=' . urlencode($regToken)) ?>">Enter code</a>
</p>
</div>
<?php registerPageFoot(); ?>
