<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security/sessions.php';
startSession();

if (!empty($_SESSION['security_session_token'])) {
    revokeUserSession($_SESSION['security_session_token'], 'user_logout');
}

session_destroy();
header('Location: ' . url('login.php'));
exit;
