<?php
/** One-time script to build sql/infinityfree_import.sql */
$base = file_get_contents(__DIR__ . '/bantaypurrpaws_import.sql');
$base = preg_replace('/CREATE DATABASE.*?;\s*/s', '', $base);
$base = preg_replace('/USE bantaypurrpaws;\s*/', '', $base);
$base = preg_replace('/SET NAMES utf8mb4;\s*SET FOREIGN_KEY_CHECKS = 0;\s*DROP TABLE.*?SET FOREIGN_KEY_CHECKS = 1;\s*/s', '', $base);

$header = <<<'HDR'
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

HDR;

$base = str_replace(
    "`staff_permissions`      LONGTEXT DEFAULT NULL,\n    `permissions_changed_at` DATETIME DEFAULT NULL,",
    "`staff_permissions`      LONGTEXT DEFAULT NULL,\n    `permissions_changed_at` DATETIME DEFAULT NULL,\n    `failed_login_count`     INT NOT NULL DEFAULT 0,\n    `account_locked_until`   DATETIME DEFAULT NULL,\n    `require_password_reset` TINYINT(1) NOT NULL DEFAULT 0,\n    `security_risk_score`    INT NOT NULL DEFAULT 0,",
    $base
);

$base = str_replace(
    "        'login',\n        'password_reset',",
    "        'login',\n        'login_security',\n        'password_reset',",
    $base
);

$security = file_get_contents(__DIR__ . '/security_auth_migration.sql');
$security = preg_replace('/^--.*$/m', '', $security);
$security = preg_replace('/USE bantaypurrpaws;\s*/', '', $security);
$security = preg_replace('/ALTER TABLE `users`.*?;/s', '', $security);
$security = preg_replace('/ALTER TABLE `otp_tokens`.*?;/s', '', $security);

$reg = file_get_contents(__DIR__ . '/registration_verification_migration.sql');
$reg = preg_replace('/USE bantaypurrpaws;\s*/', '', $reg);
$reg = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TABLE', $reg);

$security = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TABLE', $security);

$out = $header . trim($base) . "\n\n" . trim($security) . "\n\n" . trim($reg) . "\n";
file_put_contents(__DIR__ . '/infinityfree_import.sql', $out);
echo "Written " . strlen($out) . " bytes to infinityfree_import.sql\n";
