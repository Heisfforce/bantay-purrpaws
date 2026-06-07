<?php
/**
 * MySQL database configuration for InfinityFree / shared hosting.
 *
 * InfinityFree panel → MySQL Databases:
 *   DB_HOST     = sqlXXX.infinityfree.com (NOT your website URL)
 *   DB_NAME     = epiz_XXXXX_dbname
 *   DB_USER     = epiz_XXXXX
 *   DB_PASSWORD = (from panel)
 */

declare(strict_types=1);

/**
 * @return array{host: string, port: string, name: string, user: string, pass: string, charset: string}
 */
function db_config(): array {
    return [
        'host'    => (string) env_value('DB_HOST', 'localhost'),
        'port'    => (string) env_value('DB_PORT', '3306'),
        'name'    => (string) env_value('DB_NAME', ''),
        'user'    => (string) env_value('DB_USER', ''),
        'pass'    => (string) env_value('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ];
}

function db_is_configured(): bool {
    $c = db_config();
    return $c['name'] !== '' && $c['user'] !== '';
}

/**
 * User-friendly error when credentials are missing (web requests only).
 */
function db_config_error_response(): void {
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Configuration Required</title></head><body>';
    echo '<h1>Database Not Configured</h1>';
    echo '<p>Copy <code>.env.example</code> to <code>.env</code> (or set hosting Variables) and configure <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, and <code>DB_PASSWORD</code>.</p>';
    if (APP_DEBUG) {
        echo '<p>See <code>docs/DEPLOY_INFINITYFREE.md</code> for setup steps.</p>';
    }
    echo '</body></html>';
    exit;
}

/**
 * Build PDO DSN for MySQL/MariaDB on InfinityFree.
 */
function db_dsn(): string {
    $c = db_config();
    return sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $c['host'],
        $c['port'],
        $c['name'],
        $c['charset']
    );
}

/**
 * PDO connection options tuned for shared hosting.
 *
 * @return array<int, mixed>
 */
function db_pdo_options(): array {
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ];
}
