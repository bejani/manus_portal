<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('teacher');

$teacherId = (int) current_user()['id'];
$pdo = db();

$classesStmt = $pdo->prepare(
    'SELECT DISTINCT c.id, c.title
     FROM classes c
     WHERE EXISTS (
         SELECT 1 FROM teacher_classes tc
         WHERE tc.class_id = c.id AND tc.teacher_id = ?
     ) OR EXISTS (
         SELECT 1 FROM class_subjects cs
         WHERE cs.class_id = c.id AND cs.teacher_id = ?
     )
     ORDER BY c.title'
);
$classesStmt->execute([$teacherId, $teacherId]);
$classes = $classesStmt->fetchAll();

$canManageClass = static function (int $classId) use ($pdo, $teacherId): bool {
    if ($classId < 1) return false;
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM classes c
         WHERE c.id = ? AND (
             EXISTS (SELECT 1 FROM teacher_classes tc WHERE tc.class_id = c.id AND tc.teacher_id = ?)
             OR EXISTS (SELECT 1 FROM class_subjects cs WHERE cs.class_id = c.id AND cs.teacher_id = ?)
         )'
    );
    $stmt->execute([$classId, $teacherId, $teacherId]);
    return (int) $stmt->fetchColumn() > 0;
};

$selectedClassId = get_int('class_id');
if ($selectedClassId < 1 && $classes) {
    $selectedClassId = (int) $classes[0]['id'];
}
if ($selectedClassId > 0 && !$canManageClass($selectedClassId)) {
    flash('error', 'این کلاس به شما منتسب نیست؛ یکی از کلاس‌های مجاز انتخاب شد.');
    $selectedClassId = $classes ? (int) $classes[0]['id'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $classId = (int) ($_POST['class_id'] ?? 0);

    if (!$canManageClass($classId)) {
        flash('error', 'شما فقط می‌توانید دانش‌آموزان کلاس‌های منتسب به خود را مدیریت کنید.');
        redirect('teacher/students.php');
    }

    if ($action === 'create_student') {
        $name = post_string('full_name', 150);
        $mobile = normalize_mobile(post_string('mobile', 20));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        if ($name === '') $errors[] = 'نام و نام خانوادگی الزامی است.';
        if (strlen($mobile) < 10 || strlen($mobile) > 15) $errors[] = 'شمارهٔ موبایل باید بین ۱۰ تا ۱۵ رقم باشد.';
        if (strlen($password) < 6) $errors[] = 'رمز عبور اولیه باید حداقل ۶ نویسه داشته باشد.';

        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            try {
                $pdo->beginTransaction();
                $create = $pdo->prepare('INSERT INTO users(role, full_name, mobile, password_hash) VALUES(?,?,?,?)');
                $create->execute(['student', $name, $mobile, password_hash($password, PASSWORD_DEFAULT)]);
                $studentId = (int) $pdo->lastInsertId();
                $enroll = $pdo->prepare('INSERT INTO class_students(class_id, student_id) VALUES(?,?)');
                $enroll->execute([$classId, $studentId]);
                $pdo->commit();
                audit('create_student_and_enroll', 'user', $studentId);
                flash('success', 'حساب دانش‌آموز ساخته و به کلاس انتخاب‌شده افزوده شد.');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('error', 'ثبت انجام نشد؛ شمارهٔ موبایل ممکن است قبلاً در سامانه ثبت شده باشد.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('error', 'ساخت حساب یا افزودن به کلاس انجام نشد.');
            }
        }
        redirect('teacher/students.php?class_id=' . $classId);
    }

    if ($action === 'enroll_existing') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $studentStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' AND is_active = 1");
        $studentStmt->execute([$studentId]);
        if (!$studentStmt->fetchColumn()) {
            flash('error', 'حساب دانش‌آموز فعال پیدا نشد.');
        } else {
            $enroll = $pdo->prepare('INSERT IGNORE INTO class_students(class_id, student_id) VALUES(?,?)');
            $enroll->execute([$classId, $studentId]);
            if ($enroll->rowCount()) {
                audit('enroll_student', 'class', $classId);
                flash('success', 'دانش‌آموز به کلاس افزوده شد.');
            } else {
                flash('error', 'این دانش‌آموز از قبل عضو همین کلاس است.');
            }
        }
        redirect('teacher/students.php?class_id=' . $classId);
    }

    flash('error', 'عملیات درخواستی معتبر نیست.');
    redirect('teacher/students.php?class_id=' . $classId);
}

$search = trim((string) ($_GET['q'] ?? ''));
$availableStudents = [];
$members = [];
if ($selectedClassId > 0) {
    $availableSql = "SELECT u.id, u.full_name, u.mobile
                     FROM users u
                     WHERE u.role = 'student' AND u.is_active = 1
                       AND NOT EXISTS (
                           SELECT 1 FROM class_students cs
                           WHERE cs.class_id = ? AND cs.student_id = u.id
                       )";
    $availableParams = [$selectedClassId];
    if ($search !== '') {
        $availableSql .= ' AND (u.full_name LIKE ? OR u.mobile LIKE ?)';
        $like = '%' . $search . '%';
        $availableParams[] = $like;
        $availableParams[] = $like;
    }
    $availableSql .= ' ORDER BY u.full_name LIMIT 200';
    $availableStmt = $pdo->prepare($availableSql);
    $availableStmt->execute($availableParams);
    $availableStudents = $availableStmt->fetchAll();

    $membersStmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.mobile
         FROM class_students cs JOIN users u ON u.id = cs.student_id
         WHERE cs.class_id = ? AND u.role = 'student'
         ORDER BY u.full_name"
    );
    $membersStmt->execute([$selectedClassId]);
    $members = $membersStmt->fetchAll();
}

$title = 'دانش‌آموزان کلاس‌های من';
require __DIR__ . '/../partials/header.php';
?>
<section class="student-admin-hero">
    <div><span class="eyebrow">ابزار معلم</span><h1>دانش‌آموزان کلاس‌های من</h1>
        <p>حساب دانش‌آموز بسازید یا دانش‌آموز ثبت‌شده را به یکی از کلاس‌های منتسب به خود اضافه کنید.</p></div>
</section>
<?php if (!$classes): ?>
    <div class="card"><h2>هنوز کلاسی به شما منتسب نشده است</h2><p class="muted">برای دسترسی به این بخش، مدیر باید شما را به کلاس یا یکی از درس‌های آن کلاس اختصاص دهد.</p></div>
<?php else: ?>
    <form method="get" class="card student-class-select"><label for="class_id">کلاس</label>
        <select id="class_id" name="class_id" required>
            <?php foreach ($classes as $class): ?><option value="<?=$class['id']?>" <?=$selectedClassId === (int)$class['id'] ? 'selected' : ''?>><?=e($class['title'])?></option><?php endforeach; ?>
        </select><button class="btn btn-primary">نمایش کلاس</button>
    </form>
    <div class="student-management-grid">
        <section class="card student-management-card">
            <span class="eyebrow">حساب جدید</span><h2>تعریف دانش‌آموز و افزودن به کلاس</h2>
            <p class="muted">نقش حساب به‌صورت خودکار «دانش‌آموز» ثبت می‌شود؛ معلم امکان ساخت حساب مدیر یا معلم ندارد.</p>
            <form method="post" class="grid">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="create_student"><input type="hidden" name="class_id" value="<?=$selectedClassId?>">
                <label>نام و نام خانوادگی<input name="full_name" maxlength="150" required></label>
                <label>شمارهٔ موبایل<input name="mobile" inputmode="tel" autocomplete="tel" required></label>
                <label>رمز عبور اولیه<input name="password" type="password" minlength="6" autocomplete="new-password" required></label>
                <button class="btn btn-primary">ساخت حساب و افزودن به کلاس</button>
            </form>
        </section>
        <section class="card student-management-card">
            <span class="eyebrow">حساب موجود</span><h2>افزودن دانش‌آموز ثبت‌شده</h2>
            <form method="get" class="search-form student-existing-search">
                <input type="hidden" name="class_id" value="<?=$selectedClassId?>">
                <input name="q" value="<?=e($search)?>" placeholder="جست‌وجو با نام یا موبایل">
                <button class="btn btn-light">جست‌وجو</button>
            </form>
            <?php if ($availableStudents): ?>
                <form method="post" class="grid student-enroll-form">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="enroll_existing"><input type="hidden" name="class_id" value="<?=$selectedClassId?>">
                    <label>دانش‌آموز<select name="student_id" required><option value="">انتخاب کنید</option><?php foreach ($availableStudents as $student): ?><option value="<?=$student['id']?>"><?=e($student['full_name'])?> — <?=e($student['mobile'])?></option><?php endforeach; ?></select></label>
                    <small class="muted">حداکثر ۲۰۰ نتیجهٔ نخست نمایش داده می‌شود؛ برای یافتن سریع، نام یا موبایل را جست‌وجو کنید.</small>
                    <button class="btn btn-success">افزودن به کلاس</button>
                </form>
            <?php else: ?><p class="muted">دانش‌آموزی مطابق جست‌وجو پیدا نشد که هنوز عضو این کلاس باشد.</p><?php endif; ?>
        </section>
    </div>
    <section class="card student-members-card">
        <div class="section-heading"><div><span class="eyebrow">فهرست کلاس</span><h2>اعضای کلاس <span class="badge"><?=number_format(count($members))?></span></h2></div></div>
        <?php if ($members): ?><div class="table-wrap"><table class="table"><thead><tr><?php if (count($members) > 10): ?><th>ردیف</th><?php endif; ?><th>نام دانش‌آموز</th><th>موبایل</th></tr></thead><tbody>
            <?php foreach ($members as $index => $member): ?><tr><?php if (count($members) > 10): ?><td><?=number_format($index + 1)?></td><?php endif; ?><td><?=e($member['full_name'])?></td><td><?=e($member['mobile'])?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><div class="empty-card"><h3>هنوز دانش‌آموزی به این کلاس افزوده نشده است</h3><p class="muted">از یکی از فرم‌های بالا استفاده کنید.</p></div><?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
