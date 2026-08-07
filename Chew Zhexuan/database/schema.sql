-- VoteTrust User, Voter, and Admin Module schema extension.
-- Apply this after the team's election_db.sql. It does not alter or replace
-- other members' tables, columns, or source files.
--
-- The shared `voter` table stores the organiser's election-specific enrolment:
-- one row per (`user_id`, `election_id`).  It is not a generic user role table.

ALTER TABLE `user`
    ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(100) NULL AFTER `username`,
    ADD COLUMN IF NOT EXISTS `ic_number` VARCHAR(14) NULL AFTER `full_name`,
    ADD COLUMN IF NOT EXISTS `email` VARCHAR(190) NULL AFTER `password_hash`,
    ADD COLUMN IF NOT EXISTS `account_status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE' AFTER `is_admin`,
    ADD COLUMN IF NOT EXISTS `failed_login_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `account_status`,
    ADD COLUMN IF NOT EXISTS `account_disabled_at` DATETIME NULL AFTER `failed_login_attempts`,
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL AFTER `created_at`;

-- This definition is used for a new database.  If your current `voter` table
-- contains only `id`, run database/migrate_voter_table.sql once instead.
CREATE TABLE IF NOT EXISTS `voter` (
    `user_id` INT(11) NOT NULL,
    `election_id` INT(11) NOT NULL,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED') NOT NULL DEFAULT 'APPROVED',
    `added_by` INT(11) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `election_id`),
    KEY `voter_election_status` (`election_id`, `status`),
    KEY `voter_added_by` (`added_by`),
    CONSTRAINT `voter_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
    CONSTRAINT `voter_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `election` (`id`) ON DELETE CASCADE,
    CONSTRAINT `voter_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE `user`
SET `account_status` = 'ACTIVE'
WHERE `account_status` IS NULL OR `account_status` = '';
