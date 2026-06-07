<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security/login-flow.php';
require_once __DIR__ . '/includes/security/csrf.php';
requireLogin();

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$devices = getTrustedDevices($userId);
$pageTitle = 'Trusted Devices';
$extraCss  = ['css/security-auth.css'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $deviceId = (int) ($_POST['device_id'] ?? 0);
    if ($deviceId && revokeTrustedDevice($userId, $deviceId)) {
        flash('success', 'Trusted device removed.');
    }
    header('Location: ' . url('trusted-devices.php'));
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="security-page">
    <div class="page-header page-header-row">
        <div class="page-header-text">
            <p class="page-eyebrow">Account</p>
            <h2>Trusted Devices</h2>
            <p>Devices you chose to trust during sign-in (90-day expiry).</p>
        </div>
        <a href="<?= url('security-settings.php') ?>" class="btn btn-ghost btn-sm">← Security</a>
    </div>

    <div class="card security-card">
        <div class="card-header">
            <h3 class="card-title">Your trusted devices</h3>
        </div>
        <div class="card-body">
            <?php if (empty($devices)): ?>
                <p class="security-empty">No trusted devices yet. Check &ldquo;Trust this device&rdquo; when signing in to add one.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table security-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Browser / OS</th>
                            <th>Last used</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($devices as $d): ?>
                        <tr>
                            <td><?= sanitize($d['device_label'] ?? 'Device') ?></td>
                            <td><?= sanitize(trim(($d['browser'] ?? '') . ' / ' . ($d['os'] ?? ''))) ?></td>
                            <td><?= sanitize(date('M j, Y', strtotime($d['last_used_at'] ?? $d['trusted_at'] ?? 'now'))) ?></td>
                            <td>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="device_id" value="<?= (int) $d['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm">Remove</button>
                                </form>
                            </td>
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
