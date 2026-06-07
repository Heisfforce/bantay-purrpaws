<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security/csrf.php';
require_once __DIR__ . '/../../includes/security/login-flow.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token = trim($_GET['attempt_token'] ?? $_SESSION['pending_login_attempt'] ?? '');
if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'No active login attempt.']);
    exit;
}

echo json_encode(getLoginAttemptStatus($token));
