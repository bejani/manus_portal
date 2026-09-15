<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('teacher','admin');
$uid = (int) current_user()['id'];
$isAdmin = current_user()['role'] === 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $studentId = (int)($_POST['student_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $titleText = post_string('title', 200);
    $body = post_string('body', 10000);
    $priority = post_string('priority', 20);
    if (!$studentId || !$classId || $titleText === '' || $body === '') {
        flash('error','دانش‌آموز، کلاس، عنوان و متن بازخورد الزامی است.');
    } elseif (!in_array($priority, ['normal','important','support'], true)) {
        flash('error','اولویت بازخورد معتبر نیست.');
    } else {
        $permission = $isAdmin ? true : false;
        if (!$isAdmin) { $p = db()->prepare('SELECT COUNT(*) FROM teacher_classes WHERE teacher_id=? AND class_id=?'); $p->execute([$uid,$classId]); $permission = (int)$p->fetchColumn() > 0; }
        $member = db()->prepare('SELECT COUNT(*) FROM class_students WHERE class_id=? AND student_id=?'); $member->execute([$classId,$studentId]);
        if (!$permission || (int)$member->fetchColumn() !== 1) {
            flash('error','شما مجوز ثبت بازخورد برای این دانش‌آموز را ندارید.');
        } else {
            $stmt = db()->prepare('INSERT INTO feedback_notes(student_id,author_id,class_id,title,body,priority) VALUES(?,?,?,?,?,?)');
            $stmt->execute([$studentId,$uid,$classId,$titleText,$body,$priority]);
            $noteId = (int)db()->lastInsertId();
            notify_user($studentId,'بازخورد آموزشی جدید','معلم یا مدیر برای شما یک بازخورد آموزشی ثبت کرده است.','student/feedback.php','feedback');
            audit('create_feedback','feedback_note',$noteId);
            flash('success','بازخورد برای دانش‌آموز ثبت شد.');
        }
    }
    redirect('teacher/feedback.php');
}
if ($isAdmin) $classes = db()->query('SELECT id,title FROM classes ORDER BY title')->fetchAll();
else { $s = db()->prepare('SELECT c.id,c.title FROM classes c JOIN teacher_classes tc ON tc.class_id=c.id WHERE tc.teacher_id=? ORDER BY c.title'); $s->execute([$uid]); $classes = $s->fetchAll(); }
$studentsStmt = db()->prepare('SELECT cs.class_id,u.id,u.full_name,u.mobile,c.title class_title FROM class_students cs JOIN users u ON u.id=cs.student_id JOIN classes c ON c.id=cs.class_id WHERE u.role="student"' . ($isAdmin ? '' : ' AND EXISTS (SELECT 1 FROM teacher_classes tc WHERE tc.class_id=cs.class_id AND tc.teacher_id=?)') . ' ORDER BY u.full_name');
$studentsStmt->execute($isAdmin ? [] : [$uid]); $students = $studentsStmt->fetchAll();
$recentStmt = $isAdmin ? db()->query('SELECT f.*,u.full_name student_name,a.full_name author_name,c.title class_title FROM feedback_notes f JOIN users u ON u.id=f.student_id JOIN users a ON a.id=f.author_id LEFT JOIN classes c ON c.id=f.class_id ORDER BY f.id DESC LIMIT 30') : db()->prepare('SELECT f.*,u.full_name student_name,a.full_name author_name,c.title class_title FROM feedback_notes f JOIN users u ON u.id=f.student_id JOIN users a ON a.id=f.author_id LEFT JOIN classes c ON c.id=f.class_id WHERE f.author_id=? OR EXISTS (SELECT 1 FROM teacher_classes tc WHERE tc.class_id=f.class_id AND tc.teacher_id=?) ORDER BY f.id DESC LIMIT 30');
if (!$isAdmin) { $recentStmt->execute([$uid,$uid]); } $notes = $recentStmt->fetchAll();
$title='ثبت بازخورد آموزشی'; require __DIR__ . '/../partials/header.php';
?>
<div class="card class-toolbar"><div><span class="eyebrow">پشتیبانی آموزشی</span><h1>ثبت بازخورد دانش‌آموز</h1><p class="muted">برای دانش‌آموزانی که نیازمند مرور یا برنامهٔ تقویتی هستند، یادداشت آموزشی ثبت کنید.</p></div><a class="btn btn-light" href="<?= e(url('teacher/index.php')) ?>">بازگشت</a></div>
<div class="card"><form method="post" class="grid grid-2"><?= csrf_field() ?><div class="form-group"><label>کلاس</label><select name="class_id" required><option value="">انتخاب کنید</option><?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>"><?= e($class['title']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>دانش‌آموز</label><select name="student_id" required><option value="">انتخاب کنید</option><?php foreach ($students as $student): ?><option value="<?= $student['id'] ?>"><?= e($student['full_name'].' — '.$student['class_title']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>عنوان بازخورد</label><input name="title" maxlength="200" placeholder="مثلاً برنامهٔ مرور پودمان اول" required></div><div class="form-group"><label>اولویت</label><select name="priority"><option value="normal">عادی</option><option value="important">مهم</option><option value="support">نیازمند پشتیبانی</option></select></div><div class="form-group" style="grid-column:1/-1"><label>متن بازخورد یا برنامهٔ تمرینی</label><textarea name="body" rows="6" placeholder="نکات آموزشی، مباحث نیازمند مرور و پیشنهاد تمرینی را بنویسید..." required></textarea></div><button class="btn btn-primary">ثبت و اطلاع‌رسانی به دانش‌آموز</button></form></div>
<div class="card"><h2>بازخوردهای اخیر</h2><div class="table-wrap"><table class="table"><thead><tr><th>دانش‌آموز</th><th>کلاس</th><th>عنوان</th><th>اولویت</th><th>ثبت‌کننده</th><th>تاریخ</th></tr></thead><tbody><?php if (!$notes): ?><tr><td colspan="6" class="muted text-center">هنوز بازخوردی ثبت نشده است.</td></tr><?php else: foreach ($notes as $note): ?><tr><td><?= e($note['student_name']) ?></td><td><?= e($note['class_title'] ?? '—') ?></td><td><?= e($note['title']) ?><br><small><?= e($note['body']) ?></small></td><td><?= e($note['priority']) ?></td><td><?= e($note['author_name']) ?></td><td><?= e($note['created_at']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
