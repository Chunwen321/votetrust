-- VoteTrust integrated Election + Voting development database.
--
-- This script is intentionally non-destructive: it does not DROP tables.
-- It can initialise a clean `election_db` or restore missing shared tables.
-- Existing seed rows with the same primary keys are normalised for local demo use.

CREATE DATABASE IF NOT EXISTS `election_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `election_db`;

CREATE TABLE IF NOT EXISTS `user` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `organizer` (
    `id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `organizer_ibfk_1`
        FOREIGN KEY (`id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `candidate` (
    `id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `candidate_ibfk_1`
        FOREIGN KEY (`id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `voter` (
    `id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `voter_ibfk_1`
        FOREIGN KEY (`id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payment` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `amount` DECIMAL(10,2) NOT NULL,
    `status` ENUM('Pending','Completed','Failed') NOT NULL DEFAULT 'Pending',
    `organizer_id` INT(11) NOT NULL,
    `candidate_id` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `organizer_id` (`organizer_id`),
    KEY `candidate_id` (`candidate_id`),
    CONSTRAINT `payment_ibfk_1`
        FOREIGN KEY (`organizer_id`) REFERENCES `organizer` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payment_ibfk_2`
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `election` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `organizer_id` INT(11) DEFAULT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `eligibility_rules` TEXT DEFAULT NULL,
    `nomination_start` DATETIME DEFAULT NULL,
    `nomination_end` DATETIME DEFAULT NULL,
    `voting_start` DATETIME DEFAULT NULL,
    `voting_end` DATETIME DEFAULT NULL,
    `hosting_fee` DECIMAL(10,2) DEFAULT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'MYR',
    `status` VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
    `payment_id` INT(11) DEFAULT NULL,
    `payment_reference` VARCHAR(64) DEFAULT NULL,
    `payment_verified_at` DATETIME DEFAULT NULL,
    `payment_request_id` VARCHAR(64) DEFAULT NULL,
    `payment_provider_status` VARCHAR(32) DEFAULT NULL,
    `payment_response_json` LONGTEXT DEFAULT NULL,
    `approved_by` INT(11) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_by` INT(11) DEFAULT NULL,
    `rejected_at` DATETIME DEFAULT NULL,
    `rejection_reason` VARCHAR(250) DEFAULT NULL,
    `transition_history_json` LONGTEXT DEFAULT NULL,
    `version` INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `payment_id` (`payment_id`),
    KEY `organizer_id` (`organizer_id`),
    CONSTRAINT `election_ibfk_1`
        FOREIGN KEY (`payment_id`) REFERENCES `payment` (`id`) ON DELETE SET NULL,
    CONSTRAINT `election_ibfk_2`
        FOREIGN KEY (`organizer_id`) REFERENCES `organizer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `candidacy` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `candidate_id` INT(11) NOT NULL,
    `election_id` INT(11) NOT NULL,
    `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_candidate_per_election` (`candidate_id`, `election_id`),
    KEY `election_id` (`election_id`),
    CONSTRAINT `candidacy_ibfk_1`
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE CASCADE,
    CONSTRAINT `candidacy_ibfk_2`
        FOREIGN KEY (`election_id`) REFERENCES `election` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `vote` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `election_id` INT(11) NOT NULL,
    `voter_id` INT(11) NOT NULL,
    `candidate_id` INT(11) NOT NULL,
    `cast_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_voter_per_election` (`voter_id`, `election_id`),
    KEY `election_id` (`election_id`),
    KEY `candidate_id` (`candidate_id`),
    CONSTRAINT `vote_ibfk_1`
        FOREIGN KEY (`election_id`) REFERENCES `election` (`id`) ON DELETE CASCADE,
    CONSTRAINT `vote_ibfk_2`
        FOREIGN KEY (`voter_id`) REFERENCES `voter` (`id`) ON DELETE CASCADE,
    CONSTRAINT `vote_ibfk_3`
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed accounts. All six voter accounts use: VoteTrustVoter!2026
INSERT INTO `user` (`id`, `username`, `password_hash`, `is_admin`) VALUES
    (1, 'OrganizerAdmin', '$2y$10$BuXfE/D.Y42pbx4pXIJVO..xzueBRmkPlDV1yTBZ3fLDaVS/BqKX2', 0),
    (2, 'VoterJohn', '$2y$10$BcX4jJAAZW4Wi8MRA8RQ6.OnzQeRqtU6DE8.NwsDI67GqHlKcBHBy', 0),
    (3, 'LowWenKaiOrganizer', '$2y$10$BuXfE/D.Y42pbx4pXIJVO..xzueBRmkPlDV1yTBZ3fLDaVS/BqKX2', 0),
    (4, 'SystemAdmin', '$2y$10$dkfEJdLQ.35/BpGWSwAIEewbW.WA3IbyNt6IFsbZj7GLdsvK9o2cS', 1),
    (5, 'CandidateAisha', '$2y$10$K1.bx8mFkX9fno9FDdRmmudXW4m2Ut6hVwOg4uKxuQ8MDpettDacq', 0),
    (6, 'CandidateDaniel', '$2y$10$KNdVVvp4Xc9lb9sUWcdieOk1OefAQIaevP2SgB55wyFXFiMe.mFAi', 0)
ON DUPLICATE KEY UPDATE
    `username` = VALUES(`username`),
    `password_hash` = VALUES(`password_hash`),
    `is_admin` = VALUES(`is_admin`);

-- Additional voters are keyed by username rather than fixed IDs so this file
-- can be imported into a database where other teammates already added users.
INSERT INTO `user` (`username`, `password_hash`, `is_admin`) VALUES
    ('VoterAmina', '$2y$10$BcX4jJAAZW4Wi8MRA8RQ6.OnzQeRqtU6DE8.NwsDI67GqHlKcBHBy', 0),
    ('VoterBenjamin', '$2y$10$BcX4jJAAZW4Wi8MRA8RQ6.OnzQeRqtU6DE8.NwsDI67GqHlKcBHBy', 0),
    ('VoterChen', '$2y$10$BcX4jJAAZW4Wi8MRA8RQ6.OnzQeRqtU6DE8.NwsDI67GqHlKcBHBy', 0),
    ('VoterDevi', '$2y$10$BcX4jJAAZW4Wi8MRA8RQ6.OnzQeRqtU6DE8.NwsDI67GqHlKcBHBy', 0),
    ('VoterFarah', '$2y$10$BcX4jJAAZW4Wi8MRA8RQ6.OnzQeRqtU6DE8.NwsDI67GqHlKcBHBy', 0)
ON DUPLICATE KEY UPDATE
    `password_hash` = VALUES(`password_hash`),
    `is_admin` = VALUES(`is_admin`);

INSERT IGNORE INTO `organizer` (`id`) VALUES (1), (3);
INSERT IGNORE INTO `candidate` (`id`) VALUES (5), (6);
INSERT IGNORE INTO `voter` (`id`)
SELECT `id` FROM `user`
WHERE `username` IN ('VoterJohn', 'VoterAmina', 'VoterBenjamin', 'VoterChen', 'VoterDevi', 'VoterFarah');

INSERT INTO `payment` (`id`, `amount`, `status`, `organizer_id`, `candidate_id`) VALUES
    (100, 100.00, 'Completed', 3, NULL)
ON DUPLICATE KEY UPDATE
    `amount` = VALUES(`amount`),
    `status` = VALUES(`status`),
    `organizer_id` = VALUES(`organizer_id`),
    `candidate_id` = VALUES(`candidate_id`);

-- A closed election supplies stable tally data. Low Wen Kai's setup command
-- creates a separate, date-relative election for the interactive demo.
INSERT INTO `election` (
    `id`, `organizer_id`, `title`, `description`, `eligibility_rules`,
    `nomination_start`, `nomination_end`, `voting_start`, `voting_end`,
    `hosting_fee`, `currency`, `status`, `payment_id`, `payment_reference`,
    `payment_verified_at`, `payment_request_id`, `payment_provider_status`,
    `payment_response_json`, `approved_by`, `approved_at`,
    `transition_history_json`, `version`, `created_at`, `updated_at`
) VALUES (
    1,
    3,
    'VoteTrust Student Council Election 2026',
    'A completed election containing stable sample votes for tally demonstrations.',
    'Active students verified by the User Module may participate.',
    '2026-07-30 09:00:00',
    '2026-08-01 09:00:00',
    '2026-08-01 10:00:00',
    '2026-08-03 18:00:00',
    100.00,
    'MYR',
    'CLOSED',
    100,
    'VT-HOST-PAID-001',
    '2026-08-01 08:30:00',
    'SEED-PAYMENT-0001',
    'CONFIRMED',
    '{"status":"S","message":"Seed payment confirmed."}',
    4,
    '2026-08-01 08:45:00',
    '[]',
    1,
    '2026-07-29 12:00:00',
    '2026-08-03 18:00:00'
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `eligibility_rules` = VALUES(`eligibility_rules`),
    `nomination_start` = VALUES(`nomination_start`),
    `nomination_end` = VALUES(`nomination_end`),
    `voting_start` = VALUES(`voting_start`),
    `voting_end` = VALUES(`voting_end`),
    `hosting_fee` = VALUES(`hosting_fee`),
    `currency` = VALUES(`currency`),
    `status` = VALUES(`status`),
    `payment_id` = VALUES(`payment_id`),
    `payment_reference` = VALUES(`payment_reference`),
    `payment_verified_at` = VALUES(`payment_verified_at`),
    `payment_request_id` = VALUES(`payment_request_id`),
    `payment_provider_status` = VALUES(`payment_provider_status`),
    `payment_response_json` = VALUES(`payment_response_json`),
    `approved_by` = VALUES(`approved_by`),
    `approved_at` = VALUES(`approved_at`),
    `transition_history_json` = VALUES(`transition_history_json`),
    `updated_at` = VALUES(`updated_at`);

INSERT INTO `candidacy` (`candidate_id`, `election_id`, `status`, `registered_at`) VALUES
    (5, 1, 'Approved', '2026-07-31 10:00:00'),
    (6, 1, 'Approved', '2026-07-31 10:05:00')
ON DUPLICATE KEY UPDATE
    `status` = VALUES(`status`),
    `registered_at` = VALUES(`registered_at`);

-- Sample votes are restricted to the CLOSED seed election. Each voter remains
-- able to test any new election created by Low Wen Kai's setup command.
INSERT INTO `vote` (`election_id`, `voter_id`, `candidate_id`, `cast_at`)
SELECT 1, u.`id`, seeds.`candidate_id`, seeds.`cast_at`
FROM (
    SELECT 'VoterJohn' AS `username`, 5 AS `candidate_id`, '2026-08-02 09:00:00' AS `cast_at`
    UNION ALL SELECT 'VoterAmina', 5, '2026-08-02 09:05:00'
    UNION ALL SELECT 'VoterBenjamin', 6, '2026-08-02 09:10:00'
    UNION ALL SELECT 'VoterChen', 5, '2026-08-02 09:15:00'
    UNION ALL SELECT 'VoterDevi', 6, '2026-08-02 09:20:00'
    UNION ALL SELECT 'VoterFarah', 5, '2026-08-02 09:25:00'
) AS seeds
INNER JOIN `user` u ON u.`username` = seeds.`username`
ON DUPLICATE KEY UPDATE
    `candidate_id` = VALUES(`candidate_id`),
    `cast_at` = VALUES(`cast_at`);

ALTER TABLE `payment` AUTO_INCREMENT = 101;
ALTER TABLE `election` AUTO_INCREMENT = 2;
ALTER TABLE `candidacy` AUTO_INCREMENT = 3;

ALTER TABLE `candidate`
    ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `email` VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS `phone_number` VARCHAR(30) NULL;

-- Wong Shun Bin: Candidate Registration module data.
ALTER TABLE `candidacy`
    ADD COLUMN IF NOT EXISTS `position_sought` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `manifesto` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `supporting_details` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS `profile_photo_mime` VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS `payment_id` INT(11) NULL;

CREATE TABLE IF NOT EXISTS `candidate_document` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `candidacy_id` INT(11) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(80) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `uploaded_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `stored_name` (`stored_name`),
    CONSTRAINT `candidate_document_ibfk_1` FOREIGN KEY (`candidacy_id`) REFERENCES `candidacy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Voting module can use this view after organiser approval.
CREATE OR REPLACE VIEW `eligible_candidate` AS
SELECT * FROM `candidacy` WHERE `status` = 'Approved';

DROP TRIGGER IF EXISTS `candidate_payment_approved`;
CREATE TRIGGER `candidate_payment_approved`
AFTER UPDATE ON `payment` FOR EACH ROW
UPDATE `candidacy`
SET `status` = 'Approved'
WHERE `payment_id` = NEW.`id` AND NEW.`status` = 'Completed';
