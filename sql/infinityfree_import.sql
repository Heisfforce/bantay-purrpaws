-- ============================================================
--  BantayPurrPaws — Complete import for InfinityFree (MariaDB)
--
--  INSTRUCTIONS (phpMyAdmin):
--  1. Create MySQL database in InfinityFree control panel
--  2. Select your database in phpMyAdmin left sidebar
--  3. Import this file
--
--  Default admin (change password immediately after login):
--    email: anthony.domasig@evsu.edu.ph
--    password: password
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `registration_verifications`;
DROP TABLE IF EXISTS `account_recovery_records`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `security_events`;
DROP TABLE IF EXISTS `trusted_devices`;
DROP TABLE IF EXISTS `device_fingerprints`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `blocked_ips`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `adoption_applications`;
DROP TABLE IF EXISTS `pet_images`;
DROP TABLE IF EXISTS `report_logs`;
DROP TABLE IF EXISTS `rescue_reports`;
DROP TABLE IF EXISTS `pets`;
DROP TABLE IF EXISTS `otp_tokens`;
DROP TABLE IF EXISTS `staff_invites`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
--  BantayPurrPaws — Complete database import (XAMPP / MariaDB)
--
--  Includes all schema updates from:
--    sql/schema.sql, database/*.sql, sql/*_migration.sql
--
--  Import via phpMyAdmin or:
--    mysql -u root < sql/bantaypurrpaws_import.sql
--
--  Default admin (change password after first login):
--    email: anthony.domasig@evsu.edu.ph
--    password: password
-- ============================================================

-- ── Users ───────────────────────────────────────────────────
CREATE TABLE `users` (
    `id`                     INT NOT NULL AUTO_INCREMENT,
    `full_name`              VARCHAR(150) NOT NULL,
    `email`                  VARCHAR(512) NOT NULL,
    `email_hash`             VARCHAR(64) DEFAULT NULL,
    `password`               VARCHAR(255) DEFAULT NULL,
    `role`                   ENUM('user','staff','admin') NOT NULL DEFAULT 'user',
    `google_id`              VARCHAR(128) DEFAULT NULL,
    `avatar_url`             VARCHAR(512) DEFAULT NULL,
    `email_verified`         TINYINT(1) NOT NULL DEFAULT 0,
    `auth_provider`          ENUM('local','google') NOT NULL DEFAULT 'local',
    `username`               VARCHAR(50) DEFAULT NULL,
    `phone_number`           VARCHAR(512) DEFAULT NULL,
    `profile_picture`        VARCHAR(512) DEFAULT NULL,
    `staff_permissions`      LONGTEXT DEFAULT NULL,
    `permissions_changed_at` DATETIME DEFAULT NULL,
    `failed_login_count`     INT NOT NULL DEFAULT 0,
    `account_locked_until`   DATETIME DEFAULT NULL,
    `require_password_reset` TINYINT(1) NOT NULL DEFAULT 0,
    `security_risk_score`    INT NOT NULL DEFAULT 0,
    `created_at`             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email_hash` (`email_hash`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rescue reports ──────────────────────────────────────────
CREATE TABLE `rescue_reports` (
    `id`             INT NOT NULL AUTO_INCREMENT,
    `report_code`    VARCHAR(20) NOT NULL,
    `reporter_id`    INT NOT NULL,
    `reporter_name`  VARCHAR(150) NOT NULL,
    `contact_number` VARCHAR(512) NOT NULL,
    `animal_type`    VARCHAR(100) DEFAULT NULL,
    `location`       TEXT NOT NULL,
    `description`    TEXT,
    `photo_path`     VARCHAR(255) DEFAULT NULL,
    `status`         ENUM('pending','in_progress','rescued','failed') NOT NULL DEFAULT 'pending',
    `assigned_to`    INT DEFAULT NULL,
    `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `report_code` (`report_code`),
    KEY `reporter_id` (`reporter_id`),
    KEY `assigned_to` (`assigned_to`),
    CONSTRAINT `rescue_reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `rescue_reports_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Report activity log ─────────────────────────────────────
CREATE TABLE `report_logs` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `report_id`  INT NOT NULL,
    `updated_by` INT NOT NULL,
    `old_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `notes`      TEXT,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `report_id` (`report_id`),
    KEY `updated_by` (`updated_by`),
    CONSTRAINT `report_logs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `rescue_reports` (`id`) ON DELETE CASCADE,
    CONSTRAINT `report_logs_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pets ────────────────────────────────────────────────────
CREATE TABLE `pets` (
    `id`                    INT NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(100) NOT NULL,
    `breed`                 VARCHAR(100) NOT NULL,
    `age`                   VARCHAR(50) NOT NULL,
    `gender`                ENUM('Male','Female','Unknown') NOT NULL DEFAULT 'Unknown',
    `vaccination_status`    VARCHAR(150) DEFAULT NULL,
    `health_condition`      TEXT,
    `description`           TEXT,
    `adoption_requirements` TEXT,
    `rescue_date`           DATE DEFAULT NULL,
    `status`                ENUM('available','pending_adoption','adopted') NOT NULL DEFAULT 'available',
    `image`                 VARCHAR(255) DEFAULT NULL,
    `created_at`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pet_images` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `pet_id`     INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `pet_id` (`pet_id`),
    CONSTRAINT `pet_images_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Adoption applications ───────────────────────────────────
CREATE TABLE `adoption_applications` (
    `id`                  INT NOT NULL AUTO_INCREMENT,
    `pet_id`              INT NOT NULL,
    `user_id`             INT NOT NULL,
    `full_name`           VARCHAR(150) NOT NULL,
    `contact_number`      VARCHAR(512) NOT NULL,
    `email`               VARCHAR(512) NOT NULL,
    `address`             TEXT,
    `occupation`          VARCHAR(100) NOT NULL,
    `reason_for_adoption` TEXT,
    `home_type`           VARCHAR(80) DEFAULT NULL,
    `existing_pets`       ENUM('yes','no') NOT NULL,
    `agreement`           TINYINT(1) NOT NULL DEFAULT 0,
    `status`              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `schedule_date`       DATE DEFAULT NULL,
    `schedule_time`       TIME DEFAULT NULL,
    `created_at`          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `pet_id` (`pet_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `adoption_applications_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `adoption_applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications ───────────────────────────────────────────
CREATE TABLE `notifications` (
    `id`                INT NOT NULL AUTO_INCREMENT,
    `application_id`    INT DEFAULT NULL,
    `user_id`           INT DEFAULT NULL,
    `notification_type` VARCHAR(32) NOT NULL DEFAULT 'adoption',
    `message`           VARCHAR(255) NOT NULL,
    `link_url`          VARCHAR(512) DEFAULT NULL,
    `is_read`           TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `application_id` (`application_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `adoption_applications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── OTP tokens ──────────────────────────────────────────────
CREATE TABLE `otp_tokens` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(150) NOT NULL,
    `otp_code`   CHAR(6) NOT NULL,
    `purpose`    ENUM(
        'registration',
        'login',
        'login_security',
        'password_reset',
        'google_link',
        'profile_update',
        'email_change_current',
        'email_change_new',
        'staff_invite'
    ) NOT NULL DEFAULT 'registration',
    `expires_at` DATETIME NOT NULL,
    `used`       TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_otp_email_purpose` (`email`,`purpose`),
    KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Staff invites ───────────────────────────────────────────
CREATE TABLE `staff_invites` (
    `id`          INT NOT NULL AUTO_INCREMENT,
    `email`       VARCHAR(150) NOT NULL,
    `token`       VARCHAR(64) NOT NULL,
    `permissions` LONGTEXT DEFAULT NULL,
    `expires_at`  DATETIME NOT NULL,
    `used`        TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`  INT DEFAULT NULL,
    `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token` (`token`),
    KEY `idx_invite_token` (`token`),
    KEY `idx_invite_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed: default admin ─────────────────────────────────────
INSERT INTO `users` (
    `id`, `full_name`, `email`, `password`, `role`, `email_verified`, `auth_provider`
) VALUES (
    1,
    'System Administrator',
    'anthony.domasig@evsu.edu.ph',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1,
    'local'
);

CREATE TABLE `login_attempts` (
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


CREATE TABLE `device_fingerprints` (
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


CREATE TABLE `trusted_devices` (
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


CREATE TABLE `security_events` (
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


CREATE TABLE `user_sessions` (
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


CREATE TABLE `blocked_ips` (
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


CREATE TABLE `account_recovery_records` (
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

-- BantayPurrPaws — Multi-step registration email verification
-- Run: mysql -u root bantaypurrpaws < sql/registration_verification_migration.sql

CREATE TABLE `registration_verifications` (
    `id`                           INT NOT NULL AUTO_INCREMENT,
    `registration_token`           VARCHAR(64) NOT NULL,
    `full_name`                    VARCHAR(150) NOT NULL,
    `email`                        VARCHAR(255) NOT NULL,
    `email_hash`                   VARCHAR(64) NOT NULL,
    `password_hash`                VARCHAR(255) NOT NULL,
    `verification_token`           VARCHAR(64) NOT NULL,
    `verification_token_expiry`    DATETIME NOT NULL,
    `email_link_verified`          TINYINT(1) NOT NULL DEFAULT 0,
    `email_link_verified_at`       DATETIME DEFAULT NULL,
    `verification_code`            CHAR(6) DEFAULT NULL,
    `verification_code_expiry`     DATETIME DEFAULT NULL,
    `verification_attempts`        TINYINT NOT NULL DEFAULT 0,
    `last_verification_email_sent` DATETIME DEFAULT NULL,
    `last_code_sent`               DATETIME DEFAULT NULL,
    `verification_status`          ENUM(
        'pending_link',
        'link_verified',
        'pending_code',
        'activated',
        'expired',
        'locked'
    ) NOT NULL DEFAULT 'pending_link',
    `ip_address`                   VARCHAR(45) DEFAULT NULL,
    `created_at`                   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_registration_token` (`registration_token`),
    UNIQUE KEY `uk_verification_token` (`verification_token`),
    KEY `idx_reg_email_hash` (`email_hash`),
    KEY `idx_reg_status` (`verification_status`),
    KEY `idx_reg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
