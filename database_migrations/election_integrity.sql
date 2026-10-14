-- Run once on an existing NACOS database after taking a full backup.
-- This preserves historical vote rows by replacing vote-table cascades with RESTRICT.
-- MySQL / MariaDB: execute the entire file, including DELIMITER directives.

SET @has_eligible_voter_count = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'elections'
    AND COLUMN_NAME = 'eligible_voter_count'
);
SET @add_eligible_voter_count_sql = IF(
  @has_eligible_voter_count = 0,
  'ALTER TABLE elections ADD COLUMN eligible_voter_count INT UNSIGNED DEFAULT NULL',
  'SELECT 1'
);
PREPARE add_eligible_voter_count_stmt FROM @add_eligible_voter_count_sql;
EXECUTE add_eligible_voter_count_stmt;
DEALLOCATE PREPARE add_eligible_voter_count_stmt;

DELIMITER //
DROP PROCEDURE IF EXISTS migrate_vote_foreign_keys_to_restrict//
CREATE PROCEDURE migrate_vote_foreign_keys_to_restrict()
BEGIN
  DECLARE finished INT DEFAULT 0;
  DECLARE fk_name VARCHAR(64);
  DECLARE fk_cursor CURSOR FOR
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'votes'
      AND REFERENCED_TABLE_NAME IS NOT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

  OPEN fk_cursor;
  drop_loop: LOOP
    FETCH fk_cursor INTO fk_name;
    IF finished = 1 THEN
      LEAVE drop_loop;
    END IF;
    SET @drop_fk_sql = CONCAT('ALTER TABLE `votes` DROP FOREIGN KEY `', REPLACE(fk_name, '`', '``'), '`');
    PREPARE drop_fk_stmt FROM @drop_fk_sql;
    EXECUTE drop_fk_stmt;
    DEALLOCATE PREPARE drop_fk_stmt;
  END LOOP;
  CLOSE fk_cursor;

  ALTER TABLE votes
    ADD CONSTRAINT fk_votes_election_restrict FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_votes_position_restrict FOREIGN KEY (position_id) REFERENCES election_positions(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_votes_candidate_restrict FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_votes_student_restrict FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT;
END//

CALL migrate_vote_foreign_keys_to_restrict()//
DROP PROCEDURE migrate_vote_foreign_keys_to_restrict//
DELIMITER ;
