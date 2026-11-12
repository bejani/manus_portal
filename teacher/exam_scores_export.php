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

// بهترین تلاش ثبت‌شدهٔ هر دانش‌آموز؛ revoked و تلاش‌های نیمه‌کاره صادر نمی‌شوند.
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
echo "\xEF\xBB\xBF";
$cell = static fn($value) => '"' . str_replace('"', '""', (string)$value) . '"';
echo $cell('نام دانش‌آموز') . "\t" . $cell('نمره از ' . rtrim(rtrim(number_format((float)$exam['total_score'], 2, '.', ''), '0'), '.')) . "\n";
foreach ($rows as [$name, $score]) {
    echo $cell($name) . "\t" . $score . "\n";
}
exit;
