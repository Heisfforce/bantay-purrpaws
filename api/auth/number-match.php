<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security/csrf.php';
require_once __DIR__ . '/../../includes/security/login-flow.php';

startSession();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token    = trim($_POST['attempt_token'] ?? '');
$selected = (int) ($_POST['selected_number'] ?? 0);

if ($token === '' || $selected <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

echo json_encode(verifyNumberMatch($token, $selected));
