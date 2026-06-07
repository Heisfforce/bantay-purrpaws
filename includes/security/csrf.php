<?php
/**
 * CSRF protection for forms and AJAX.
 */

require_once __DIR__ . '/../auth.php';

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfField(): string {
    $token = csrfToken();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function csrfMetaTag(): string {
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrf(?string $token = null): bool {
    startSession();
    $token ??= $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['_csrf_token'] ?? '';
    if ($token === '' || $expected === '') {
        return false;
    }
    return hash_equals($expected, $token);
}

function requireCsrf(): void {
    if (!validateCsrf()) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
        } else {
            echo 'Invalid security token.';
        }
        exit;
    }
}
