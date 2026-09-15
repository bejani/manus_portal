<?php
require_once __DIR__ . '/../lib/bootstrap.php'; require_role('admin');

$existingClassColumns = db()->query('SHOW COLUMNS FROM classes')->fetchAll(PDO::FETCH_COLUMN);
foreach (['grade_level' => 'VARCHAR(30) NULL', 'field_name' => 'VARCHAR(150) NULL', 'section_name' => 'VARCHAR(50) NULL'] as $column => $definition) {
    if (!in_array($column, $existingClassColumns, true)) {
        db()->exec('ALTER TABLE classes ADD COLUMN `' . $column . '` ' . $definition);
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$editClassId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editClass = null;
if ($editClassId > 0) {
    $stmt = db()->prepare('SELECT * FROM classes WHERE id = ?');
    $stmt->execute([$editClassId]);
    $editClass = $stmt->fetch();
    if (!$editClass) {
        $editClassId = 0;
        $editClass = null;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $classId = (int)($_POST['class_id'] ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $returnSearch = trim((string)($_POST['q'] ?? ''));
    $suffix = $returnSearch !== '' ? '?q=' . rawurlencode($returnSearch) : '';
    if ($action === 'create') {
        $titleText = post_string('title', 150);
        if ($titleText !== '') {
            $s = db()->prepare('INSERT INTO classes(title,grade_level,field_name,section_name,school_year,description,created_by) VALUES(?,?,?,?,?,?,?)');
            $s->execute([$titleText, post_string('grade_level', 30), post_string('field_name', 150), post_string('section_name', 50), post_string('school_year', 30), post_string('description'), current_user()['id']]);
            flash('success', 'کلاس ایجاد شد.');
        } else {
            flash('error', 'عنوان کلاس الزامی است.');
        }
    } elseif ($action === 'update') {
        $titleText = post_string('title', 150);
        if ($classId > 0 && $titleText !== '') {
            $s = db()->prepare('UPDATE classes SET title = ?, grade_level = ?, field_name = ?, section_name = ?, school_year = ?, description = ? WHERE id = ?');
            $s->execute([$titleText, post_string('grade_level', 30), post_string('field_name', 150), post_string('section_name', 50), post_string('school_year', 30), post_string('description'), $classId]);
            flash('success', 'اطلاعات کلاس با موفقیت به‌روزرسانی شد.');
        } else {
            flash('error', 'عنوان کلاس و شناسه کلاس معتبر نیستند.');
        }
    } elseif ($action === 'delete') {
        if ($classId > 0) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM class_students WHERE class_id = ?')->execute([$classId]);
                $pdo->prepare('DELETE FROM class_subjects WHERE class_id = ?')->execute([$classId]);
                $pdo->prepare('DELETE FROM teacher_classes WHERE class_id = ?')->execute([$classId]);
                $examIds = $pdo->prepare('SELECT id FROM exams WHERE class_id = ?');
                $examIds->execute([$classId]);
                foreach ($examIds->fetchAll(PDO::FETCH_COLUMN) as $examId) {
                    $pdo->prepare('DELETE FROM report_shares WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id = ?)')->execute([$examId]);
                    $pdo->prepare('DELETE FROM answers WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id = ?)')->execute([$examId]);
                    $pdo->prepare('DELETE FROM attempts WHERE exam_id = ?')->execute([$examId]);
                    $pdo->prepare('DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE exam_id = ?)')->execute([$examId]);
                    $pdo->prepare('DELETE FROM questions WHERE exam_id = ?')->execute([$examId]);
                }
                $pdo->prepare('DELETE FROM exams WHERE class_id = ?')->execute([$classId]);
                $pdo->prepare('DELETE FROM classes WHERE id = ?')->execute([$classId]);
                $pdo->commit();
                flash('success', 'کلاس حذف شد.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash('error', 'حذف کلاس انجام نشد.');
            }
        } else {
            flash('error', 'کلاس موردنظر نامعتبر است.');
        }
    } elseif ($action === 'enroll') {
        if ($classId > 0 && $studentId > 0) {
            $s = db()->prepare('INSERT IGNORE INTO class_students(class_id,student_id) VALUES(?,?)');
            $s->execute([$classId, $studentId]);
            flash('success', $s->rowCount() ? 'دانش‌آموز به کلاس اضافه شد.' : 'این دانش‌آموز قبلاً عضو همین کلاس بوده است.');
        } else {
            flash('error', 'کلاس و دانش‌آموز را انتخاب کنید.');
        }
    } elseif ($action === 'remove') {
        if ($classId > 0 && $studentId > 0) {
            $s = db()->prepare('DELETE FROM class_students WHERE class_id=? AND student_id=?');
            $s->execute([$classId, $studentId]);
            flash('success', $s->rowCount() ? 'دانش‌آموز از کلاس حذف شد.' : 'عضویت موردنظر پیدا نشد.');
        } else {
            flash('error', 'اطلاعات حذف عضو معتبر نیست.');
        }
    } elseif ($action === 'assign_subject') {
        if ($classId > 0 && $subjectId > 0 && $teacherId > 0) {
            $s = db()->prepare('INSERT INTO class_subjects(class_id,subject_id,teacher_id) VALUES(?,?,?)');
            try {
                $s->execute([$classId, $subjectId, $teacherId]);
                flash('success', 'درس و معلم به کلاس اختصاص داده شد.');
            } catch (Throwable $e) {
                flash('error', 'این درس قبلاً به همین کلاس اختصاص یافته یا اطلاعات نامعتبر است.');
            }
        } else {
            flash('error', 'کلاس، درس و معلم را انتخاب کنید.');
        }
    } elseif ($action === 'remove_subject') {
        if ($classId > 0 && $subjectId > 0) {
            $s = db()->prepare('DELETE FROM class_subjects WHERE class_id=? AND subject_id=?');
            $s->execute([$classId, $subjectId]);
            flash('success', 'تخصیص درس از کلاس حذف شد.');
        } else {
            flash('error', 'اطلاعات تخصیص درس معتبر نیست.');
        }
    } elseif ($action === 'assign_teacher') {
        if ($classId > 0 && $teacherId > 0) {
            $s = db()->prepare("INSERT IGNORE INTO teacher_classes(teacher_id,class_id) VALUES(?,?)");
            $s->execute([$teacherId, $classId]);
            flash('success', $s->rowCount() ? 'معلم به کلاس منتسب شد.' : 'این معلم قبلاً به کلاس منتسب بوده است.');
        } else {
            flash('error', 'کلاس و معلم را انتخاب کنید.');
        }
    } elseif ($action === 'remove_teacher') {
        if ($classId > 0 && $teacherId > 0) {
            $s = db()->prepare('DELETE FROM teacher_classes WHERE teacher_id=? AND class_id=?');
            $s->execute([$teacherId, $classId]);
            flash('success', $s->rowCount() ? 'معلم از کلاس حذف شد.' : 'انتساب معلم پیدا نشد.');
        } else {
            flash('error', 'اطلاعات حذف معلم معتبر نیست.');
        }
    }
    redirect('admin/classes.php' . $suffix);
}
$classes = db()->query('SELECT c.*,COUNT(cs.student_id) student_count FROM classes c LEFT JOIN class_students cs ON cs.class_id=c.id GROUP BY c.id ORDER BY c.id DESC')->fetchAll();
$teachers = db()->query("SELECT id,full_name,mobile FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
$subjects = db()->query('SELECT id,title,grade_level,field_name FROM subjects WHERE is_active=1 ORDER BY title,grade_level')->fetchAll();
$subjectRows = db()->query("SELECT cs.class_id,cs.subject_id,cs.teacher_id,s.title,s.grade_level,s.field_name,u.full_name teacher_name FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id JOIN users u ON u.id=cs.teacher_id ORDER BY s.title,u.full_name")->fetchAll();
$subjectsByClass = [];
foreach ($subjectRows as $subjectRow) {
    $subjectsByClass[(int)$subjectRow['class_id']][] = $subjectRow;
}
$teacherRows = db()->query("SELECT tc.class_id,u.id,u.full_name,u.mobile FROM teacher_classes tc JOIN users u ON u.id=tc.teacher_id WHERE u.role='teacher' AND u.is_active=1 ORDER BY u.full_name")->fetchAll();
$teachersByClass = [];
foreach ($teacherRows as $teacher) {
    $teachersByClass[(int)$teacher['class_id']][] = $teacher;
}
$studentsSql = "SELECT id,full_name,mobile FROM users WHERE role='student' AND is_active=1";
$studentParams = [];
if ($search !== '') {
    $studentsSql .= ' AND (full_name LIKE ? OR mobile LIKE ?)';
    $like = '%' . $search . '%';
    $studentParams = [$like, $like];
}
$studentsSql .= ' ORDER BY full_name';
$s = db()->prepare($studentsSql);
$s->execute($studentParams);
$students = $s->fetchAll();
$memberSql = "SELECT cs.class_id,u.id,u.full_name,u.mobile FROM class_students cs JOIN users u ON u.id=cs.student_id WHERE u.role='student'";
$memberParams = [];
if ($search !== '') {
    $memberSql .= ' AND (u.full_name LIKE ? OR u.mobile LIKE ?)';
    $memberParams = [$like, $like];
}
$memberSql .= ' ORDER BY u.full_name';
$s = db()->prepare($memberSql);
$s->execute($memberParams);
$membersByClass = [];
foreach ($s->fetchAll() as $member) {
    $membersByClass[(int)$member['class_id']][] = $member;
}
$title = 'مدیریت کلاس‌ها';
require __DIR__ . '/../partials/header.php';
?><h1>مدیریت کلاس‌ها</h1><div class="card class-toolbar"><div><h2>جست‌وجوی دانش‌آموز</h2><p class="muted">نام، نام خانوادگی یا شماره موبایل را وارد کنید تا فهرست افزودن و اعضای کلاس فیلتر شود.</p></div><form method="get" class="search-form"><input name="q" value="<?=e($search)?>" placeholder="جست‌وجو بر اساس نام یا موبایل..." autofocus><button class="btn btn-primary">جست‌وجو</button><?php if ($search !== ''): ?><a class="btn btn-light" href="<?=e(url('admin/classes.php'))?>">حذف فیلتر</a><?php endif; ?></form></div><div class="card"><h2><?= $editClass ? 'ویرایش کلاس' : 'ایجاد کلاس جدید' ?></h2><form method="post" class="grid grid-2"><?=csrf_field()?><input type="hidden" name="action" value="<?= $editClass ? 'update' : 'create' ?>"><?php if ($editClass): ?><input type="hidden" name="class_id" value="<?= (int)$editClass['id'] ?>"><?php endif; ?><div class="form-group"><label>عنوان کلاس</label><input name="title" required value="<?= e($editClass['title'] ?? '') ?>"></div><div class="form-group"><label>پایه</label><select name="grade_level"><option value="">انتخاب پایه</option><option value="دهم" <?= ($editClass['grade_level'] ?? '') === 'دهم' ? 'selected' : '' ?>>دهم</option><option value="یازدهم" <?= ($editClass['grade_level'] ?? '') === 'یازدهم' ? 'selected' : '' ?>>یازدهم</option><option value="دوازدهم" <?= ($editClass['grade_level'] ?? '') === 'دوازدهم' ? 'selected' : '' ?>>دوازدهم</option></select></div><div class="form-group"><label>رشته</label><input name="field_name" placeholder="مثلاً: شبکه و نرم‌افزار" value="<?= e($editClass['field_name'] ?? '') ?>"></div><div class="form-group"><label>بخش / شماره کلاس</label><input name="section_name" placeholder="مثلاً: ۱ یا الف" value="<?= e($editClass['section_name'] ?? '') ?>"></div><div class="form-group"><label>سال تحصیلی</label><input name="school_year" placeholder="۱۴۰۵-۱۴۰۶" value="<?= e($editClass['school_year'] ?? '') ?>"></div><div class="form-group"><label>توضیحات</label><textarea name="description"><?= e($editClass['description'] ?? '') ?></textarea></div><?php if ($editClass): ?><div class="form-actions"><button class="btn btn-primary">ذخیره تغییرات</button><a class="btn btn-light" href="<?=e(url('admin/classes.php'))?>">انصراف</a></div><?php else: ?><div><button class="btn btn-primary">ثبت کلاس</button></div><?php endif; ?></form></div><div class="class-grid"><?php foreach ($classes as $class): $cid = (int)$class['id']; ?><section class="card class-card"><div class="class-card-header"><div><h2><?=e($class['title'])?></h2><p class="muted">پایه: <?=e($class['grade_level']?:'—')?> | رشته: <?=e($class['field_name']?:'همه رشته‌ها')?> | بخش: <?=e($class['section_name']?:'—')?> | سال تحصیلی: <?=e($class['school_year']?:'—')?> | تعداد اعضا: <strong><?=(int)$class['student_count']?></strong></p></div><span class="class-id">کلاس #<?=$cid?></span></div><div class="class-card-actions"><a class="btn btn-sm btn-light" href="<?=e(url('admin/classes.php?edit_id=' . $cid . ($search !== '' ? '&q=' . rawurlencode($search) : '')))?>">ویرایش</a><form method="post" onsubmit="return confirm('این کلاس حذف شود؟')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="class_id" value="<?=$cid?>"><input type="hidden" name="q" value="<?=e($search)?>"><button class="btn btn-sm btn-danger">حذف</button></form></div><div class="class-teachers"><div><h3>معلمان کلاس</h3><?php if (empty($teachersByClass[$cid])): ?><p class="muted">هنوز معلمی به این کلاس منتسب نشده است.</p><?php else: ?><div class="teacher-list"><?php foreach ($teachersByClass[$cid] as $teacher): ?><div class="teacher-chip"><span><strong><?=e($teacher['full_name'])?></strong><small><?=e($teacher['mobile'])?></small></span><form method="post" onsubmit="return confirm('این معلم از کلاس حذف شود؟')"><?=csrf_field()?><input type="hidden" name="action" value="remove_teacher"><input type="hidden" name="class_id" value="<?=$cid?>"><input type="hidden" name="teacher_id" value="<?=$teacher['id']?>"><input type="hidden" name="q" value="<?=e($search)?>"><button class="btn btn-sm btn-danger">حذف</button></form></div><?php endforeach; ?></div><?php endif; ?></div><form method="post" class="teacher-assign-form"><?=csrf_field()?><input type="hidden" name="action" value="assign_teacher"><input type="hidden" name="class_id" value="<?=$cid?>"><input type="hidden" name="q" value="<?=e($search)?>"><select name="teacher_id" required><option value="">انتخاب معلم</option><?php foreach ($teachers as $teacher): ?><option value="<?=$teacher['id']?>"><?=e($teacher['full_name'])?> — <?=e($teacher['mobile'])?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">افزودن معلم</button></form></div><div class="class-subjects"><h3>درس‌های این کلاس</h3><?php if (empty($subjectsByClass[$cid])): ?><p class="muted">هنوز درسی به این کلاس اختصاص نیافته است.</p><?php else: ?><div class="teacher-list"><?php foreach ($subjectsByClass[$cid] as $assigned): ?><div class="teacher-chip"><span><strong><?=e($assigned['title'])?><?php if ($assigned['grade_level']): ?> - <?=e($assigned['grade_level'])?><?php endif; ?></strong><small><?=e($assigned['teacher_name'])?></small></span><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_subject"><input type="hidden" name="class_id" value="<?=$cid?>"><input type="hidden" name="subject_id" value="<?=$assigned['subject_id']?>"><button class="btn btn-sm btn-danger">حذف</button></form></div><?php endforeach; ?></div><?php endif; ?><form method="post" class="teacher-assign-form"><?=csrf_field()?><input type="hidden" name="action" value="assign_subject"><input type="hidden" name="class_id" value="<?=$cid?>"><select name="subject_id" required><option value="">انتخاب درس سازگار</option><?php foreach ($subjects as $subject): ?><?php if (!$class['grade_level'] || !$subject['grade_level'] || $class['grade_level'] === $subject['grade_level']): ?><option value="<?=$subject['id']?>"><?=e($subject['title'])?> — <?=e($subject['grade_level']?:'همه پایه‌ها')?></option><?php endif; ?><?php endforeach; ?></select><select name="teacher_id" required><option value="">انتخاب معلم</option><?php foreach ($teachers as $teacher): ?><option value="<?=$teacher['id']?>"><?=e($teacher['full_name'])?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">اختصاص درس</button></form></div><div class="class-columns"><div class="class-members"><h3>اعضای کلاس</h3><?php if (empty($membersByClass[$cid])): ?><p class="muted"><?=$search !== '' ? 'عضوی مطابق جست‌وجو پیدا نشد.' : 'هنوز عضوی ثبت نشده است.'?></p><?php else: ?><div class="member-list"><?php foreach ($membersByClass[$cid] as $member): ?><div class="member-row"><span><strong><?=e($member['full_name'])?></strong><small class="muted"><?=e($member['mobile'])?></small></span><form method="post" onsubmit="return confirm('این دانش‌آموز از کلاس حذف شود؟')"><?=csrf_field()?><input type="hidden" name="action" value="remove"><input type="hidden" name="class_id" value="<?=$cid?>"><input type="hidden" name="student_id" value="<?=$member['id']?>"><input type="hidden" name="q" value="<?=e($search)?>"><button class="btn btn-sm btn-danger">حذف</button></form></div><?php endforeach; ?></div><?php endif; ?></div><div class="class-enroll"><h3>افزودن دانش‌آموز</h3><?php if (!$students): ?><p class="muted">دانش‌آموز فعالی مطابق جست‌وجو پیدا نشد.</p><?php else: ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="enroll"><input type="hidden" name="class_id" value="<?=$cid?>"><input type="hidden" name="q" value="<?=e($search)?>"><select name="student_id" required><option value="">انتخاب دانش‌آموز (<?=count($students)?> نتیجه)</option><?php foreach ($students as $student): ?><option value="<?=$student['id']?>"><?=e($student['full_name'])?> — <?=e($student['mobile'])?></option><?php endforeach; ?></select><button class="btn btn-success">افزودن به این کلاس</button></form><?php endif; ?></div></div></section><?php endforeach; ?></div><?php require __DIR__ . '/../partials/footer.php'; ?>
