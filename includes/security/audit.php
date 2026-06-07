<?php
/**
 * Security audit logging.
 */

require_once __DIR__ . '/../db.php';

function logSecurityEvent(
    string $eventType,
    string $severity = 'info',
    ?int $userId = null,
    ?string $ip = null,
    ?string $fingerprintHash = null,
    array $metadata = []
): void {
    try {
        db_insert('security_events', [
            'user_id'          => $userId,
            'event_type'       => $eventType,
            'severity'         => $severity,
            'ip_address'       => $ip,
            'fingerprint_hash' => $fingerprintHash,
            'metadata'         => $metadata !== [] ? json_encode($metadata) : null,
        ]);
    } catch (Throwable $e) {
        error_log('logSecurityEvent: ' . $e->getMessage());
    }
}
