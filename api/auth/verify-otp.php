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

$token       = trim($_POST['attempt_token'] ?? '');
$otp         = trim($_POST['otp'] ?? '');
$trustDevice = !empty($_POST['trust_device']);

if ($token === '' || strlen($otp) < 6) {
    echo json_encode(['success' => false, 'message' => 'OTP is required.']);
    exit;
}

echo json_encode(verifyLoginOtpAndComplete($token, $otp, $trustDevice));
