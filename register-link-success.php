<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/registration/verification.php';
startSession();

$regToken = trim($_GET['reg'] ?? '');
$row = $regToken ? findRegistrationByToken($regToken) : null;

if (!$row || !(int) ($row['email_link_verified'] ?? 0)) {
    header('Location: ' . url('register-pending.php?reg=' . urlencode($regToken)));
    exit;
}

require_once __DIR__ . '/includes/register-layout.php';
$flash = flash('success');
registerPageHead('Email Verified', 'One more step to activate your account', 3);
?>

<?php registerStatusIcon('success', '✓'); ?>

<?php if ($flash): ?>
    <div class="card-alert-success block"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<p class="card-hint">
    Email verification successful. We sent a <strong>6-digit code</strong> to your email.
    Enter it on the next page to activate your account. The code expires in <strong>5 minutes</strong>.
</p>

<div class="card-action-stack">
    <a href="<?= url('register-verify-code.php?reg=' . urlencode($regToken)) ?>" class="card-btn-primary">
        Enter Verification Code
    </a>
</div>

<?php registerPageFoot(); ?>
