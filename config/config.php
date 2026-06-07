<?php
/**
 * BantayPurrPaws — Central application configuration.
 * Loaded automatically by includes/db.php and includes/mailer.php.
 *
 * InfinityFree: upload this folder with the rest of the app to htdocs.
 * Copy .env.example → .env and fill in your panel credentials.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';

$envPath = dirname(__DIR__) . '/.env';
load_env_file($envPath);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/smtp.php';

/** Application environment: production | local */
define('APP_ENV', strtolower((string) env_value('APP_ENV', 'production')));

/** When true, detailed errors may be shown — NEVER enable on InfinityFree production */
define('APP_DEBUG', env_bool('APP_DEBUG', false));

/** Public site URL (no trailing slash), e.g. https://yoursite.infinityfreeapp.com */
define('APP_URL', rtrim((string) env_value('APP_URL', ''), '/'));

/** Application display name */
define('APP_NAME', (string) env_value('APP_NAME', 'BantayPurrPaws'));

require_once __DIR__ . '/security.php';

/**
 * Whether the app runs on InfinityFree-style hosting.
 */
function is_infinityfree_host(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return (bool) preg_match('/\.(infinityfree\.me|infinityfreeapp\.com|rf\.gd|42web\.io|epizy\.com)$/i', $host);
}

/**
 * Production-safe error handling — hide stack traces on shared hosting.
 */
function configure_error_handling(): void {
    if (APP_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        return;
    }

    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

configure_error_handling();

/**
 * Required PHP extensions for InfinityFree deployment.
 *
 * @return array{ok: bool, missing: string[]}
 */
function check_required_extensions(): array {
    $required = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'fileinfo'];
    $missing  = [];

    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }

    return ['ok' => $missing === [], 'missing' => $missing];
}

/**
 * Optional but recommended extensions.
 *
 * @return string[]
 */
function check_recommended_extensions(): array {
    $recommended = ['sodium', 'openssl'];
    $missing     = [];
    foreach ($recommended as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    return $missing;
}
