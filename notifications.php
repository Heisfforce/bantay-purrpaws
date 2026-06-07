<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
requireLogin();

$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex gap-3 mb-6">
    <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost">← Back to Home</a>
</div>

<div class="page-header">
    <h2>Notification Center</h2>
    <p>Your recent activity and system alerts.</p>
</div>

<div class="card">
    <div class="card-body">
        <div class="notif-toolbar">
            <select id="filterType" class="form-control" onchange="loadNotifications()">
                <option value="">All types</option>
                <option value="adoption">🐾 Adoption</option>
                <option value="system">🔔 System</option>
                <option value="otp">🔐 Security</option>
            </select>
            <select id="filterRead" class="form-control" onchange="loadNotifications()">
                <option value="">All</option>
                <option value="unread">Unread only</option>
                <option value="read">Read only</option>
            </select>
            <button class="btn btn-ghost" id="markAllBtn" onclick="markAllRead()">Mark all read</button>
        </div>

        <div id="notifList" class="notif-list">
            <div class="notif-loading">Loading notifications…</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
const API = '<?= url('api/notifications.php') ?>';
let allNotifications = [];

async function loadNotifications() {
    const res  = await fetch(API + '?action=list&limit=50');
    const data = await res.json();
    if (!data.success) return;

    allNotifications = data.notifications;
    renderNotifications();
}

function renderNotifications() {
    const typeFilter = document.getElementById('filterType').value;
    const readFilter = document.getElementById('filterRead').value;

    let items = allNotifications;
    if (typeFilter) items = items.filter(n => n.type === typeFilter);
    if (readFilter === 'unread') items = items.filter(n => !n.is_read);
    if (readFilter === 'read')   items = items.filter(n => n.is_read);

    const list = document.getElementById('notifList');
    if (!items.length) {
        list.innerHTML = '<div class="empty-state"><div class="empty-icon">🔕</div><h3>No notifications</h3><p>You\'re all caught up.</p></div>';
        return;
    }

    const badgeClass = { adoption:'badge-adoption', system:'badge-system', otp:'badge-otp' };

    list.innerHTML = items.map(n => {
        const href = n.link_url ? '<?= url('') ?>' + n.link_url : '#';
        const cls  = n.is_read ? '' : 'unread';
        const badge = `<span class="badge-type ${badgeClass[n.type] || 'badge-system'}">${n.type}</span>`;
        return `
          <a href="${href}" class="notif-card ${cls}"
             onclick="markRead(${n.id}, this)"
             data-id="${n.id}">
            <div class="notif-dot"></div>
            <div class="notif-icon">${n.icon}</div>
            <div class="notif-body">
              <p class="notif-msg">${escHtml(n.message)}</p>
              <div class="notif-meta">
                ${badge}
                <span>⏱ ${n.time_ago}</span>
              </div>
            </div>
          </a>`;
    }).join('');
}

async function markRead(id, el) {
    if (el.classList.contains('unread')) {
        el.classList.remove('unread');
        const dot = el.querySelector('.notif-dot');
        if (dot) dot.style.background = 'transparent';
        await fetch(API + '?action=mark_read', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=mark_read&id=' + id
        });
        const n = allNotifications.find(x => x.id === id);
        if (n) n.is_read = true;
        updateBadge();
    }
}

async function markAllRead() {
    await fetch(API + '?action=mark_all_read', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=mark_all_read'
    });
    allNotifications.forEach(n => n.is_read = true);
    renderNotifications();
    updateBadge(0);
}

function updateBadge(count) {
    const badge = document.querySelector('.notif-badge');
    if (!badge) return;
    if (count === undefined) count = allNotifications.filter(n => !n.is_read).length;
    badge.textContent = count > 0 ? count : '';
    badge.style.display = count > 0 ? '' : 'none';
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadNotifications();
</script>
