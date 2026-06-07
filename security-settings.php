<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security/login-flow.php';
require_once __DIR__ . '/includes/security/sessions.php';
requireLogin();

$userId   = (int) ($_SESSION['user_id'] ?? 0);
$sessions = getActiveSessionsForUser($userId);
$pageTitle = 'Security Settings';
$extraCss  = ['css/security-auth.css'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="security-page">
    <div class="page-header page-header-row">
        <div class="page-header-text">
            <p class="page-eyebrow">Account</p>
            <h2>Security Settings</h2>
            <p>Manage account protection, sessions, and trusted devices.</p>
        </div>
        <a href="<?= url('profile.php') ?>" class="btn btn-ghost btn-sm">← Profile</a>
    </div>

    <div class="feature-grid" style="margin-bottom: 28px;">
        <a href="<?= url('login-history.php') ?>" class="feature-card">
            <span class="feature-card-icon">📜</span>
            <span class="feature-card-title">Login History</span>
            <span class="feature-card-desc">View recent sign-in attempts and risk levels.</span>
        </a>
        <a href="<?= url('trusted-devices.php') ?>" class="feature-card">
            <span class="feature-card-icon">💻</span>
            <span class="feature-card-title">Trusted Devices</span>
            <span class="feature-card-desc">Manage devices you marked as trusted.</span>
        </a>
    </div>

    <div class="card security-card">
        <div class="card-header">
            <h3 class="card-title">Active Sessions (<?= count($sessions) ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($sessions)): ?>
                <p class="security-empty">No tracked sessions yet. Sessions appear after your next secure sign-in.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table security-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>IP address</th>
                            <th>Last active</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><?= sanitize(trim(($s['browser'] ?? 'Unknown') . ' on ' . ($s['os'] ?? 'Unknown'))) ?></td>
                            <td><?= sanitize($s['ip_address'] ?? '—') ?></td>
                            <td><?= sanitize(date('M j, Y g:i A', strtotime($s['last_activity_at'] ?? $s['created_at'] ?? 'now'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
