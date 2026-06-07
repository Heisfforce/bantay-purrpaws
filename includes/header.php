<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn() && ($_SESSION['role'] ?? '') === 'staff') {
    $staffId = (int) ($_SESSION['user_id'] ?? 0);
    if ($staffId) {
        $staffRow = db_select('users', "id=eq.{$staffId}&select=staff_permissions,permissions_changed_at&limit=1", true);
        $changedAt    = $staffRow['permissions_changed_at'] ?? null;
        $sessionStart = $_SESSION['session_started_at'] ?? 0;
        if ($changedAt && strtotime($changedAt) > $sessionStart) {
            $raw = $staffRow['staff_permissions'] ?? null;
            if ($raw !== null && is_string($raw)) {
                $decoded = json_decode($raw, true);
                $_SESSION['staff_permissions'] = is_array($decoded) ? $decoded : null;
            } else {
                $_SESSION['staff_permissions'] = is_array($raw) ? $raw : null;
            }
            $_SESSION['session_started_at'] = time();
            if (empty($_SESSION['perms_refresh_notified_at']) ||
                $_SESSION['perms_refresh_notified_at'] < strtotime($changedAt)) {
                $_SESSION['perms_refresh_notified_at'] = time();
                flash('info', '🛡 Your account permissions have been updated by an administrator.');
            }
        }
    }
}

$navUser = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isAdminArea = str_starts_with($_SERVER['PHP_SELF'], '/admin/') || str_contains($_SERVER['PHP_SELF'], '/admin/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'BantayPurrPaws' ?> — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <?php if (!empty($useBootstrap)): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <?php foreach ($extraCss ?? [] as $cssFile): ?>
    <link rel="stylesheet" href="<?= url($cssFile) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app-layout app-layout-topnav">
    <header class="site-header">
        <div class="site-header-inner">
            <a href="<?= dashboardHomeUrl() ?>" class="site-brand" title="BantayPurrPaws Home">
                <?php if (is_file(__DIR__ . '/../assets/logo.png')): ?>
                <img src="<?= url('assets/logo.png') ?>" alt="" class="site-brand-img">
                <?php else: ?>
                <span class="site-brand-icon" aria-hidden="true">🐾</span>
                <?php endif; ?>
                <span class="site-brand-text">BantayPurrPaws</span>
            </a>

            <div class="site-header-actions">
                <?php require __DIR__ . '/notification-bell.php'; ?>
                <script>
                function headerMarkRead(id, el) {
                    if (!el.classList.contains('unread')) return;
                    el.classList.remove('unread');
                    fetch('<?= url('api/notifications.php') ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=mark_read&id=' + id
                    });
                    const badge = document.querySelector('.notif-badge');
                    if (badge) {
                        const n = parseInt(badge.textContent, 10) - 1;
                        if (n > 0) { badge.textContent = n > 99 ? '99+' : n; }
                        else { badge.textContent = ''; badge.style.display = 'none'; }
                    }
                }
                function headerMarkAllRead() {
                    fetch('<?= url('api/notifications.php') ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=mark_all_read'
                    });
                    document.querySelectorAll('.notification-item.unread').forEach(function (el) {
                        el.classList.remove('unread');
                    });
                    const badge = document.querySelector('.notif-badge');
                    if (badge) { badge.textContent = ''; badge.style.display = 'none'; }
                }
                </script>

                <div class="profile-menu" id="profileMenu">
                    <button type="button" class="profile-menu-trigger" id="profileMenuTrigger" aria-expanded="false" aria-haspopup="true">
                        <?php if (!empty($navUser['avatar'])): ?>
                        <img src="<?= sanitize($navUser['avatar']) ?>" alt="" class="profile-avatar">
                        <?php else: ?>
                        <span class="profile-avatar profile-avatar-initials"><?= strtoupper(substr($navUser['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="profile-menu-label hide-mobile">My Profile</span>
                        <svg class="profile-chevron" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M4.5 6l3.5 3.5L11.5 6z"/></svg>
                    </button>
                    <div class="profile-menu-dropdown" id="profileMenuDropdown">
                        <div class="profile-menu-header">
                            <div class="profile-menu-name"><?= sanitize($navUser['name']) ?></div>
                            <div class="profile-menu-email"><?= sanitize($navUser['email'] ?? '') ?></div>
                            <span class="role-badge <?= roleBadgeClass() ?>"><?= roleLabel() ?></span>
                        </div>
                        <div class="profile-menu-links">
                            <a href="<?= url('profile.php') ?>" class="profile-menu-link">
                                <span aria-hidden="true">👤</span> My Profile
                            </a>
                            <a href="<?= url('security-settings.php') ?>" class="profile-menu-link">
                                <span aria-hidden="true">🔒</span> Security
                            </a>
                            <?php if (!isAdmin()): ?>
                            <a href="<?= url('notifications.php') ?>" class="profile-menu-link">
                                <span aria-hidden="true">🔔</span> Notifications
                            </a>
                            <?php endif; ?>
                            <a href="<?= url('logout.php') ?>" class="profile-menu-link profile-menu-link-danger">
                                <span aria-hidden="true">⎋</span> Log Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="page-body">
        <?php
        if ($success = flash('success')) {
            echo "<div class=\"alert alert-success\">✓ " . sanitize($success) . "</div>";
        }
        if ($error = flash('error')) {
            echo "<div class=\"alert alert-error\">✕ " . sanitize($error) . "</div>";
        }
        if ($info = flash('info')) {
            echo "<div class=\"alert alert-info\">" . sanitize($info) . "</div>";
        }
        if ($warning = flash('warning')) {
            echo "<div class=\"alert alert-warning\">⚠ " . sanitize($warning) . "</div>";
        }
        ?>
