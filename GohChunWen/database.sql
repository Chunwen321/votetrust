-- Matches the shared election_db.sql supplied by the team.
-- This module owns only the existing singular `vote` table.
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
    CONSTRAINT `vote_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `election` (`id`) ON DELETE CASCADE,
    CONSTRAINT `vote_ibfk_2` FOREIGN KEY (`voter_id`) REFERENCES `voter` (`id`) ON DELETE CASCADE,
    CONSTRAINT `vote_ibfk_3` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
