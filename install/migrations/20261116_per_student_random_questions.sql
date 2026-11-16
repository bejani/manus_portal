-- Per-student random question sets for new exams.
-- Existing exams keep the fixed-question behavior (random_per_student = 0).

SET @has_random_per_student := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'random_per_student'
);
SET @ddl_random_per_student := IF(
  @has_random_per_student = 0,
  'ALTER TABLE exams ADD COLUMN random_per_student TINYINT(1) NOT NULL DEFAULT 0 AFTER chapter',
  'SELECT 1'
);
PREPARE stmt_random_per_student FROM @ddl_random_per_student;
EXECUTE stmt_random_per_student;
DEALLOCATE PREPARE stmt_random_per_student;

SET @has_random_easy_count := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'random_easy_count'
);
SET @ddl_random_easy_count := IF(
  @has_random_easy_count = 0,
  'ALTER TABLE exams ADD COLUMN random_easy_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER random_per_student',
  'SELECT 1'
);
PREPARE stmt_random_easy_count FROM @ddl_random_easy_count;
EXECUTE stmt_random_easy_count;
DEALLOCATE PREPARE stmt_random_easy_count;

SET @has_random_medium_count := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'random_medium_count'
);
SET @ddl_random_medium_count := IF(
  @has_random_medium_count = 0,
  'ALTER TABLE exams ADD COLUMN random_medium_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER random_easy_count',
  'SELECT 1'
);
PREPARE stmt_random_medium_count FROM @ddl_random_medium_count;
EXECUTE stmt_random_medium_count;
DEALLOCATE PREPARE stmt_random_medium_count;

SET @has_random_hard_count := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'random_hard_count'
);
SET @ddl_random_hard_count := IF(
  @has_random_hard_count = 0,
  'ALTER TABLE exams ADD COLUMN random_hard_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER random_medium_count',
  'SELECT 1'
);
PREPARE stmt_random_hard_count FROM @ddl_random_hard_count;
EXECUTE stmt_random_hard_count;
DEALLOCATE PREPARE stmt_random_hard_count;

-- Question snapshots need the same bank metadata and media fields as the bank.
SET @has_question_chapter := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='chapter');
SET @ddl_question_chapter := IF(@has_question_chapter=0,'ALTER TABLE questions ADD COLUMN chapter VARCHAR(150) NULL AFTER question_text','SELECT 1');
PREPARE stmt_question_chapter FROM @ddl_question_chapter; EXECUTE stmt_question_chapter; DEALLOCATE PREPARE stmt_question_chapter;

SET @has_question_image_path := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='image_path');
SET @ddl_question_image_path := IF(@has_question_image_path=0,'ALTER TABLE questions ADD COLUMN image_path VARCHAR(500) NULL AFTER chapter','SELECT 1');
PREPARE stmt_question_image_path FROM @ddl_question_image_path; EXECUTE stmt_question_image_path; DEALLOCATE PREPARE stmt_question_image_path;

SET @has_question_difficulty := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='difficulty');
SET @ddl_question_difficulty := IF(@has_question_difficulty=0,'ALTER TABLE questions ADD COLUMN difficulty ENUM(''easy'',''medium'',''hard'') NOT NULL DEFAULT ''medium'' AFTER sort_order','SELECT 1');
PREPARE stmt_question_difficulty FROM @ddl_question_difficulty; EXECUTE stmt_question_difficulty; DEALLOCATE PREPARE stmt_question_difficulty;

SET @has_question_tags := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='tags');
SET @ddl_question_tags := IF(@has_question_tags=0,'ALTER TABLE questions ADD COLUMN tags TEXT NULL AFTER difficulty','SELECT 1');
PREPARE stmt_question_tags FROM @ddl_question_tags; EXECUTE stmt_question_tags; DEALLOCATE PREPARE stmt_question_tags;

SET @has_question_reference_answer := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='reference_answer');
SET @ddl_question_reference_answer := IF(@has_question_reference_answer=0,'ALTER TABLE questions ADD COLUMN reference_answer TEXT NULL AFTER feedback','SELECT 1');
PREPARE stmt_question_reference_answer FROM @ddl_question_reference_answer; EXECUTE stmt_question_reference_answer; DEALLOCATE PREPARE stmt_question_reference_answer;

SET @has_question_rubric := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='rubric');
SET @ddl_question_rubric := IF(@has_question_rubric=0,'ALTER TABLE questions ADD COLUMN rubric TEXT NULL AFTER reference_answer','SELECT 1');
PREPARE stmt_question_rubric FROM @ddl_question_rubric; EXECUTE stmt_question_rubric; DEALLOCATE PREPARE stmt_question_rubric;

SET @has_option_image_path := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='question_options' AND COLUMN_NAME='image_path');
SET @ddl_option_image_path := IF(@has_option_image_path=0,'ALTER TABLE question_options ADD COLUMN image_path VARCHAR(500) NULL AFTER option_text','SELECT 1');
PREPARE stmt_option_image_path FROM @ddl_option_image_path; EXECUTE stmt_option_image_path; DEALLOCATE PREPARE stmt_option_image_path;
