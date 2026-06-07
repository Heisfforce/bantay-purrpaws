<?php
/**
 * Device detection, fingerprinting, and geo lookup.
 */

function clientIpAddress(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        $value = $_SERVER[$key];
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $value = trim(explode(',', $value)[0]);
        }
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }
    return '0.0.0.0';
}

function parseUserAgent(?string $ua = null): array {
    $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
    $browser = 'Unknown Browser';
    $os      = 'Unknown OS';
    $device  = 'desktop';

    if (preg_match('/Edg\/([\d.]+)/i', $ua)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/Chrome\/([\d.]+)/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox\/([\d.]+)/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari\/([\d.]+)/i', $ua) && !str_contains($ua, 'Chrome')) {
        $browser = 'Safari';
    }

    if (preg_match('/Windows NT/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android';
        $device = 'mobile';
    } elseif (preg_match('/iPhone|iPad/i', $ua)) {
        $os = 'iOS';
        $device = str_contains($ua, 'iPad') ? 'tablet' : 'mobile';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    if (preg_match('/Mobile|Android|iPhone/i', $ua)) {
        $device = 'mobile';
    }

    return [
        'user_agent'  => substr($ua, 0, 512),
        'browser'     => $browser,
        'os'          => $os,
        'device_type' => $device,
    ];
}

function buildFingerprintHash(string $clientFingerprint, ?string $ua = null): string {
    $ua  = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $raw = $clientFingerprint . '|' . $ua . '|' . clientIpAddress();
    return hash('sha256', $raw);
}

function lookupGeoLocation(string $ip): array {
    if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
        return [
            'location_label'   => 'Local Network',
            'location_city'    => 'Local',
            'location_country' => 'Local',
        ];
    }

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,city,regionName';
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        return [
            'location_label'   => 'Unknown location',
            'location_city'    => null,
            'location_country' => null,
        ];
    }

    $data = json_decode($json, true);
    if (($data['status'] ?? '') !== 'success') {
        return [
            'location_label'   => 'Unknown location',
            'location_city'    => null,
            'location_country' => null,
        ];
    }

    $city    = $data['city'] ?? '';
    $region  = $data['regionName'] ?? '';
    $country = $data['country'] ?? '';
    $label   = trim(implode(', ', array_filter([$city, $region, $country])));

    return [
        'location_label'   => $label !== '' ? $label : 'Unknown location',
        'location_city'    => $city ?: null,
        'location_country' => $country ?: null,
    ];
}

function deviceLabel(array $device): string {
    return trim(($device['browser'] ?? 'Browser') . ' on ' . ($device['os'] ?? 'Unknown'));
}

function collectClientContext(?string $clientFingerprint = null): array {
    $device = parseUserAgent();
    $ip     = clientIpAddress();
    $geo    = lookupGeoLocation($ip);
    $fp     = $clientFingerprint
        ? buildFingerprintHash($clientFingerprint)
        : hash('sha256', ($device['user_agent'] ?? '') . '|' . $ip);

    return array_merge($device, $geo, [
        'ip_address'       => $ip,
        'fingerprint_hash' => $fp,
    ]);
}
