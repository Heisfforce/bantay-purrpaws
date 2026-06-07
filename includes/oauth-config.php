<?php
/**
 * Optional production hints (CLI / emails). OAuth redirect URIs are built
 * automatically from the current host — see auth/oauth-setup.php to verify.
 *
 * Set APP_URL in .env to force a canonical site URL when needed.
 */
declare(strict_types=1);

require_once __DIR__ . '/env.php';

if (!defined('APP_ENV')) {
    load_env_file(dirname(__DIR__) . '/.env');
}

$appUrl = rtrim((string) env_value('APP_URL', ''), '/');
if ($appUrl !== '') {
    return ['app_url' => $appUrl];
}

$redirect = rtrim((string) env_value('GOOGLE_REDIRECT_URI', ''), '/');
if ($redirect !== '') {
    $parts = parse_url($redirect);
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        return ['app_url' => $base];
    }
}

return ['app_url' => ''];
