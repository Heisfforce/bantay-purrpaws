(function () {
    'use strict';

    window.RegVerify = {
        apiBase: (document.querySelector('[data-api-base]') || {}).dataset?.apiBase || '',

        regToken() {
            const el = document.querySelector('[data-reg-token]');
            return el ? el.dataset.regToken : '';
        },

        csrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },

        async post(endpoint, data) {
            const fd = new FormData();
            Object.entries(data).forEach(([k, v]) => fd.append(k, v));
            fd.append('_csrf', this.csrfToken());
            const res = await fetch(this.apiBase + endpoint, { method: 'POST', body: fd });
            return res.json();
        },

        showErr(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = msg;
            el.classList.toggle('visible', !!msg);
        },

        bindOtpInputs(containerId, onComplete) {
            const container = document.getElementById(containerId);
            if (!container) return;
            const inputs = container.querySelectorAll('input');
            inputs.forEach((el, i, all) => {
                el.addEventListener('input', () => {
                    el.value = el.value.replace(/\D/, '');
                    if (el.value && i < all.length - 1) all[i + 1].focus();
                    const code = Array.from(all).map(d => d.value).join('');
                    if (code.length === 6 && typeof onComplete === 'function') onComplete(code);
                });
                el.addEventListener('keydown', e => {
                    if (e.key === 'Backspace' && !el.value && i > 0) all[i - 1].focus();
                });
                el.addEventListener('paste', e => {
                    e.preventDefault();
                    const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                    pasted.split('').forEach((ch, j) => { if (all[j]) all[j].value = ch; });
                    if (pasted.length === 6 && typeof onComplete === 'function') onComplete(pasted);
                });
            });
        },

        startCooldown(btnId, timerId, seconds, onDone) {
            const btn = document.getElementById(btnId);
            const timerEl = document.getElementById(timerId);
            if (!btn) return;
            let secs = seconds;
            btn.disabled = true;
            const tick = () => {
                if (timerEl) timerEl.textContent = '(' + secs + 's)';
                secs--;
                if (secs < 0) {
                    btn.disabled = false;
                    if (timerEl) timerEl.textContent = '';
                    if (typeof onDone === 'function') onDone();
                    return;
                }
                setTimeout(tick, 1000);
            };
            tick();
        }
    };
})();
