-- VoteTrust shared-schema extension for Low Wen Kai's Election module.
-- No module-specific tables are created. The submitted shared table list is
-- preserved and only columns are added to `user` and `election`.

ALTER TABLE `user`
    ADD COLUMN IF NOT EXISTS `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`;

ALTER TABLE `election`
    MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'DRAFT';

ALTER TABLE `election`
    ADD COLUMN IF NOT EXISTS `organizer_id` INT NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `eligibility_rules` TEXT NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `nomination_start` DATETIME NULL AFTER `eligibility_rules`,
    ADD COLUMN IF NOT EXISTS `nomination_end` DATETIME NULL AFTER `nomination_start`,
    ADD COLUMN IF NOT EXISTS `voting_start` DATETIME NULL AFTER `nomination_end`,
    ADD COLUMN IF NOT EXISTS `voting_end` DATETIME NULL AFTER `voting_start`,
    ADD COLUMN IF NOT EXISTS `hosting_fee` DECIMAL(10,2) NULL AFTER `voting_end`,
    ADD COLUMN IF NOT EXISTS `currency` CHAR(3) NOT NULL DEFAULT 'MYR' AFTER `hosting_fee`,
    ADD COLUMN IF NOT EXISTS `payment_reference` VARCHAR(64) NULL AFTER `payment_id`,
    ADD COLUMN IF NOT EXISTS `payment_verified_at` DATETIME NULL AFTER `payment_reference`,
    ADD COLUMN IF NOT EXISTS `payment_request_id` VARCHAR(64) NULL AFTER `payment_verified_at`,
    ADD COLUMN IF NOT EXISTS `payment_provider_status` VARCHAR(32) NULL AFTER `payment_request_id`,
    ADD COLUMN IF NOT EXISTS `payment_response_json` LONGTEXT NULL AFTER `payment_provider_status`,
    ADD COLUMN IF NOT EXISTS `approved_by` INT NULL AFTER `payment_response_json`,
    ADD COLUMN IF NOT EXISTS `approved_at` DATETIME NULL AFTER `approved_by`,
    ADD COLUMN IF NOT EXISTS `rejected_by` INT NULL AFTER `approved_at`,
    ADD COLUMN IF NOT EXISTS `rejected_at` DATETIME NULL AFTER `rejected_by`,
    ADD COLUMN IF NOT EXISTS `rejection_reason` VARCHAR(250) NULL AFTER `rejected_at`,
    ADD COLUMN IF NOT EXISTS `transition_history_json` LONGTEXT NULL AFTER `rejection_reason`,
    ADD COLUMN IF NOT EXISTS `version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `transition_history_json`,
    ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL AFTER `version`,
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL AFTER `created_at`;

-- Preserve legacy shared values while normalising them for the State factory.
UPDATE `election` SET `status` = 'DRAFT' WHERE `status` = 'Draft';
UPDATE `election` SET `status` = 'PENDING_ADMIN_APPROVAL' WHERE `status` = 'Published';

