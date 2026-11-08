

-- ثبت سؤال‌های آزمون‌های قبلی در مجموعه تلاش‌های موجود
INSERT INTO attempt_questions (attempt_id, question_id, sort_order)
SELECT a.id, q.id, q.sort_order
FROM attempts a
JOIN questions q ON q.exam_id=a.exam_id
LEFT JOIN attempt_questions aq ON aq.attempt_id=a.id AND aq.question_id=q.id
WHERE aq.attempt_id IS NULL;
