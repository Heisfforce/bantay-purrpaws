<?php
/**
 * Start secure login flow after email + password validation.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security/csrf.php';
require_once __DIR__ . '/../../includes/security/login-flow.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireCsrf();

$email       = trim($_POST['email'] ?? '');
$password    = $_POST['password'] ?? '';
$fingerprint = trim($_POST['device_fingerprint'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$result = startLoginAttempt($email, $password, $fingerprint);
echo json_encode($result);
