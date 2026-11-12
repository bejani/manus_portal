<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'archive' && $id > 0) {
        $active = db()->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id=? AND status='in_progress' AND revoked_at IS NULL");
        $active->execute([$id]);
        if ((int)$active->fetchColumn() > 0) {
            flash('error', 'تا وقتی دانش‌آموزی در حال پاسخ‌گویی به این آزمون است، امکان بایگانی آن نیست.');
        } else {
            $s = db()->prepare("UPDATE exams SET archived_at=NOW(),status='closed' WHERE id=? AND archived_at IS NULL");
            $s->execute([$id]);
            if ($s->rowCount()) {
                audit('archive_exam', 'exam', $id);
                flash('success', 'آزمون بایگانی شد؛ سوابق و کارنامه‌ها حفظ شده‌اند.');
            } else {
                flash('error', 'آزمون پیدا نشد یا از قبل بایگانی شده است.');
            }
        }
    } elseif ($action === 'unarchive' && $id > 0) {
        $s = db()->prepare("UPDATE exams SET archived_at=NULL,status='draft' WHERE id=? AND archived_at IS NOT NULL");
        $s->execute([$id]);
        if ($s->rowCount()) {
            audit('unarchive_exam', 'exam', $id);
            flash('success', 'آزمون از بایگانی خارج شد و برای جلوگیری از انتشار ناخواسته به پیش‌نویس برگشت.');
        } else {
            flash('error', 'آزمون بایگانی‌شده‌ای با این شناسه پیدا نشد.');
        }
    }
    if ($action === 'status' && $id > 0) {
        $status = post_string('status');
        if (in_array($status, ['draft', 'published', 'closed'], true)) {
            $s = db()->prepare('UPDATE exams SET status=? WHERE id=?');
            $s->execute([$status, $id]);
            audit('change_exam_status', 'exam', $id);
            flash('success', 'وضعیت آزمون تغییر کرد.');
        }
    }

    if ($action === 'copy' && $id > 0) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare('SELECT * FROM exams WHERE id=?');
            $s->execute([$id]);
            $exam = $s->fetch();
            if (!$exam) throw new RuntimeException('آزمون پیدا نشد.');

            $copy = $pdo->prepare('INSERT INTO exams(subject_id,class_id,title,instructions,duration_minutes,total_score,starts_at,ends_at,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $copy->execute([$exam['subject_id'], $exam['class_id'], $exam['title'] . ' - کپی', $exam['instructions'], $exam['duration_minutes'], $exam['total_score'], null, null, 'draft', current_user()['id']]);
            $newId = (int)$pdo->lastInsertId();

            $qs = $pdo->prepare('SELECT * FROM questions WHERE exam_id=? ORDER BY sort_order,id');
            $qs->execute([$id]);
            $qInsert = $pdo->prepare('INSERT INTO questions(exam_id,question_text,question_type,score,sort_order,feedback,reference_answer,rubric) VALUES(?,?,?,?,?,?,?,?)');
            $oSelect = $pdo->prepare('SELECT * FROM question_options WHERE question_id=? ORDER BY sort_order,id');
            $oInsert = $pdo->prepare('INSERT INTO question_options(question_id,option_text,option_key,is_correct,sort_order) VALUES(?,?,?,?,?)');
            foreach ($qs as $q) {
                $qInsert->execute([$newId, $q['question_text'], $q['question_type'], $q['score'], $q['sort_order'], $q['feedback'], $q['reference_answer'] ?? null, $q['rubric'] ?? null]);
                $newQ = (int)$pdo->lastInsertId();
                $oSelect->execute([$q['id']]);
                foreach ($oSelect as $o) {
                    $oInsert->execute([$newQ, $o['option_text'], $o['option_key'], $o['is_correct'], $o['sort_order']]);
                }
            }
            $pdo->commit();
            audit('copy_exam', 'exam', $newId);
            flash('success', 'کپی آزمون ساخته شد و به‌صورت پیش‌نویس آماده است.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'کپی آزمون انجام نشد.');
        }
    }
    redirect('admin/exams.php');
}

$search = trim((string)($_GET['q'] ?? ''));
$classId = max(0, (int)($_GET['class_id'] ?? 0));
$subjectId = max(0, (int)($_GET['subject_id'] ?? 0));
$teacherId = max(0, (int)($_GET['teacher_id'] ?? 0));
$status = (string)($_GET['status'] ?? '');
$archiveView = (($_GET['archived'] ?? '') === '1');
$allowedStatuses = ['draft', 'published', 'closed'];
if (!in_array($status, $allowedStatuses, true)) $status = '';

$where = [$archiveView ? 'e.archived_at IS NOT NULL' : 'e.archived_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[] = '(e.title LIKE ? OR s.title LIKE ? OR c.title LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($classId > 0) {
    $where[] = 'e.class_id = ?';
    $params[] = $classId;
}
if ($subjectId > 0) {
    $where[] = 'e.subject_id = ?';
    $params[] = $subjectId;
}
if ($teacherId > 0) {
    $where[] = 'e.created_by = ?';
    $params[] = $teacherId;
}
if ($status !== '') {
    $where[] = 'e.status = ?';
    $params[] = $status;
}

$sql = 'SELECT e.id,e.title,e.status,e.total_score,e.created_at,e.archived_at,s.title subject_title,c.title class_title,u.full_name teacher_name,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_id=e.id) question_count,
        (SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id) attempt_count
        FROM exams e
        LEFT JOIN subjects s ON s.id=e.subject_id
        LEFT JOIN classes c ON c.id=e.class_id
        LEFT JOIN users u ON u.id=e.created_by';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY e.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$exams = $stmt->fetchAll();

$classes = db()->query('SELECT id,title FROM classes ORDER BY title')->fetchAll();
$subjects = db()->query('SELECT id,title FROM subjects ORDER BY title')->fetchAll();
$creators = db()->query('SELECT DISTINCT u.id,u.full_name FROM users u JOIN exams e ON e.created_by=u.id ORDER BY u.full_name')->fetchAll();
$statusLabels = ['draft' => 'پیش‌نویس', 'published' => 'منتشرشده', 'closed' => 'بسته'];
$title = 'مدیریت آزمون‌ها';
require __DIR__ . '/../partials/header.php';
?>
<div class="card class-toolbar">
    <div>
        <span class="eyebrow">مرکز کنترل آزمون</span>
        <h1>مدیریت آزمون‌ها</h1>
        <p class="muted">مشاهده، فیلتر، پیش‌نمایش، کپی، انتشار و بررسی نتایج همهٔ آزمون‌ها.</p>
    </div>
    <a class="btn btn-success" href="<?= e(url('teacher/exams.php')) ?>">ساخت آزمون جدید</a>
</div>

<div class="card exam-filter-card">
    <form method="get" class="grid exam-filter-form">
        <div class="form-group">
            <label for="exam-search">جست‌وجو</label>
            <input id="exam-search" name="q" value="<?= e($search) ?>" placeholder="عنوان آزمون">
        </div>
        <div class="form-group">
            <label for="filter-class">کلاس</label>
            <select id="filter-class" name="class_id">
                <option value="0">همهٔ کلاس‌ها</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= (int)$class['id'] ?>" <?= $classId === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="filter-subject">درس</label>
            <select id="filter-subject" name="subject_id">
                <option value="0">همهٔ درس‌ها</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int)$subject['id'] ?>" <?= $subjectId === (int)$subject['id'] ? 'selected' : '' ?>><?= e($subject['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="filter-teacher">معلم / ایجادکننده</label>
            <select id="filter-teacher" name="teacher_id">
                <option value="0">همهٔ معلم‌ها</option>
                <?php foreach ($creators as $creator): ?>
                    <option value="<?= (int)$creator['id'] ?>" <?= $teacherId === (int)$creator['id'] ? 'selected' : '' ?>><?= e($creator['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="filter-status">وضعیت</label>
            <select id="filter-status" name="status">
                <option value="">همهٔ وضعیت‌ها</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="filter-archived">نمایش</label>
            <select id="filter-archived" name="archived">
                <option value="" <?= !$archiveView ? 'selected' : '' ?>>آزمون‌های جاری</option>
                <option value="1" <?= $archiveView ? 'selected' : '' ?>>آزمون‌های بایگانی‌شده</option>
            </select>
        </div>
        <div class="form-group exam-filter-actions">
            <button class="btn btn-primary" type="submit">اعمال فیلتر</button>
            <a class="btn btn-light" href="<?= e(url('admin/exams.php')) ?>">پاک‌کردن</a>
        </div>
    </form>
    <p class="muted exam-filter-count">تعداد آزمون‌های منطبق: <?= number_format(count($exams)) ?></p>
</div>

<div class="card table-wrap">
    <table class="table">
        <thead>
            <tr><?php if (count($exams) > 10): ?><th>ردیف</th><?php endif; ?><th>آزمون</th><th>درس / کلاس</th><th>معلم</th><th>پرسش</th><th>تلاش</th><th>وضعیت</th><th>بایگانی</th><th>عملیات</th></tr>
        </thead>
        <tbody>
        <?php if (!$exams): ?>
            <tr><td colspan="<?= count($exams) > 10 ? 9 : 8 ?>" class="muted text-center">آزمونی با این فیلترها پیدا نشد.</td></tr>
        <?php else: foreach ($exams as $rowIndex => $exam): ?>
            <tr>
                <?php if (count($exams) > 10): ?><td><?= number_format($rowIndex + 1) ?></td><?php endif; ?>
                <td><strong><?= e($exam['title']) ?></strong><br><small class="muted">نمره کل: <?= format_score($exam['total_score']) ?> — <?= e($exam['created_at']) ?></small></td>
                <td><?= e($exam['subject_title'] ?? 'بدون درس') ?> / <?= e($exam['class_title'] ?? 'بدون کلاس') ?></td>
                <td><?= e($exam['teacher_name'] ?? '—') ?></td>
                <td><?= e((string)$exam['question_count']) ?></td>
                <td><?= e((string)$exam['attempt_count']) ?></td>
                <td><span class="badge <?= e($exam['status']) ?>"><?= e($statusLabels[$exam['status']] ?? $exam['status']) ?></span></td>
                <td><?= $exam['archived_at'] ? '<span class="badge">بایگانی‌شده</span>' : '—' ?></td>
                <td>
                    <div class="actions">
                        <?php if ($exam['archived_at']): ?>
                            <a class="btn btn-sm btn-light" href="<?= e(url('teacher/results.php?exam_id=' . $exam['id'])) ?>">سوابق و نتایج</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('آزمون از بایگانی خارج و به پیش‌نویس برگردانده شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="unarchive"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><button class="btn btn-sm btn-success">بازیابی</button></form>
                        <?php else: ?>
                        <a class="btn btn-sm btn-primary" href="<?= e(url('teacher/exams.php?id=' . $exam['id'])) ?>">مدیریت پرسش‌ها</a>
                        <a class="btn btn-sm btn-light" href="<?= e(url('admin/exam_preview.php?id=' . $exam['id'])) ?>">پیش‌نمایش</a>
                        <a class="btn btn-sm btn-light" href="<?= e(url('teacher/results.php?exam_id=' . $exam['id'])) ?>">نتایج</a>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="copy"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><button class="btn btn-sm btn-light">کپی</button></form>
                        <?php if ($exam['status'] === 'published'): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('آزمون از دسترس دانش‌آموزان خارج و برای ویرایش به پیش‌نویس برگردانده شود؟ پاسخ‌های قبلی حذف نمی‌شوند.');"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><input type="hidden" name="status" value="draft"><button class="btn btn-sm btn-warning">بازگشت به پیش‌نویس</button></form>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><input type="hidden" name="status" value="closed"><button class="btn btn-sm btn-danger">بستن</button></form>
                        <?php elseif ($exam['status'] === 'closed'): ?>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><input type="hidden" name="status" value="draft"><button class="btn btn-sm btn-light">بازکردن</button></form>
                        <?php else: ?>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><input type="hidden" name="status" value="published"><button class="btn btn-sm btn-success">انتشار</button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('آزمون از فهرست‌های جاری و دسترس دانش‌آموز خارج شود؟ سوابق و کارنامه‌ها حذف نمی‌شوند.')"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><button class="btn btn-sm btn-light">بایگانی</button></form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
