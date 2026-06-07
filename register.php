<?php
require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

require_once __DIR__ . '/includes/register-layout.php';
registerPageHead('Create Account', 'Join BantayPurrPaws and help save animals', 1);
?>
<div data-api-base="<?= url('api/register/') ?>">

<form id="registerForm" novalidate>
    <div class="form-group">
        <label class="form-label" for="full_name">Full Name <span class="req">*</span></label>
        <input type="text" id="full_name" name="full_name" class="form-control"
               placeholder="Juan dela Cruz" autocomplete="name" required>
        <div class="field-error" id="err_full_name"></div>
    </div>

    <div class="form-group">
        <label class="form-label" for="email">Email Address <span class="req">*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="you@example.com" autocomplete="email" required>
        <div class="field-error" id="err_email"></div>
    </div>

    <div class="form-group" id="pw-field-group">
        <label class="form-label" for="password">Password <span class="req">*</span></label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Min. 12 characters" autocomplete="new-password" required>
        <div class="field-error" id="err_password"></div>
    </div>

    <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm Password <span class="req">*</span></label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
               placeholder="Repeat password" autocomplete="new-password" required>
        <div class="field-error" id="err_confirm_password"></div>
    </div>

    <div class="agree-row">
        <input type="checkbox" id="agree_terms" name="agree_terms" value="1">
        <label for="agree_terms">
            I have read and agree to the
            <a href="<?= url('terms.php') ?>" target="_blank">Terms &amp; Conditions</a>
            of BantayPurrPaws.
        </label>
    </div>
    <div class="field-error" id="err_agree"></div>
    <div class="field-error" id="err_form" style="text-align:center;margin-top:8px;"></div>

    <button type="submit" id="btnRegister" class="card-btn-primary" style="margin-top:8px;">
        Create Account
    </button>

    <p class="card-footer">
        Already have an account? <a href="<?= url('login.php') ?>">Sign in</a>
    </p>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initPasswordStrength('password', 'confirm_password', 'pw-field-group');

    const form = document.getElementById('registerForm');
    const btn  = document.getElementById('btnRegister');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        ['err_full_name','err_email','err_password','err_confirm_password','err_agree','err_form']
            .forEach(id => RegVerify.showErr(id, ''));

        btn.disabled = true;
        btn.textContent = 'Validating email…';

        try {
            const data = await RegVerify.post('start.php', {
                full_name: document.getElementById('full_name').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                confirm_password: document.getElementById('confirm_password').value,
                agree_terms: document.getElementById('agree_terms').checked ? '1' : ''
            });

            if (data.success) {
                window.location.href = data.redirect;
                return;
            }

            const field = data.field || 'form';
            const errId = field === 'form' ? 'err_form' : 'err_' + field;
            RegVerify.showErr(errId, data.message || 'Registration failed.');
        } catch (err) {
            RegVerify.showErr('err_form', 'Network error. Please try again.');
        }

        btn.disabled = false;
        btn.textContent = 'Create Account';
    });
});
</script>
</div>
<?php registerPageFoot(['js/pw-toggle.js', 'js/password-strength.js']); ?>
