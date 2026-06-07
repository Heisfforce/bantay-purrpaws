<?php
require_once __DIR__ . '/includes/auth.php';
startSession();

$regToken = trim($_GET['reg'] ?? '');
$reason   = trim($_GET['reason'] ?? 'link');

$messages = [
    'link'    => 'Verification link expired.',
    'code'    => 'Verification code expired.',
    'locked'  => 'Maximum verification attempts reached.',
    'invalid' => 'This verification link is invalid or has already been used.',
];

$message = $messages[$reason] ?? $messages['link'];

require_once __DIR__ . '/includes/register-layout.php';
registerPageHead('Verification Expired', 'Your verification session has ended');
?>

<?php registerStatusIcon('error', '!'); ?>

<div class="card-alert-error block"><?= htmlspecialchars($message) ?></div>

<p class="card-hint">
    For your security, verification links and codes expire quickly.
    You can request a new verification email to continue registration.
</p>

<div class="card-action-stack">
    <?php if ($regToken !== ''): ?>
        <a href="<?= url('register-resend.php?reg=' . urlencode($regToken)) ?>" class="card-btn-primary">
            Resend Verification
        </a>
    <?php else: ?>
        <a href="<?= url('register.php') ?>" class="card-btn-primary">Register Again</a>
    <?php endif; ?>
    <a href="<?= url('login.php') ?>" class="card-btn-ghost">Sign In</a>
</div>

<?php registerPageFoot(); ?>
