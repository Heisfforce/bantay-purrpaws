<?php
/**
 * Email verification link handler (Step 2 → Step 3).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/registration/verification.php';

startSession();

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    header('Location: ' . url('register-expired.php?reason=invalid'));
    exit;
}

$result = verifyRegistrationLink($token);

if (!empty($result['expired'])) {
    header('Location: ' . url('register-expired.php?reg=' . urlencode($result['registration_token'] ?? '') . '&reason=link'));
    exit;
}

if (!($result['success'] ?? false)) {
    flash('error', $result['message'] ?? 'Verification failed.');
    header('Location: ' . url('register-expired.php?reason=invalid'));
    exit;
}

flash('success', $result['message'] ?? 'Email verification successful.');
header('Location: ' . url('register-link-success.php?reg=' . urlencode($result['registration_token'])));
exit;
