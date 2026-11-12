-- بازتنظیم نمرهٔ تلاش‌های ثبت‌شده بر اساس مجموع نمرهٔ سؤال‌های همان تلاش.
-- نمرهٔ ذخیره‌شده در attempts.score به مقیاس exams.total_score تبدیل می‌شود؛
-- درصد نیز از نسبت امتیاز خام پاسخ‌ها به حداکثر امتیاز خام محاسبه می‌شود.
UPDATE attempts a
JOIN exams e ON e.id = a.exam_id
JOIN (
  SELECT
    aq.attempt_id,
    SUM(q.score) AS raw_max_score,
    SUM(COALESCE(ans.awarded_score, 0)) AS raw_score
  FROM attempt_questions aq
  JOIN questions q ON q.id = aq.question_id
  LEFT JOIN answers ans
    ON ans.attempt_id = aq.attempt_id
   AND ans.question_id = aq.question_id
  GROUP BY aq.attempt_id
) attempt_totals ON attempt_totals.attempt_id = a.id
SET
  a.score = CASE
    WHEN attempt_totals.raw_max_score > 0
      THEN ROUND(attempt_totals.raw_score / attempt_totals.raw_max_score * e.total_score, 2)
    ELSE 0
  END,
  a.percentage = CASE
    WHEN attempt_totals.raw_max_score > 0
      THEN ROUND(attempt_totals.raw_score / attempt_totals.raw_max_score * 100, 2)
    ELSE 0
  END
WHERE a.status IN ('submitted', 'reviewed');
