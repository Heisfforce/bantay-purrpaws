<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security/csrf.php';
require_once __DIR__ . '/../../includes/registration/verification.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireCsrf();

$token = trim($_POST['registration_token'] ?? '');
if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid registration session.']);
    exit;
}

$result = resendRegistrationCode($token);
echo json_encode($result);
