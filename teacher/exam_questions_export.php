<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('teacher', 'admin');

$uid = (int)current_user()['id'];
$examId = get_int('exam_id');
$ownerFilter = current_user()['role'] === 'admin' ? '' : ' AND e.created_by=?';
$params = current_user()['role'] === 'admin' ? [$examId] : [$examId, $uid];

$examStmt = db()->prepare("SELECT e.id,e.title FROM exams e WHERE e.id=?$ownerFilter");
$examStmt->execute($params);
$exam = $examStmt->fetch();
if (!$exam) {
    http_response_code(403);
    exit('گزارش پیدا نشد یا دسترسی ندارید.');
}

$stmt = db()->prepare('SELECT q.question_text,q.chapter,q.question_type,COUNT(a.id) responses,SUM(CASE WHEN a.is_correct=1 THEN 1 ELSE 0 END) correct_count FROM questions q LEFT JOIN answers a ON a.question_id=q.id LEFT JOIN attempts at ON at.id=a.attempt_id AND at.exam_id=? WHERE q.exam_id=? GROUP BY q.id,q.question_text,q.chapter,q.question_type,q.sort_order ORDER BY q.sort_order,q.id');
$stmt->execute([$examId, $examId]);
$questions = $stmt->fetchAll();
$labels = ['mcq'=>'چهارگزینه‌ای','true_false'=>'صحیح/غلط','short'=>'پاسخ کوتاه'];

$filename = 'exam_question_analysis_' . $examId . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
  table { border-collapse: collapse; direction: rtl; }
  th, td { border: 1px solid #777; padding: 5px 10px; vertical-align: top; }
  th { background: #e8e8e8; font-weight: bold; }
  td.number, td.percent { mso-number-format: "0.00"; text-align: center; }
  td.prompt { mso-number-format: "\@"; min-width: 300px; }
</style>
</head>
<body>
<table dir="rtl">
  <thead>
    <tr><th>شماره</th><th>نوع</th><th>فصل</th><th>پرسش</th><th>تعداد پاسخ</th><th>درصد موفقیت</th></tr>
  </thead>
  <tbody>
  <?php foreach ($questions as $index => $question):
      $responses = (int)$question['responses'];
      $successRate = $responses > 0 ? ((int)$question['correct_count'] / $responses * 100) : 0;
  ?>
    <tr>
      <td class="number"><?= $index + 1 ?></td>
      <td><?= $escape($labels[$question['question_type']] ?? $question['question_type']) ?></td>
      <td><?= $escape($question['chapter'] ?? '—') ?></td>
      <td class="prompt"><?= $escape($question['question_text']) ?></td>
      <td class="number"><?= $responses ?></td>
      <td class="percent"><?= number_format($successRate, 2, '.', '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
