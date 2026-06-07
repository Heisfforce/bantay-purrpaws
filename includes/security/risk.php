<?php
/**
 * Risk scoring for login attempts.
 */

require_once __DIR__ . '/../db.php';

function calculateLoginRisk(array $context, int $userId, bool $isTrustedDevice): array {
    $score = 0;
    $reasons = [];

    if (!$isTrustedDevice) {
        $score += 25;
        $reasons[] = 'unrecognized_device';
    }

    if (($context['device_type'] ?? '') === 'mobile') {
        $score += 5;
    }

    if (($context['location_country'] ?? '') === 'Local') {
        $score -= 10;
    } elseif (empty($context['location_country'])) {
        $score += 10;
        $reasons[] = 'unknown_location';
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT security_risk_score, failed_login_count FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $score += min(30, (int) ($user['security_risk_score'] ?? 0));
            if ((int) ($user['failed_login_count'] ?? 0) >= 3) {
                $score += 15;
                $reasons[] = 'recent_failures';
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    if (!empty($context['fingerprint_hash'])) {
        try {
            $fp = db_select(
                'device_fingerprints',
                'fingerprint_hash=eq.' . urlencode($context['fingerprint_hash']) . '&limit=1',
                true
            );
            if ($fp && (int) ($fp['failed_count'] ?? 0) >= 2) {
                $score += 20;
                $reasons[] = 'risky_fingerprint';
            }
        } catch (Throwable $e) {
            // table may not exist yet
        }
    }

    $score = max(0, min(100, $score));

    $level = match (true) {
        $score >= 75 => 'critical',
        $score >= 50 => 'high',
        $score >= 25 => 'medium',
        default      => 'low',
    };

    return [
        'risk_score'  => $score,
        'risk_level'  => $level,
        'reasons'     => $reasons,
    ];
}

function incrementUserRiskScore(int $userId, int $amount = 10): void {
    try {
        $pdo = getDB();
        $pdo->prepare(
            'UPDATE users SET security_risk_score = LEAST(100, security_risk_score + ?) WHERE id = ?'
        )->execute([$amount, $userId]);
    } catch (Throwable $e) {
        error_log('incrementUserRiskScore: ' . $e->getMessage());
    }
}

function incrementFingerprintRisk(string $fingerprintHash, bool $failed = false): void {
    try {
        $pdo = getDB();
        if ($failed) {
            $pdo->prepare(
                'INSERT INTO device_fingerprints (fingerprint_hash, failed_count, risk_score)
                 VALUES (?, 1, 10)
                 ON DUPLICATE KEY UPDATE failed_count = failed_count + 1, risk_score = LEAST(100, risk_score + 10), last_seen_at = NOW()'
            )->execute([$fingerprintHash]);
        } else {
            $pdo->prepare(
                'INSERT INTO device_fingerprints (fingerprint_hash, login_count)
                 VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE login_count = login_count + 1, last_seen_at = NOW()'
            )->execute([$fingerprintHash]);
        }
    } catch (Throwable $e) {
        error_log('incrementFingerprintRisk: ' . $e->getMessage());
    }
}

function isTrustedDevice(int $userId, string $fingerprintHash): bool {
    try {
        $row = db_select(
            'trusted_devices',
            'user_id=eq.' . $userId
            . '&fingerprint_hash=eq.' . urlencode($fingerprintHash)
            . '&limit=1',
            true
        );
        if (!$row) {
            return false;
        }
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return false;
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
