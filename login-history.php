<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security/login-flow.php';
requireLogin();

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$history = getLoginHistory($userId, 50);
$pageTitle = 'Login History';
$extraCss  = ['css/security-auth.css'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="security-page">
    <div class="page-header page-header-row">
        <div class="page-header-text">
            <p class="page-eyebrow">Account</p>
            <h2>Login History</h2>
            <p>Recent sign-in attempts on your account.</p>
        </div>
        <a href="<?= url('security-settings.php') ?>" class="btn btn-ghost btn-sm">← Security</a>
    </div>

    <div class="card security-card">
        <div class="card-header">
            <h3 class="card-title">Recent attempts</h3>
        </div>
        <div class="card-body">
            <?php if (empty($history)): ?>
                <p class="security-empty">No login attempts recorded yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table security-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Device</th>
                            <th>Location</th>
                            <th>Risk</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $row):
                        $st  = $row['status'] ?? '';
                        $cls = in_array($st, ['completed'], true) ? 'success' : (in_array($st, ['email_denied','denied','failed'], true) ? 'danger' : 'pending');
                        $risk = $row['risk_level'] ?? 'low';
                    ?>
                        <tr>
                            <td><?= sanitize(date('M j, Y g:i A', strtotime($row['created_at']))) ?></td>
                            <td><?= sanitize(trim(($row['browser'] ?? '') . ' / ' . ($row['os'] ?? ''))) ?></td>
                            <td><?= sanitize($row['location_label'] ?? '—') ?></td>
                            <td><span class="risk-badge risk-<?= sanitize($risk) ?>"><?= sanitize(ucfirst($risk)) ?></span></td>
                            <td><span class="status-pill <?= $cls ?>"><?= sanitize(str_replace('_', ' ', $st)) ?></span></td>
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
