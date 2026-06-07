<?php
/**
 * Mobile/API registration — requires full email verification workflow.
 * Step 1: POST here with full_name, email, password → returns registration_token
 * Step 2: User clicks link from email (browser)
 * Step 3: POST api/register/verify-code.php with registration_token + code
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/register-layout.php';
require_once __DIR__ . '/../includes/registration/verification.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$fullName = sanitize(trim($_POST['full_name'] ?? ''));
$email    = sanitize(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (!$fullName || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

$pwErr = validateRegistrationPassword($password, $password);
if ($pwErr) {
    echo json_encode(['success' => false, 'message' => $pwErr['message']]);
    exit;
}

$result = startRegistrationVerification($fullName, $email, password_hash($password, PASSWORD_DEFAULT));

if ($result['success'] ?? false) {
    $result['verify_link_url'] = url('register-pending.php?reg=' . urlencode($result['registration_token']));
    $result['next_step'] = 'Check email and click verification link, then enter the 6-digit code via api/register/verify-code.php';
}

echo json_encode($result);
