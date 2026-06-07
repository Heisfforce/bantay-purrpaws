<?php
/**
 * Post-deployment verification for InfinityFree.
 *
 * Usage: https://yoursite.infinityfreeapp.com/deploy/verify.php?key=YOUR_DEPLOY_VERIFY_KEY
 *
 * DELETE this file after successful deployment.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

header('Content-Type: text/html; charset=utf-8');

$key = $_GET['key'] ?? '';
$expected = deploy_verify_key();

if ($expected === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Set DEPLOY_VERIFY_KEY in .env and pass ?key=...</p>';
    exit;
}

$checks = [];

// PHP version
$checks[] = [
    'name'    => 'PHP version',
    'ok'      => version_compare(PHP_VERSION, '8.0.0', '>='),
    'detail'  => PHP_VERSION,
];

// Extensions
$ext = check_required_extensions();
$checks[] = [
    'name'   => 'Required extensions',
    'ok'     => $ext['ok'],
    'detail' => $ext['ok'] ? 'All present' : 'Missing: ' . implode(', ', $ext['missing']),
];

$rec = check_recommended_extensions();
$checks[] = [
    'name'   => 'Recommended: sodium (email encryption)',
    'ok'     => !in_array('sodium', $rec, true),
    'detail' => in_array('sodium', $rec, true)
        ? 'libsodium not loaded — emails stored as plaintext + email_hash lookup'
        : 'libsodium OK',
];

// Database
try {
    $pdo = getDB();
    $checks[] = ['name' => 'MySQL connection', 'ok' => true, 'detail' => db_config()['host'] . ' / ' . db_config()['name']];

    $tables = ['users', 'otp_tokens', 'login_attempts', 'security_events', 'registration_verifications'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
        $exists = (bool) $stmt->fetchColumn();
        $checks[] = [
            'name'   => "Table: {$t}",
            'ok'     => $exists,
            'detail' => $exists ? 'exists' : 'MISSING — import sql/infinityfree_import.sql',
        ];
    }
} catch (Throwable $e) {
    $checks[] = [
        'name'   => 'MySQL connection',
        'ok'     => false,
        'detail' => APP_DEBUG ? $e->getMessage() : 'Connection failed — check .env DB_* values',
    ];
}

// Writable directories
foreach (['uploads', 'logs'] as $dir) {
    $path = dirname(__DIR__) . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $checks[] = [
        'name'   => "Writable: {$dir}/",
        'ok'     => is_dir($path) && is_writable($path),
        'detail' => is_writable($path) ? 'writable' : 'not writable — chmod 755 via File Manager',
    ];
}

// Email config
$checks[] = [
    'name'   => 'Email (Brevo API)',
    'ok'     => mail_is_configured(),
    'detail' => mail_is_configured() ? 'BREVO_API_KEY + MAIL_FROM set' : 'Configure BREVO_API_KEY and MAIL_FROM in .env',
];

// APP_URL
$checks[] = [
    'name'   => 'APP_URL',
    'ok'     => APP_URL !== '' && str_starts_with(APP_URL, 'https://'),
    'detail' => APP_URL !== '' ? APP_URL : 'Set APP_URL in .env to your https:// subdomain',
];

// Optional test email
if (!empty($_GET['test_email']) && filter_var($_GET['test_email'], FILTER_VALIDATE_EMAIL) && mail_is_configured()) {
    $sent = sendRawEmail($_GET['test_email'], APP_NAME . ' — Deploy Test', '<p>Email delivery works on InfinityFree.</p>');
    $checks[] = [
        'name'   => 'Test email to ' . htmlspecialchars($_GET['test_email']),
        'ok'     => $sent,
        'detail' => $sent ? 'sent' : 'failed — check logs/app.log',
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deploy Verification — BantayPurrPaws</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e7e5e4; font-size: 0.9rem; }
        .ok { color: #059669; font-weight: 600; }
        .fail { color: #dc2626; font-weight: 600; }
        .warn { background: #fffbeb; padding: 12px; border-radius: 8px; margin-top: 24px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <h1>BantayPurrPaws — Deployment Verification</h1>
    <p>Environment: <strong><?= htmlspecialchars(APP_ENV) ?></strong> · Debug: <strong><?= APP_DEBUG ? 'ON (disable!)' : 'off' ?></strong></p>
    <table>
        <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td class="<?= $c['ok'] ? 'ok' : 'fail' ?>"><?= $c['ok'] ? 'PASS' : 'FAIL' ?></td>
                <td><?= htmlspecialchars($c['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="warn">
        <strong>After all checks pass:</strong> delete <code>deploy/verify.php</code>, set <code>APP_DEBUG=0</code>,
        and remove <code>DEPLOY_VERIFY_KEY</code> from .env.
        <br><br>
        Optional: add <code>&amp;test_email=you@example.com</code> to send a test email.
    </div>
</body>
</html>
