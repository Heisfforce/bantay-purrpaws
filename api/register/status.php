<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/registration/verification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token = trim($_GET['registration_token'] ?? '');
if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid registration session.']);
    exit;
}

$row = findRegistrationByToken($token);
echo json_encode(registrationStatusPayload($row));
