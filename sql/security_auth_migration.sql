-- BantayPurrPaws — Enterprise security auth tables
-- Run once: mysql -u root bantaypurrpaws < sql/security_auth_migration.sql

USE bantaypurrpaws;

-- ── User security columns ───────────────────────────────────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `failed_login_count` INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `account_locked_until` DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `require_password_reset` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `security_risk_score` INT NOT NULL DEFAULT 0;

-- Extend OTP purposes for login security flow
ALTER TABLE `otp_tokens`
    MODIFY COLUMN `purpose` ENUM(
        'registration',
        'login',
        'login_security',
        'password_reset',
        'google_link',
        'profile_update',
        'email_change_current',
        'email_change_new',
        'staff_invite'
    ) NOT NULL DEFAULT 'registration';

-- ── Login attempts ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`                INT NOT NULL AUTO_INCREMENT,
    `attempt_token`     VARCHAR(64) NOT NULL,
    `challenge_token`   VARCHAR(64) NOT NULL,
    `user_id`           INT NOT NULL,
    `ip_address`        VARCHAR(45) NOT NULL,
    `user_agent`        VARCHAR(512) DEFAULT NULL,
    `browser`           VARCHAR(80) DEFAULT NULL,
    `os`                VARCHAR(80) DEFAULT NULL,
    `device_type`       VARCHAR(40) DEFAULT NULL,
    `fingerprint_hash`  VARCHAR(64) DEFAULT NULL,
    `location_label`    VARCHAR(150) DEFAULT NULL,
    `location_city`     VARCHAR(80) DEFAULT NULL,
    `location_country`  VARCHAR(80) DEFAULT NULL,
    `risk_level`        ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    `risk_score`        INT NOT NULL DEFAULT 0,
    `status`            ENUM(
        'pending_verification',
        'email_approved',
        'email_denied',
        'number_passed',
        'otp_passed',
        'completed',
        'denied',
        'expired',
        'failed'
    ) NOT NULL DEFAULT 'pending_verification',
    `display_number`    SMALLINT DEFAULT NULL,
    `number_options`    VARCHAR(32) DEFAULT NULL,
    `correct_number`    SMALLINT DEFAULT NULL,
    `number_attempts`   TINYINT NOT NULL DEFAULT 0,
    `otp_attempts`      TINYINT NOT NULL DEFAULT 0,
    `email_verified_at` DATETIME DEFAULT NULL,
    `number_verified_at` DATETIME DEFAULT NULL,
    `completed_at`      DATETIME DEFAULT NULL,
    `expires_at`        DATETIME NOT NULL,
    `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_attempt_token` (`attempt_token`),
    UNIQUE KEY `uk_challenge_token` (`challenge_token`),
    KEY `idx_login_attempts_user` (`user_id`),
    KEY `idx_login_attempts_status` (`status`),
    KEY `idx_login_attempts_ip` (`ip_address`),
    CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Device fingerprints ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `device_fingerprints` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `fingerprint_hash` VARCHAR(64) NOT NULL,
    `user_agent`       VARCHAR(512) DEFAULT NULL,
    `browser`          VARCHAR(80) DEFAULT NULL,
    `os`               VARCHAR(80) DEFAULT NULL,
    `device_type`      VARCHAR(40) DEFAULT NULL,
    `risk_score`       INT NOT NULL DEFAULT 0,
    `login_count`      INT NOT NULL DEFAULT 0,
    `failed_count`     INT NOT NULL DEFAULT 0,
    `first_seen_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fingerprint_hash` (`fingerprint_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Trusted devices ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `trusted_devices` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `user_id`          INT NOT NULL,
    `fingerprint_hash` VARCHAR(64) NOT NULL,
    `device_label`     VARCHAR(120) NOT NULL,
    `browser`          VARCHAR(80) DEFAULT NULL,
    `os`               VARCHAR(80) DEFAULT NULL,
    `ip_address_last`  VARCHAR(45) DEFAULT NULL,
    `trusted_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`       DATETIME DEFAULT NULL,
    `last_used_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_fingerprint` (`user_id`, `fingerprint_hash`),
    KEY `idx_trusted_user` (`user_id`),
    CONSTRAINT `trusted_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Security audit events ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `security_events` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `user_id`          INT DEFAULT NULL,
    `event_type`       VARCHAR(64) NOT NULL,
    `severity`         ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `ip_address`       VARCHAR(45) DEFAULT NULL,
    `fingerprint_hash` VARCHAR(64) DEFAULT NULL,
    `metadata`         LONGTEXT DEFAULT NULL,
    `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_security_events_user` (`user_id`),
    KEY `idx_security_events_type` (`event_type`),
    KEY `idx_security_events_created` (`created_at`),
    CONSTRAINT `security_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Active sessions ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `user_id`          INT NOT NULL,
    `session_token`    VARCHAR(64) NOT NULL,
    `php_session_id`   VARCHAR(128) DEFAULT NULL,
    `fingerprint_hash` VARCHAR(64) DEFAULT NULL,
    `ip_address`       VARCHAR(45) DEFAULT NULL,
    `user_agent`       VARCHAR(512) DEFAULT NULL,
    `browser`          VARCHAR(80) DEFAULT NULL,
    `os`               VARCHAR(80) DEFAULT NULL,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at`       DATETIME DEFAULT NULL,
    `revoked_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session_token` (`session_token`),
    KEY `idx_user_sessions_user` (`user_id`),
    KEY `idx_user_sessions_active` (`is_active`),
    CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Blocked IPs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `ip_address`    VARCHAR(45) NOT NULL,
    `reason`        VARCHAR(255) NOT NULL,
    `blocked_until` DATETIME DEFAULT NULL,
    `blocked_by`    INT DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_blocked_ip` (`ip_address`),
    KEY `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Account recovery ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `account_recovery_records` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `user_id`       INT NOT NULL,
    `recovery_type` ENUM('password_reset','account_unlock','forced_reset') NOT NULL,
    `token_hash`    VARCHAR(64) NOT NULL,
    `status`        ENUM('pending','completed','expired','cancelled') NOT NULL DEFAULT 'pending',
    `ip_address`    VARCHAR(45) DEFAULT NULL,
    `expires_at`    DATETIME NOT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_recovery_user` (`user_id`),
    KEY `idx_recovery_token` (`token_hash`),
    CONSTRAINT `account_recovery_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
