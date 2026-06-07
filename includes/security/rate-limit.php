<?php
/**
 * Rate limiting via security_events + login_attempts counts.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/device.php';

function rateLimitKey(string $scope, string $identifier): string {
    return $scope . ':' . $identifier;
}

function countRecentEvents(string $eventType, string $ip, int $windowSeconds): int {
    $since = date('Y-m-d H:i:s', time() - $windowSeconds);
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM security_events
             WHERE event_type = ? AND ip_address = ? AND created_at >= ?'
        );
        $stmt->execute([$eventType, $ip, $since]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('countRecentEvents: ' . $e->getMessage());
        return 0;
    }
}

function isIpBlocked(?string $ip = null): bool {
    $ip = $ip ?? clientIpAddress();
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT id FROM blocked_ips
             WHERE ip_address = ?
               AND (blocked_until IS NULL OR blocked_until > NOW())
             LIMIT 1'
        );
        $stmt->execute([$ip]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function checkLoginRateLimit(?string $ip = null): ?string {
    $ip = $ip ?? clientIpAddress();

    if (isIpBlocked($ip)) {
        return 'Access from your network has been temporarily blocked due to suspicious activity.';
    }

    $passwordFails = countRecentEvents('login_password_failed', $ip, 900);
    if ($passwordFails >= 15) {
        return 'Too many login attempts. Please wait 15 minutes and try again.';
    }

    $starts = countRecentEvents('login_attempt_started', $ip, 3600);
    if ($starts >= 30) {
        return 'Too many sign-in requests from this network. Please try again later.';
    }

    return null;
}

function blockIpAddress(string $ip, string $reason, ?int $hours = 24, ?int $blockedBy = null): void {
    try {
        $pdo = getDB();
        $until = $hours ? date('Y-m-d H:i:s', time() + ($hours * 3600)) : null;
        $stmt = $pdo->prepare(
            'INSERT INTO blocked_ips (ip_address, reason, blocked_until, blocked_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), blocked_until = VALUES(blocked_until), blocked_by = VALUES(blocked_by)'
        );
        $stmt->execute([$ip, $reason, $until, $blockedBy]);
    } catch (Throwable $e) {
        error_log('blockIpAddress: ' . $e->getMessage());
    }
}

function unblockIpAddress(string $ip): bool {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('DELETE FROM blocked_ips WHERE ip_address = ?');
        return $stmt->execute([$ip]);
    } catch (Throwable $e) {
        return false;
    }
}
