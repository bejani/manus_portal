-- سقف یک فرصت مجدد برای هر دانش‌آموز در هر آزمون.
-- مجوزهای ثبت‌شدهٔ قبلی (استفاده‌شده یا استفاده‌نشده) نیز به‌عنوان فرصت مصرف‌شده لحاظ می‌شوند.
CREATE TABLE IF NOT EXISTS exam_retake_limits (
  exam_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (exam_id, student_id),
  INDEX idx_retake_limits_student (student_id, exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO exam_retake_limits (exam_id, student_id)
SELECT exam_id, student_id
FROM exam_retake_grants
GROUP BY exam_id, student_id;
