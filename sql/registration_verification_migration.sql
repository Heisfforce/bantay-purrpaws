-- BantayPurrPaws — Multi-step registration email verification
-- Run: mysql -u root bantaypurrpaws < sql/registration_verification_migration.sql

USE bantaypurrpaws;

CREATE TABLE IF NOT EXISTS `registration_verifications` (
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
