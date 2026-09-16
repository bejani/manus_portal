-- Add an optional module/chapter to exams so the question bank can be scoped correctly.
-- Run once on existing installations.
SET @has_exam_chapter := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'exams'
    AND COLUMN_NAME = 'chapter'
);
SET @add_exam_chapter_sql := IF(
  @has_exam_chapter = 0,
  'ALTER TABLE exams ADD COLUMN chapter VARCHAR(150) NULL AFTER subject_id, ADD INDEX idx_exams_subject_chapter (subject_id, chapter)',
  'SELECT 1'
);
PREPARE add_exam_chapter_stmt FROM @add_exam_chapter_sql;
EXECUTE add_exam_chapter_stmt;
DEALLOCATE PREPARE add_exam_chapter_stmt;
