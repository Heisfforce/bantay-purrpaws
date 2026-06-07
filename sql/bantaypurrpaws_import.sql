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

CREATE DATABASE IF NOT EXISTS bantaypurrpaws
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bantaypurrpaws;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
