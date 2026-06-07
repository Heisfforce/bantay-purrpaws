<?php
/**
 * Email link handler for Yes/No login verification.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security/login-flow.php';

startSession();

$token  = trim($_GET['token'] ?? '');
$action = strtolower(trim($_GET['action'] ?? ''));

if ($token === '' || !in_array($action, ['yes', 'no'], true)) {
    http_response_code(400);
    require_once __DIR__ . '/../includes/paths.php';
    header('Location: ' . url('login.php?error=invalid_link'));
    exit;
}

if ($action === 'yes') {
    $result = approveEmailChallenge($token);
    if ($result['success']) {
        $_SESSION['pending_login_attempt'] = $result['attempt_token'];
        header('Location: ' . url('login.php?step=number&attempt=' . urlencode($result['attempt_token'])));
        exit;
    }
    flash('error', $result['message']);
    header('Location: ' . url('login.php'));
    exit;
}

denyEmailChallenge($token);
header('Location: ' . url('security/login-denied.php'));
exit;
