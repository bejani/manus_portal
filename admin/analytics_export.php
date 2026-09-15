<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('admin');
$classId = get_int('class_id');
$examId = get_int('exam_id');
$where = ['a.status IN ("submitted","reviewed")', 'a.revoked_at IS NULL'];
$params = [];
if ($classId > 0) { $where[] = 'e.class_id=?'; $params[] = $classId; }
if ($examId > 0) { $where[] = 'a.exam_id=?'; $params[] = $examId; }
$sql = 'SELECT e.title exam_title,c.title class_title,s.title subject_title,COUNT(a.id) attempts,COUNT(DISTINCT a.student_id) students,COALESCE(AVG(a.percentage),0) average_percentage,COALESCE(MAX(a.percentage),0) best_percentage FROM attempts a JOIN exams e ON e.id=a.exam_id LEFT JOIN classes c ON c.id=e.class_id LEFT JOIN subjects s ON s.id=e.subject_id WHERE '.implode(' AND ',$where).' GROUP BY e.id,e.title,c.title,s.title ORDER BY e.id DESC';
$stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
function analytics_xls_cell($value): string { return '"'.str_replace('"','""',(string)$value).'"'; }
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="admin_analytics_'.date('Ymd_His').'.xls"');
header('Cache-Control: no-store');
echo "\xEF\xBB\xBF";
echo implode("\t", array_map('analytics_xls_cell', ['آزمون','درس','کلاس','دفعات شرکت','تعداد دانش‌آموز','میانگین درصد','بهترین درصد']))."\n";
foreach ($rows as $row) echo implode("\t", array_map('analytics_xls_cell', [$row['exam_title'],$row['subject_title']??'',$row['class_title']??'',(int)$row['attempts'],(int)$row['students'],format_score($row['average_percentage']).'%',format_score($row['best_percentage']).'%']))."\n";
exit;
