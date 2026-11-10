-- مجوزهای موردی تلاش مجدد: هر مجوز فقط یک بار مصرف می‌شود.
CREATE TABLE IF NOT EXISTS exam_retake_grants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  granted_by INT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  granted_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  used_attempt_id INT UNSIGNED NULL,
  INDEX idx_retake_grants_student_exam (student_id, exam_id, used_at),
  INDEX idx_retake_grants_exam (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
