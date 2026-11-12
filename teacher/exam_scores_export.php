<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('teacher', 'admin');

$uid = (int)current_user()['id'];
$examId = get_int('exam_id');
$ownerFilter = current_user()['role'] === 'admin' ? '' : ' AND e.created_by=?';
$params = current_user()['role'] === 'admin' ? [$examId] : [$examId, $uid];

$stmt = db()->prepare("SELECT e.id,e.title,e.total_score FROM exams e WHERE e.id=?$ownerFilter");
$stmt->execute($params);
$exam = $stmt->fetch();
if (!$exam) exit('گزارش پیدا نشد یا دسترسی ندارید.');

// بهترین تلاش ثبت‌شدهٔ هر دانش‌آموز؛ تلاش لغوشده و نیمه‌کاره صادر نمی‌شود.
$stmt = db()->prepare("SELECT a.student_id,u.full_name,a.score
    FROM attempts a
    JOIN users u ON u.id=a.student_id
    WHERE a.exam_id=? AND a.revoked_at IS NULL AND a.status IN ('submitted','reviewed')
    ORDER BY a.student_id ASC, COALESCE(a.score,0) DESC, a.id DESC");
$stmt->execute([$examId]);
$rows = [];
$seen = [];
foreach ($stmt->fetchAll() as $row) {
    $studentId = (int)$row['student_id'];
    if (isset($seen[$studentId])) continue;
    $seen[$studentId] = true;
    $rows[] = [$row['full_name'], number_format((float)($row['score'] ?? 0), 2, '.', '')];
}
usort($rows, static fn($a, $b) => strcmp((string)$a[0], (string)$b[0]));

$filename = 'exam_scores_' . $examId . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$maxScore = rtrim(rtrim(number_format((float)$exam['total_score'], 2, '.', ''), '0'), '.');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
  table { border-collapse: collapse; direction: rtl; }
  th, td { border: 1px solid #777; padding: 5px 10px; }
  th { background: #e8e8e8; font-weight: bold; }
  td.score { mso-number-format: "0.00"; text-align: center; }
</style>
</head>
<body>
<table dir="rtl">
  <thead>
    <tr><th>نام دانش‌آموز</th><th>نمره از <?= $escape($maxScore) ?></th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as [$name, $score]): ?>
    <tr><td><?= $escape($name) ?></td><td class="score"><?= $escape($score) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
