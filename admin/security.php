<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security/csrf.php';
require_once __DIR__ . '/../includes/security/login-flow.php';
require_once __DIR__ . '/../includes/security/rate-limit.php';
require_once __DIR__ . '/../includes/security/sessions.php';
requireAdminOnly();

$tab = $_GET['tab'] ?? 'attempts';
$pageTitle = 'Security Dashboard';
$extraCss  = ['css/security-auth.css'];
$useSweetAlert = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'unblock_ip') {
        unblockIpAddress(trim($_POST['ip'] ?? ''));
        flash('success', 'IP address unblocked.');
    } elseif ($action === 'unlock_user') {
        unlockUserAccount((int) ($_POST['user_id'] ?? 0));
        flash('success', 'Account unlocked.');
    } elseif ($action === 'revoke_session') {
        revokeSessionById((int) ($_POST['session_id'] ?? 0));
        flash('success', 'Session revoked.');
    } elseif ($action === 'block_ip') {
        blockIpAddress(
            trim($_POST['ip'] ?? ''),
            trim($_POST['reason'] ?? 'Manual block'),
            (int) ($_POST['hours'] ?? 24),
            (int) ($_SESSION['user_id'] ?? 0)
        );
        flash('success', 'IP blocked.');
    }

    header('Location: ' . url('admin/security.php?tab=' . urlencode($tab)));
    exit;
}

$attempts = db_select('login_attempts', 'order=created_at.desc&limit=100') ?: [];
$events   = db_select('security_events', 'order=created_at.desc&limit=100') ?: [];
$sessions = db_select('user_sessions', 'is_active=eq.true&order=last_activity_at.desc&limit=100') ?: [];

try {
    $pdo = getDB();
    $blockedIps = $pdo->query(
        'SELECT * FROM blocked_ips WHERE blocked_until IS NULL OR blocked_until > NOW() ORDER BY created_at DESC LIMIT 100'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $blockedIps = [];
}

$suspicious = array_filter($attempts, fn($a) => in_array($a['status'], ['email_denied','denied','failed'], true)
    || in_array($a['risk_level'] ?? '', ['high','critical'], true));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="security-page">
    <div class="page-header page-header-row">
        <div class="page-header-text">
            <p class="page-eyebrow">Administrator</p>
            <h2>Security Dashboard</h2>
            <p>Monitor login attempts, suspicious activity, sessions, and blocked IPs.</p>
        </div>
        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-ghost btn-sm">← Dashboard</a>
    </div>

    <nav class="security-tabs" aria-label="Security sections">
        <?php foreach (['attempts'=>'Login Attempts','suspicious'=>'Suspicious','events'=>'Events','sessions'=>'Sessions','blocked'=>'Blocked IPs'] as $key => $label): ?>
            <a href="?tab=<?= $key ?>" class="btn btn-sm <?= $tab === $key ? 'btn-accent' : 'btn-ghost' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'attempts' || $tab === 'suspicious'): ?>
    <?php $rows = $tab === 'suspicious' ? $suspicious : $attempts; ?>
    <div class="card security-card">
        <div class="card-header">
            <h3 class="card-title"><?= $tab === 'suspicious' ? 'Suspicious activity' : 'All login attempts' ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <p class="security-empty">No records found.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table security-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>IP</th>
                            <th>Device</th>
                            <th>Location</th>
                            <th>Risk</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $risk = $r['risk_level'] ?? 'low';
                        $st   = $r['status'] ?? '';
                        $pill = in_array($st, ['completed'], true) ? 'success' : (in_array($st, ['email_denied','denied','failed'], true) ? 'danger' : 'pending');
                    ?>
                        <tr>
                            <td><?= sanitize(date('M j, g:i A', strtotime($r['created_at'] ?? 'now'))) ?></td>
                            <td>#<?= (int) ($r['user_id'] ?? 0) ?></td>
                            <td><?= sanitize($r['ip_address'] ?? '') ?></td>
                            <td><?= sanitize(trim(($r['browser'] ?? '') . ' / ' . ($r['os'] ?? ''))) ?></td>
                            <td><?= sanitize($r['location_label'] ?? '—') ?></td>
                            <td><span class="risk-badge risk-<?= sanitize($risk) ?>"><?= sanitize(ucfirst($risk)) ?></span></td>
                            <td><span class="status-pill <?= $pill ?>"><?= sanitize(str_replace('_', ' ', $st)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'events'): ?>
    <div class="card security-card">
        <div class="card-header"><h3 class="card-title">Security events</h3></div>
        <div class="card-body">
            <?php if (empty($events)): ?>
                <p class="security-empty">No security events logged yet.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table security-table">
                    <thead><tr><th>Time</th><th>Type</th><th>Severity</th><th>User</th><th>IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $e): ?>
                        <tr>
                            <td><?= sanitize(date('M j, g:i A', strtotime($e['created_at'] ?? 'now'))) ?></td>
                            <td><?= sanitize(str_replace('_', ' ', $e['event_type'] ?? '')) ?></td>
                            <td class="severity-<?= sanitize($e['severity'] ?? 'info') ?>"><?= sanitize($e['severity'] ?? '') ?></td>
                            <td><?= (int) ($e['user_id'] ?? 0) ?: '—' ?></td>
                            <td><?= sanitize($e['ip_address'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'sessions'): ?>
    <div class="card security-card">
        <div class="card-header"><h3 class="card-title">Active sessions</h3></div>
        <div class="card-body">
            <?php if (empty($sessions)): ?>
                <p class="security-empty">No active sessions.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table security-table">
                    <thead><tr><th>User</th><th>Device</th><th>IP</th><th>Last active</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td>#<?= (int) ($s['user_id'] ?? 0) ?></td>
                            <td><?= sanitize(trim(($s['browser'] ?? '') . ' on ' . ($s['os'] ?? ''))) ?></td>
                            <td><?= sanitize($s['ip_address'] ?? '—') ?></td>
                            <td><?= sanitize(date('M j, g:i A', strtotime($s['last_activity_at'] ?? $s['created_at'] ?? 'now'))) ?></td>
                            <td>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="revoke_session">
                                    <input type="hidden" name="session_id" value="<?= (int) $s['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit">Force logout</button>
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
    <?php endif; ?>

    <?php if ($tab === 'blocked'): ?>
    <div class="card security-card">
        <div class="card-header"><h3 class="card-title">Block IP address</h3></div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" class="security-form-grid">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="block_ip">
                <div class="form-group">
                    <label class="form-label" for="block_ip">IP address</label>
                    <input class="form-control" id="block_ip" name="ip" required placeholder="e.g. 192.168.1.1">
                </div>
                <div class="form-group">
                    <label class="form-label" for="block_reason">Reason</label>
                    <input class="form-control" id="block_reason" name="reason" value="Manual admin block">
                </div>
                <div class="form-group">
                    <label class="form-label" for="block_hours">Hours</label>
                    <input class="form-control" id="block_hours" name="hours" type="number" value="24" min="1">
                </div>
                <button class="btn btn-accent" type="submit">Block IP</button>
            </form>
        </div>
    </div>

    <div class="card security-card">
        <div class="card-header"><h3 class="card-title">Blocked IPs</h3></div>
        <div class="card-body">
            <?php if (empty($blockedIps)): ?>
                <p class="security-empty">No blocked IP addresses.</p>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="table security-table">
                    <thead><tr><th>IP</th><th>Reason</th><th>Until</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($blockedIps as $b): ?>
                        <tr>
                            <td><?= sanitize($b['ip_address'] ?? '') ?></td>
                            <td><?= sanitize($b['reason'] ?? '') ?></td>
                            <td><?= sanitize($b['blocked_until'] ? date('M j, Y g:i A', strtotime($b['blocked_until'])) : 'Permanent') ?></td>
                            <td>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="unblock_ip">
                                    <input type="hidden" name="ip" value="<?= sanitize($b['ip_address'] ?? '') ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit">Unblock</button>
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

    <div class="card security-card">
        <div class="card-header"><h3 class="card-title">Unlock account</h3></div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" class="security-form-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="unlock_user">
                <div class="form-group">
                    <label class="form-label" for="unlock_user_id">User ID</label>
                    <input class="form-control" id="unlock_user_id" name="user_id" type="number" required min="1">
                </div>
                <button class="btn btn-accent" type="submit">Unlock account</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
