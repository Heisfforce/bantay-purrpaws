document.addEventListener('DOMContentLoaded', function () {
    // Profile dropdown menu
    const profileMenu = document.getElementById('profileMenu');
    const profileTrigger = document.getElementById('profileMenuTrigger');

    if (profileMenu && profileTrigger) {
        profileTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = profileMenu.classList.toggle('open');
            profileTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function () {
            profileMenu.classList.remove('open');
            profileTrigger.setAttribute('aria-expanded', 'false');
        });

        profileMenu.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    // Responsive tables: copy header labels onto cells for stacked layout
    document.querySelectorAll('.table-responsive-stack').forEach(function (wrapper) {
        if (wrapper.querySelector('.table-scroll-hint')) {
            return;
        }
        const hint = document.createElement('p');
        hint.className = 'table-scroll-hint';
        hint.textContent = 'Swipe sideways to see more columns';
        wrapper.insertBefore(hint, wrapper.firstChild);

        const table = wrapper.querySelector('table');
        if (!table) {
            return;
        }

        const headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
            return th.textContent.trim();
        });

        table.querySelectorAll('tbody tr').forEach(function (row) {
            row.querySelectorAll('td').forEach(function (cell, index) {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index]);
                }
            });
        });
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease, margin 0.4s ease, padding 0.4s ease, max-height 0.4s ease';
            alert.style.opacity = '0';
            alert.style.maxHeight = '0';
            alert.style.overflow = 'hidden';
            alert.style.padding = '0';
            alert.style.margin = '0';
            setTimeout(function () { alert.remove(); }, 450);
        }, 4000);
    });

    // Confirm before role changes
    document.querySelectorAll('form[action=""]').forEach(function (form) {
        if (form.querySelector('[name="change_role"]')) {
            form.addEventListener('submit', function (e) {
                const role = form.querySelector('select[name="role"]').value;
                if (!confirm('Change this user\'s role to "' + role + '"?')) {
                    e.preventDefault();
                }
            });
        }
    });

    // Table row click → navigate to view if data-href
    document.querySelectorAll('tr[data-href]').forEach(function (row) {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function () {
            window.location.href = row.dataset.href;
        });
    });

    // Notification dropdown
    const notifBell = document.getElementById('notificationBell');
    const notifDrop = document.getElementById('notificationDropdown');
    if (notifBell && notifDrop) {
        notifBell.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = notifDrop.classList.toggle('open');
            notifBell.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function () {
            notifDrop.classList.remove('open');
            notifBell.setAttribute('aria-expanded', 'false');
        });
        notifDrop.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }
});
