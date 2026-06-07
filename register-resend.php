<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/registration/verification.php';
startSession();

$regToken = trim($_GET['reg'] ?? '');
$type     = trim($_GET['type'] ?? 'link');
$row = $regToken ? findRegistrationByToken($regToken) : null;
$status = registrationStatusPayload($row);

if (!$status['found'] || ($status['status'] ?? '') === 'activated') {
    header('Location: ' . url('register.php'));
    exit;
}

$linkVerified = (bool) ($status['email_link_verified'] ?? false);
if ($type === 'code' && !$linkVerified) {
    $type = 'link';
}

$activeStep = ($type === 'code') ? 3 : 2;

require_once __DIR__ . '/includes/register-layout.php';
registerPageHead('Resend Verification', 'Request a new verification email or code', $activeStep);
?>
<div data-api-base="<?= url('api/register/') ?>" data-reg-token="<?= htmlspecialchars($regToken) ?>">

<div id="resendAlert" class="card-alert-info block" style="display:none;"></div>

<?php if ($type === 'code' && $linkVerified): ?>
    <?php registerStatusIcon('pending', '#'); ?>
    <p class="card-hint">
        Request a new <strong>6-digit verification code</strong>. Previous codes will be invalidated.
        Resend cooldown: <strong>60 seconds</strong>.
    </p>
    <div class="card-action-stack">
        <button type="button" id="btnResendCode" class="card-btn-primary" disabled>Resend Code</button>
        <span id="resendTimer" class="resend-timer"></span>
        <a href="<?= url('register-verify-code.php?reg=' . urlencode($regToken)) ?>" class="card-btn-ghost">Back to Code Entry</a>
    </div>
<?php else: ?>
    <?php registerStatusIcon('pending', '✉'); ?>
    <p class="card-hint">
        Request a new <strong>verification link</strong>. Previous links and codes will be invalidated.
        Resend cooldown: <strong>60 seconds</strong>.
    </p>
    <div class="card-action-stack">
        <button type="button" id="btnResendLink" class="card-btn-primary" disabled>Resend Link</button>
        <span id="resendTimer" class="resend-timer"></span>
        <a href="<?= url('register-pending.php?reg=' . urlencode($regToken)) ?>" class="card-btn-ghost">Back</a>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const token = RegVerify.regToken();
    const alertEl = document.getElementById('resendAlert');

    function showAlert(msg, type) {
        alertEl.textContent = msg;
        alertEl.className = 'card-alert-' + (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info')) + ' block';
        alertEl.style.display = 'block';
    }

    async function resend(endpoint) {
        try {
            const data = await RegVerify.post(endpoint, { registration_token: token });
            showAlert(data.message, data.success ? 'success' : 'error');
            if (data.success && endpoint === 'resend-link.php') {
                setTimeout(() => {
                    window.location.href = '<?= url('register-pending.php') ?>?reg=' + encodeURIComponent(token);
                }, 1200);
            }
            return data;
        } catch (e) {
            showAlert('Network error.', 'error');
            return { success: false };
        }
    }

    const linkBtn = document.getElementById('btnResendLink');
    const codeBtn = document.getElementById('btnResendCode');

    if (linkBtn) {
        linkBtn.addEventListener('click', async () => {
            linkBtn.disabled = true;
            const data = await resend('resend-link.php');
            if (!data.success && data.cooldown) {
                RegVerify.startCooldown('btnResendLink', 'resendTimer', data.cooldown);
            } else if (!data.success) {
                linkBtn.disabled = false;
            }
        });
        RegVerify.startCooldown('btnResendLink', 'resendTimer', 60);
    }

    if (codeBtn) {
        codeBtn.addEventListener('click', async () => {
            codeBtn.disabled = true;
            const data = await resend('resend-code.php');
            if (data.success) {
                setTimeout(() => {
                    window.location.href = '<?= url('register-verify-code.php') ?>?reg=' + encodeURIComponent(token);
                }, 1200);
            } else if (data.cooldown) {
                RegVerify.startCooldown('btnResendCode', 'resendTimer', data.cooldown);
            } else {
                codeBtn.disabled = false;
            }
        });
        RegVerify.startCooldown('btnResendCode', 'resendTimer', 60);
    }
});
</script>
</div>
<?php registerPageFoot(); ?>
