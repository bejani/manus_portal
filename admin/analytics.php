<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('admin');

$classId = get_int('class_id');
$subjectId = get_int('subject_id');
$examId = get_int('exam_id');
$examSortInput = $_GET['exam_sort'] ?? 'newest';
$examSort = is_string($examSortInput) ? $examSortInput : 'newest';
$sortOptions = [
    'newest' => 'e.id DESC',
    'title' => 'e.title ASC',
    'average_desc' => 'average_percentage DESC,e.title ASC',
    'average_asc' => 'average_percentage ASC,e.title ASC',
    'attempts_desc' => 'attempts DESC,e.title ASC',
];
if (!isset($sortOptions[$examSort])) $examSort = 'newest';
$examOrderBy = $sortOptions[$examSort];

$classes = db()->query('SELECT id,title FROM classes ORDER BY title')->fetchAll();
$subjects = db()->query('SELECT id,title FROM subjects ORDER BY title')->fetchAll();
$exams = db()->query('SELECT e.id,e.title,e.class_id,e.subject_id,c.title class_title,s.title subject_title FROM exams e LEFT JOIN classes c ON c.id=e.class_id LEFT JOIN subjects s ON s.id=e.subject_id ORDER BY e.id DESC')->fetchAll();

$where = ["a.status IN ('submitted','reviewed')", 'a.revoked_at IS NULL'];
$params = [];
if ($classId > 0) { $where[] = 'e.class_id=?'; $params[] = $classId; }
if ($subjectId > 0) { $where[] = 'e.subject_id=?'; $params[] = $subjectId; }
if ($examId > 0) { $where[] = 'a.exam_id=?'; $params[] = $examId; }
$whereSql = implode(' AND ', $where);

$summaryStmt = db()->prepare("SELECT COUNT(*) attempts,COUNT(DISTINCT a.student_id) students,COALESCE(AVG(a.percentage),0) average_percentage,COALESCE(MAX(a.percentage),0) best_percentage FROM attempts a JOIN exams e ON e.id=a.exam_id WHERE $whereSql");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: ['attempts'=>0,'students'=>0,'average_percentage'=>0,'best_percentage'=>0];

$classStmt = db()->prepare("SELECT c.id,c.title,COUNT(a.id) attempts,COUNT(DISTINCT a.student_id) students,COALESCE(AVG(a.percentage),0) average_percentage,COALESCE(MAX(a.percentage),0) best_percentage FROM classes c JOIN exams e ON e.class_id=c.id JOIN attempts a ON a.exam_id=e.id WHERE $whereSql GROUP BY c.id,c.title ORDER BY average_percentage DESC,c.title");
$classStmt->execute($params);
$classRows = $classStmt->fetchAll();

$examWhere = ['1=1'];
$examParams = [];
if ($classId > 0) { $examWhere[] = 'e.class_id=?'; $examParams[] = $classId; }
if ($subjectId > 0) { $examWhere[] = 'e.subject_id=?'; $examParams[] = $subjectId; }
if ($examId > 0) { $examWhere[] = 'e.id=?'; $examParams[] = $examId; }
$examStmt = db()->prepare("SELECT e.id,e.title,c.title class_title,s.title subject_title,COUNT(a.id) attempts,COALESCE(AVG(a.percentage),0) average_percentage,COALESCE(MAX(a.percentage),0) best_percentage FROM exams e LEFT JOIN classes c ON c.id=e.class_id LEFT JOIN subjects s ON s.id=e.subject_id LEFT JOIN attempts a ON a.exam_id=e.id AND a.status IN ('submitted','reviewed') AND a.revoked_at IS NULL WHERE " . implode(' AND ', $examWhere) . " GROUP BY e.id,e.title,c.title,s.title ORDER BY $examOrderBy LIMIT 30");
$examStmt->execute($examParams);
$examRows = $examStmt->fetchAll();
$exportQuery = http_build_query(['class_id'=>$classId,'subject_id'=>$subjectId,'exam_id'=>$examId,'exam_sort'=>$examSort]);
$title = 'گزارش جامع مدیریتی';
require __DIR__ . '/../partials/header.php';
?>
<div class="card class-toolbar"><div><span class="eyebrow">تحلیل مدیریتی</span><h1>گزارش جامع سامانه</h1><p class="muted">مقایسهٔ مشارکت و میانگین عملکرد دانش‌آموزان در کلاس‌ها و آزمون‌ها.</p></div><div class="actions"><a class="btn btn-success" href="<?= e(url('admin/analytics_export.php?' . $exportQuery)) ?>">خروجی Excel</a><button class="btn btn-primary" onclick="window.print()">چاپ گزارش</button><a class="btn btn-light" href="<?= e(url('admin/index.php')) ?>">بازگشت به داشبورد</a></div></div>
<div class="card analytics-filter-panel"><div class="section-heading"><div><span class="eyebrow">محدودهٔ گزارش</span><h2>فیلتر و مرتب‌سازی</h2></div><a class="btn btn-light btn-sm" href="<?= e(url('admin/analytics.php')) ?>">پاک‌کردن فیلترها</a></div>
<form method="get" class="analytics-filters">
    <div class="form-group"><label for="analytics-class">کلاس</label><select id="analytics-class" name="class_id"><option value="0">همهٔ کلاس‌ها</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id'] ?>" <?= $classId === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['title']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="analytics-subject">درس</label><select id="analytics-subject" name="subject_id"><option value="0">همهٔ درس‌ها</option><?php foreach ($subjects as $subject): ?><option value="<?= (int)$subject['id'] ?>" <?= $subjectId === (int)$subject['id'] ? 'selected' : '' ?>><?= e($subject['title']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="analytics-exam">آزمون</label><select id="analytics-exam" name="exam_id"><option value="0">همهٔ آزمون‌ها</option><?php foreach ($exams as $exam): ?><option value="<?= (int)$exam['id'] ?>" data-class-id="<?= (int)($exam['class_id'] ?? 0) ?>" data-subject-id="<?= (int)($exam['subject_id'] ?? 0) ?>" <?= $examId === (int)$exam['id'] ? 'selected' : '' ?>><?= e($exam['title'] . ' — ' . ($exam['class_title'] ?? 'بدون کلاس') . ' / ' . ($exam['subject_title'] ?? 'بدون درس')) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="analytics-sort">ترتیب آزمون‌ها</label><select id="analytics-sort" name="exam_sort"><option value="newest" <?= $examSort === 'newest' ? 'selected' : '' ?>>جدیدترین</option><option value="title" <?= $examSort === 'title' ? 'selected' : '' ?>>نام آزمون</option><option value="average_desc" <?= $examSort === 'average_desc' ? 'selected' : '' ?>>میانگین عملکرد: بیشترین</option><option value="average_asc" <?= $examSort === 'average_asc' ? 'selected' : '' ?>>میانگین عملکرد: کمترین</option><option value="attempts_desc" <?= $examSort === 'attempts_desc' ? 'selected' : '' ?>>بیشترین دفعات شرکت</option></select></div>
    <div class="analytics-filter-actions"><button class="btn btn-primary" type="submit">اعمال فیلتر</button></div>
</form></div>
<div class="grid grid-4 analytics-summary"><div class="card stat"><span>دفعات شرکت</span><strong><?= number_format((int)$summary['attempts']) ?></strong></div><div class="card stat"><span>دانش‌آموزان</span><strong><?= number_format((int)$summary['students']) ?></strong></div><div class="card stat"><span>میانگین درصد</span><strong><?= format_score($summary['average_percentage']) ?>%</strong></div><div class="card stat"><span>بیشترین درصد</span><strong><?= format_score($summary['best_percentage']) ?>%</strong></div></div>
<section class="card"><div class="section-heading"><div><span class="eyebrow">مقایسهٔ کلاس‌ها</span><h2>عملکرد کلاس‌ها</h2></div></div><div class="table-wrap"><table class="table"><thead><tr><th>کلاس</th><th>دانش‌آموز</th><th>دفعات شرکت</th><th>میانگین درصد</th><th>بهترین درصد</th><th>وضعیت</th></tr></thead><tbody><?php if (!$classRows): ?><tr><td colspan="6" class="muted text-center">داده‌ای برای این فیلتر وجود ندارد.</td></tr><?php else: foreach ($classRows as $row): $rate = (float)$row['average_percentage']; ?><tr><td><strong><?= e($row['title']) ?></strong></td><td><?= number_format((int)$row['students']) ?></td><td><?= number_format((int)$row['attempts']) ?></td><td><?= format_score($rate) ?>%</td><td><?= format_score($row['best_percentage']) ?>%</td><td><?= e($rate >= 80 ? 'قوی' : ($rate >= 50 ? 'نیازمند مرور' : 'نیازمند برنامهٔ تقویتی')) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<section class="card"><div class="section-heading"><div><span class="eyebrow">مقایسهٔ آزمون‌ها</span><h2>عملکرد آزمون‌ها</h2><p class="muted">بر اساس فیلترهای بالا؛ نمایش حداکثر ۳۰ آزمون</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>آزمون</th><th>درس</th><th>کلاس</th><th>دفعات شرکت</th><th>میانگین درصد</th><th>بهترین درصد</th><th>گزارش تفصیلی</th></tr></thead><tbody><?php if (!$examRows): ?><tr><td colspan="7" class="muted text-center">آزمونی برای نمایش وجود ندارد.</td></tr><?php else: foreach ($examRows as $row): ?><tr><td><?= e($row['title']) ?></td><td><?= e($row['subject_title'] ?? '—') ?></td><td><?= e($row['class_title'] ?? '—') ?></td><td><?= number_format((int)$row['attempts']) ?></td><td><?= format_score($row['average_percentage']) ?>%</td><td><?= format_score($row['best_percentage']) ?>%</td><td><a class="btn btn-sm btn-primary" href="<?= e(url('teacher/exam_report.php?exam_id=' . $row['id'])) ?>">مشاهده</a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
