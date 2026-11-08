-- این فایل را حتی اگر بخشی از مهاجرت قبلی اجرا شده باشد، می‌توان اجرا کرد.
CREATE TABLE IF NOT EXISTS attempt_questions (
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (attempt_id, question_id),
  INDEX idx_attempt_questions_question (question_id),
  INDEX idx_attempt_questions_order (attempt_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- اتصال سؤال‌های موجود به تلاش‌های قبلی؛ در صورت وجود دادهٔ تکراری نادیده گرفته می‌شود.
INSERT IGNORE INTO attempt_questions (attempt_id, question_id, sort_order)
SELECT a.id, q.id, q.sort_order
FROM attempts a
JOIN questions q ON q.exam_id = a.exam_id
LEFT JOIN attempt_questions aq
  ON aq.attempt_id = a.id AND aq.question_id = q.id
WHERE aq.attempt_id IS NULL;
