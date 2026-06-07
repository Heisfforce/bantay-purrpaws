<?php
/**
 * Database-backed session tracking.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/device.php';
require_once __DIR__ . '/audit.php';

function createUserSession(int $userId, array $context): string {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400 * 7);

    db_insert('user_sessions', [
        'user_id'          => $userId,
        'session_token'    => $token,
        'php_session_id'   => session_id(),
        'fingerprint_hash' => $context['fingerprint_hash'] ?? null,
        'ip_address'       => $context['ip_address'] ?? null,
        'user_agent'       => $context['user_agent'] ?? null,
        'browser'          => $context['browser'] ?? null,
        'os'               => $context['os'] ?? null,
        'is_active'        => true,
        'expires_at'       => $expires,
    ]);

    startSession();
    session_regenerate_id(true);
    $_SESSION['security_session_token'] = $token;

    return $token;
}

function touchUserSession(?string $token = null): void {
    $token ??= $_SESSION['security_session_token'] ?? null;
    if (!$token) {
        return;
    }
    try {
        db_update('user_sessions', ['last_activity_at' => date('Y-m-d H:i:s')], 'session_token=eq.' . urlencode($token));
    } catch (Throwable $e) {
        // ignore
    }
}

function revokeUserSession(string $token, string $reason = 'user_logout'): void {
    db_update('user_sessions', [
        'is_active'  => false,
        'revoked_at' => date('Y-m-d H:i:s'),
    ], 'session_token=eq.' . urlencode($token));

    $row = db_select('user_sessions', 'session_token=eq.' . urlencode($token) . '&limit=1', true);
    if ($row) {
        logSecurityEvent('session_revoked', 'info', (int) $row['user_id'], null, null, ['reason' => $reason]);
    }
}

function revokeAllUserSessions(int $userId, ?string $exceptToken = null): int {
    try {
        $pdo = getDB();
        if ($exceptToken) {
            $stmt = $pdo->prepare(
                'UPDATE user_sessions SET is_active = 0, revoked_at = NOW()
                 WHERE user_id = ? AND session_token <> ? AND is_active = 1'
            );
            $stmt->execute([$userId, $exceptToken]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE user_sessions SET is_active = 0, revoked_at = NOW()
                 WHERE user_id = ? AND is_active = 1'
            );
            $stmt->execute([$userId]);
        }
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function revokeSessionById(int $sessionId): bool {
    $row = db_select('user_sessions', 'id=eq.' . $sessionId . '&limit=1', true);
    if (!$row) {
        return false;
    }
    revokeUserSession($row['session_token'], 'admin_force_logout');
    return true;
}

function getActiveSessionsForUser(int $userId): array {
    return db_select(
        'user_sessions',
        'user_id=eq.' . $userId . '&is_active=eq.true&order=last_activity_at.desc'
    ) ?: [];
}

function isAccountLocked(array $user): bool {
    if (empty($user['account_locked_until'])) {
        return false;
    }
    return strtotime($user['account_locked_until']) > time();
}

function recordFailedPasswordAttempt(int $userId): void {
    try {
        $pdo = getDB();
        $pdo->prepare(
            'UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = ?'
        )->execute([$userId]);

        $stmt = $pdo->prepare('SELECT failed_login_count FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= 5) {
            $lockUntil = date('Y-m-d H:i:s', time() + 900);
            $pdo->prepare(
                'UPDATE users SET account_locked_until = ? WHERE id = ?'
            )->execute([$lockUntil, $userId]);
            logSecurityEvent('account_locked', 'warning', $userId, clientIpAddress(), null, ['until' => $lockUntil]);
        }
    } catch (Throwable $e) {
        error_log('recordFailedPasswordAttempt: ' . $e->getMessage());
    }
}

function clearFailedPasswordAttempts(int $userId): void {
    try {
        getDB()->prepare(
            'UPDATE users SET failed_login_count = 0, account_locked_until = NULL WHERE id = ?'
        )->execute([$userId]);
    } catch (Throwable $e) {
        error_log('clearFailedPasswordAttempts: ' . $e->getMessage());
    }
}

function requirePasswordResetIfFlagged(array $user): ?string {
    if (!empty($user['require_password_reset'])) {
        return 'Your account requires a password reset due to suspicious activity. Please use Forgot Password.';
    }
    return null;
}

// clientIpAddress from device.php
