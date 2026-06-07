<?php
/**
 * Security settings for sessions, CSRF, encryption, and production hardening.
 */

declare(strict_types=1);

/** Session cookie lifetime (0 = until browser closes) */
define('SESSION_LIFETIME', (int) env_value('SESSION_LIFETIME', 0));

/** Trusted device duration in days */
define('TRUSTED_DEVICE_DAYS', (int) env_value('TRUSTED_DEVICE_DAYS', 90));

/** Max failed login attempts before lockout */
define('MAX_FAILED_LOGINS', (int) env_value('MAX_FAILED_LOGINS', 5));

/** Account lockout duration in minutes */
define('ACCOUNT_LOCKOUT_MINUTES', (int) env_value('ACCOUNT_LOCKOUT_MINUTES', 30));

/**
 * 32-byte encryption key for sensitive data at rest.
 * Generate: php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
 * Store in .env as DATA_ENCRYPTION_KEY — keep secret, never commit.
 */
function data_encryption_key_raw(): string {
    return (string) env_value('DATA_ENCRYPTION_KEY', '');
}

/**
 * One-time deploy verification key (optional).
 * Set DEPLOY_VERIFY_KEY in .env to use deploy/verify.php after upload.
 */
function deploy_verify_key(): string {
    return (string) env_value('DEPLOY_VERIFY_KEY', '');
}

/**
 * Security headers for production (call from pages if needed).
 */
function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (request_scheme() === 'https') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Block direct access to maintenance/dev scripts in production.
 */
function block_dev_scripts_in_production(): void {
    if (APP_ENV !== 'production' && APP_DEBUG) {
        return;
    }

    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $blocked = ['test_db.php', 'seed.php', 'migrate_passwords.php'];
    if (in_array($script, $blocked, true)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'This script is disabled in production. Use deploy/verify.php instead.';
        exit;
    }
}

block_dev_scripts_in_production();
