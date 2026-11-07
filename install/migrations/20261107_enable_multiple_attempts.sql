-- فعال‌سازی چند تلاش برای هر دانش‌آموز در هر آزمون
-- این فایل را فقط یک‌بار روی دیتابیس موجود اجرا کنید.
ALTER TABLE exams
  ADD COLUMN max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER total_score,
  ADD COLUMN score_policy ENUM('latest','best') NOT NULL DEFAULT 'latest' AFTER max_attempts;

ALTER TABLE attempts
  DROP INDEX uq_attempt_exam_student,
  ADD COLUMN attempt_no SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER student_id,
  ADD COLUMN revoked_at DATETIME NULL AFTER submitted_at,
  ADD COLUMN revoked_by INT UNSIGNED NULL AFTER revoked_at,
  ADD INDEX idx_attempt_exam_student_no (exam_id, student_id, attempt_no);
