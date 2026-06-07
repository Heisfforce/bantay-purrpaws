<?php
require_once __DIR__ . '/includes/auth.php';
startSession();

require_once __DIR__ . '/includes/register-layout.php';
registerPageHead('Account Activated', 'Welcome to BantayPurrPaws');
?>

<?php registerStatusIcon('success', '✓'); ?>

<div class="card-alert-success block">Account successfully activated.</div>

<p class="card-hint">
    Your email has been verified and your account is ready. You can now sign in and start helping stray animals.
</p>

<div class="card-action-stack">
    <a href="<?= url('login.php') ?>" class="card-btn-primary">Sign In</a>
    <a href="<?= url('index.php') ?>" class="card-btn-ghost">Go to Homepage</a>
</div>

<?php registerPageFoot(); ?>
