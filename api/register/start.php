<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security/csrf.php';
require_once __DIR__ . '/../../includes/register-layout.php';
require_once __DIR__ . '/../../includes/registration/verification.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireCsrf();

$fullName = sanitize(trim($_POST['full_name'] ?? ''));
$email    = sanitize(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$agreed   = !empty($_POST['agree_terms']);

if ($fullName === '' || $email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill out all required fields.']);
    exit;
}

if (!$agreed) {
    echo json_encode(['success' => false, 'field' => 'agree', 'message' => 'You must agree to the Terms & Conditions.']);
    exit;
}

$pwErr = validateRegistrationPassword($password, $confirm);
if ($pwErr) {
    echo json_encode(['success' => false, 'field' => $pwErr['field'], 'message' => $pwErr['message']]);
    exit;
}

$result = startRegistrationVerification($fullName, $email, password_hash($password, PASSWORD_DEFAULT));
echo json_encode($result);
