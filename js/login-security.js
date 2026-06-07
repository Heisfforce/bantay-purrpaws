/**
 * Enterprise login security wizard.
 */
(function () {
    const cfg = window.BPP_LOGIN || {};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const panels = {
        credentials: document.getElementById('panelCredentials'),
        awaiting: document.getElementById('panelAwaiting'),
        number: document.getElementById('panelNumber'),
        otp: document.getElementById('panelOtp'),
    };

    const steps = ['step1', 'step2', 'step3', 'step4', 'step5'];
    let attemptToken = cfg.initialAttempt || '';
    let pollTimer = null;

    function showError(msg) {
        const el = document.getElementById('loginError');
        const text = document.getElementById('loginErrorMsg');
        if (!el || !text) return;
        text.textContent = msg;
        el.style.display = msg ? 'flex' : 'none';
    }

    function setStep(n) {
        Object.values(panels).forEach(p => p && p.classList.remove('active'));
        const connectors = document.querySelectorAll('.card-mfa-connector');
        steps.forEach((id, i) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.remove('active', 'done');
            if (i + 1 < n) el.classList.add('done');
            if (i + 1 === n) el.classList.add('active');
        });
        connectors.forEach((c, i) => {
            c.classList.toggle('done', i < n - 1);
        });
        const map = { 1: 'credentials', 2: 'awaiting', 3: 'number', 4: 'otp' };
        const panel = panels[map[n]];
        if (panel) panel.classList.add('active');
    }

    async function apiPost(url, data) {
        const body = new URLSearchParams(data);
        body.set('_csrf', csrf);
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
            body,
        });
        return res.json();
    }

    async function startLogin() {
        const email = document.getElementById('email')?.value.trim();
        const password = document.getElementById('password')?.value;
        if (!email || !password) {
            showError('Enter your email and password.');
            return;
        }

        showError('');
        const btn = document.getElementById('btnStartLogin');
        btn.disabled = true;
        btn.textContent = 'Verifying…';

        try {
            const json = await apiPost(cfg.urls.start, {
                email,
                password,
                device_fingerprint: window.BPP.getDeviceFingerprint(),
            });

            if (!json.success) {
                showError(json.message || 'Sign-in failed.');
                return;
            }

            attemptToken = json.attempt_token;
            setStep(2);
            startPolling();
        } catch (e) {
            showError('Network error. Please try again.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Continue';
        }
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(checkStatus, 3000);
        checkStatus();
    }

    function stopPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
    }

    async function checkStatus() {
        if (!attemptToken) return;
        try {
            const url = cfg.urls.status + '?attempt_token=' + encodeURIComponent(attemptToken);
            const res = await fetch(url);
            const json = await res.json();
            if (!json.success) return;

            if (json.status === 'email_denied' || json.step === 'denied' || json.step === 'failed') {
                stopPolling();
                window.location.href = cfg.urls.denied;
                return;
            }

            if (json.step === 'number_match') {
                stopPolling();
                setupNumberMatch(json);
                setStep(3);
            } else if (json.step === 'otp') {
                stopPolling();
                setStep(4);
            } else if (json.step === 'completed') {
                stopPolling();
                window.location.href = cfg.urls.dashboard;
            }
        } catch (e) {
            // silent poll failure
        }
    }

    function setupNumberMatch(json) {
        const target = document.getElementById('displayNumber');
        const grid = document.getElementById('numberGrid');
        if (target) target.textContent = json.display_number;
        if (!grid) return;
        grid.innerHTML = '';
        (json.number_options || []).forEach(num => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'card-btn-primary number-choice';
            btn.textContent = num;
            btn.addEventListener('click', () => submitNumber(num));
            grid.appendChild(btn);
        });
    }

    async function submitNumber(num) {
        showError('');
        const json = await apiPost(cfg.urls.number, {
            attempt_token: attemptToken,
            selected_number: num,
        });
        if (!json.success) {
            showError(json.message);
            if (json.step === 'failed') setStep(1);
            return;
        }
        setStep(4);
    }

    async function submitOtp() {
        const otp = document.getElementById('otpCode')?.value.trim();
        if (!otp || otp.length < 6) {
            showError('Enter the 6-digit OTP.');
            return;
        }
        showError('');
        const trust = document.getElementById('trustDevice')?.checked ? '1' : '';
        const json = await apiPost(cfg.urls.otp, {
            attempt_token: attemptToken,
            otp,
            trust_device: trust,
        });
        if (!json.success) {
            showError(json.message);
            if (json.step === 'failed') setStep(1);
            return;
        }
        window.location.href = json.redirect_url || cfg.urls.dashboard;
    }

    document.getElementById('btnStartLogin')?.addEventListener('click', startLogin);
    document.getElementById('btnSubmitOtp')?.addEventListener('click', submitOtp);
    document.getElementById('btnBackCredentials')?.addEventListener('click', () => {
        stopPolling();
        setStep(1);
    });

    if (cfg.initialStep === 'number' && attemptToken) {
        checkStatus().then(() => setStep(3));
    } else {
        setStep(1);
    }
})();
