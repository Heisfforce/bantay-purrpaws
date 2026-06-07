<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/registration/verification.php';
startSession();

$regToken = trim($_GET['reg'] ?? '');
$row = $regToken ? findRegistrationByToken($regToken) : null;
$status = registrationStatusPayload($row);

if (!$status['found']) {
    header('Location: ' . url('register.php'));
    exit;
}

if (($status['status'] ?? '') === 'activated') {
    header('Location: ' . url('register-success.php'));
    exit;
}

if (($status['status'] ?? '') === 'locked') {
    header('Location: ' . url('register-expired.php?reg=' . urlencode($regToken) . '&reason=locked'));
    exit;
}

if (!($status['email_link_verified'] ?? false)) {
    header('Location: ' . url('register-pending.php?reg=' . urlencode($regToken)));
    exit;
}

require_once __DIR__ . '/includes/register-layout.php';
registerPageHead('Enter Verification Code', 'Check your email for the 6-digit code', 3);
?>
<div data-api-base="<?= url('api/register/') ?>" data-reg-token="<?= htmlspecialchars($regToken) ?>">

<p class="card-hint">
    Enter the verification code sent to
    <span class="card-email-chip"><?= htmlspecialchars($status['email_masked'] ?? 'your email') ?></span>
</p>

<div id="codeAlert" class="card-alert-info block" style="display:none;"></div>

<form id="codeForm">
    <div class="otp-inputs" id="otpBoxes">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
        <?php endfor; ?>
    </div>
    <div class="field-error" id="err_code" style="text-align:center;"></div>

    <div class="resend-row">
        Didn't receive the code?
        <button type="button" id="btnResendCode" disabled>Resend Code</button>
        <span id="resendTimer"></span>
    </div>

    <button type="submit" id="btnVerify" class="card-btn-primary">Activate Account</button>
</form>

<div class="card-action-stack" style="margin-top:12px;">
    <a href="<?= url('register-resend.php?reg=' . urlencode($regToken) . '&type=code') ?>" class="card-btn-ghost">
        Resend Options
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const token = RegVerify.regToken();
    const alertEl = document.getElementById('codeAlert');
    const btnVerify = document.getElementById('btnVerify');

    function showAlert(msg, type) {
        alertEl.textContent = msg;
        alertEl.className = 'card-alert-' + (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info')) + ' block';
        alertEl.style.display = 'block';
    }

    async function submitCode(code) {
        RegVerify.showErr('err_code', '');
        btnVerify.disabled = true;
        btnVerify.textContent = 'Verifying…';

        try {
            const data = await RegVerify.post('verify-code.php', {
                registration_token: token,
                verification_code: code
            });

            if (data.success) {
                showAlert(data.message || 'Account successfully activated.', 'success');
                setTimeout(() => { window.location.href = data.redirect || '<?= url('register-success.php') ?>'; }, 800);
                return;
            }

            if (data.locked || data.expired) {
                window.location.href = '<?= url('register-expired.php') ?>?reg=' + encodeURIComponent(token)
                    + '&reason=' + (data.locked ? 'locked' : 'code');
                return;
            }

            RegVerify.showErr('err_code', data.message || 'Invalid verification code.');
            document.getElementById('otpBoxes').classList.add('otp-shake');
            setTimeout(() => document.getElementById('otpBoxes').classList.remove('otp-shake'), 400);
            document.querySelectorAll('#otpBoxes input').forEach(i => { i.value = ''; });
            document.querySelector('#otpBoxes input').focus();
        } catch (e) {
            RegVerify.showErr('err_code', 'Network error. Please try again.');
        }

        btnVerify.disabled = false;
        btnVerify.textContent = 'Activate Account';
    }

    RegVerify.bindOtpInputs('otpBoxes', submitCode);
    document.querySelector('#otpBoxes input').focus();

    document.getElementById('codeForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const code = Array.from(document.querySelectorAll('#otpBoxes input')).map(i => i.value).join('');
        if (code.length === 6) submitCode(code);
    });

    document.getElementById('btnResendCode').addEventListener('click', async () => {
        const btn = document.getElementById('btnResendCode');
        btn.disabled = true;
        try {
            const data = await RegVerify.post('resend-code.php', { registration_token: token });
            showAlert(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                RegVerify.startCooldown('btnResendCode', 'resendTimer', data.cooldown || 60);
            } else if (data.cooldown) {
                RegVerify.startCooldown('btnResendCode', 'resendTimer', data.cooldown);
            } else {
                btn.disabled = false;
            }
        } catch (e) {
            showAlert('Network error.', 'error');
            btn.disabled = false;
        }
    });

    RegVerify.startCooldown('btnResendCode', 'resendTimer', 60);
});
</script>
</div>
<?php registerPageFoot(); ?>
