<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('admin');

$summary = [
    'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'students' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn(),
    'teachers' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn(),
    'classes' => (int) db()->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
    'subjects' => (int) db()->query('SELECT COUNT(*) FROM subjects WHERE is_active=1')->fetchColumn(),
    'exams' => (int) db()->query('SELECT COUNT(*) FROM exams')->fetchColumn(),
    'published' => (int) db()->query("SELECT COUNT(*) FROM exams WHERE status='published'")->fetchColumn(),
    'attempts' => (int) db()->query("SELECT COUNT(*) FROM attempts WHERE status IN ('submitted','reviewed') AND revoked_at IS NULL")->fetchColumn(),
    'pending_short' => (int) db()->query("SELECT COUNT(*) FROM answers a JOIN questions q ON q.id=a.question_id JOIN attempts at ON at.id=a.attempt_id WHERE q.question_type='short' AND a.awarded_score IS NULL AND at.revoked_at IS NULL")->fetchColumn(),
];
$avgScore = (float) (db()->query("SELECT COALESCE(AVG(percentage),0) FROM attempts WHERE status IN ('submitted','reviewed') AND revoked_at IS NULL")->fetchColumn() ?: 0);
$recentStmt = db()->query("SELECT e.id,e.title,e.status,e.created_at,s.title subject_title,c.title class_title,(SELECT COUNT(*) FROM questions q WHERE q.exam_id=e.id) question_count,(SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id AND a.revoked_at IS NULL) attempt_count FROM exams e LEFT JOIN subjects s ON s.id=e.subject_id LEFT JOIN classes c ON c.id=e.class_id ORDER BY e.id DESC LIMIT 8");
$recentExams = $recentStmt->fetchAll();
$title = 'داشبورد مدیر';
require __DIR__ . '/../partials/header.php';
?>
<div class="admin-hero"><div><span class="eyebrow">مرکز کنترل سامانه</span><h1>داشبورد مدیر</h1><p>نمای کلی کاربران، محتوای آموزشی، آزمون‌ها و عملکرد سامانه.</p></div><a class="btn btn-light" href="<?= e(url('admin/backup.php')) ?>">پشتیبان‌گیری</a></div>
<section class="admin-quick-area" aria-labelledby="admin-quick-title">
    <div class="section-heading admin-quick-heading"><div><span class="eyebrow">دسترسی سریع</span><h2 id="admin-quick-title">عملیات مدیریتی</h2><p class="muted">ابزارهای پرکاربرد مدیریت سامانه را از اینجا سریع باز کنید.</p></div><span class="admin-quick-hint">۹ میانبر کاربردی</span></div>
    <div class="admin-action-grid">
        <a class="admin-action-card action-users" href="<?= e(url('admin/users.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">کاربران</span><h3>مدیریت کاربران</h3><p>افزودن، ویرایش و تعیین نقش دانش‌آموزان و معلمان.</p><span class="action-link">ورود به کاربران ←</span></a>
        <a class="admin-action-card action-classes" href="<?= e(url('admin/classes.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">ساختار آموزشی</span><h3>مدیریت کلاس‌ها</h3><p>ساخت کلاس، عضویت دانش‌آموزان و انتساب معلمان.</p><span class="action-link">مشاهده کلاس‌ها ←</span></a>
        <a class="admin-action-card action-subjects" href="<?= e(url('admin/subjects.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">محتوای آموزشی</span><h3>مدیریت درس‌ها</h3><p>تعریف درس‌ها و آماده‌سازی آن‌ها برای کلاس‌ها.</p><span class="action-link">مدیریت درس‌ها ←</span></a>
        <a class="admin-action-card action-exams" href="<?= e(url('admin/exams.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">ارزیابی</span><h3>مدیریت آزمون‌ها</h3><p>مشاهده، بررسی و مدیریت همه آزمون‌های سامانه.</p><span class="action-link">مشاهده آزمون‌ها ←</span></a>
        <a class="admin-action-card action-question-bank" href="<?= e(url('admin/question_bank.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">محتوای سؤال</span><h3>بانک سؤال</h3><p>مدیریت سؤال‌های تستی، صحیح/غلط و تشریحی.</p><span class="action-link">ورود به بانک سؤال ←</span></a>
        <a class="admin-action-card action-reports" href="<?= e(url('admin/analytics.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">تحلیل داده</span><h3>گزارش جامع</h3><p>تحلیل عملکرد کلاس‌ها، دانش‌آموزان و آزمون‌ها.</p><span class="action-link">مشاهده گزارش‌ها ←</span></a>
        <a class="admin-action-card action-performance" href="<?= e(url('admin/performance.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">پایش پیشرفت</span><h3>عملکرد دانش‌آموزان</h3><p>رتبه‌بندی و بررسی روند پیشرفت یادگیرندگان.</p><span class="action-link">مشاهده عملکرد ←</span></a>
        <a class="admin-action-card action-audit" href="<?= e(url('admin/audit_logs.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">امنیت سامانه</span><h3>لاگ فعالیت‌ها</h3><p>پیگیری رویدادها و فعالیت‌های مهم مدیریتی.</p><span class="action-link">مشاهده لاگ‌ها ←</span></a>
        <a class="admin-action-card action-create" href="<?= e(url('teacher/exams.php')) ?>"><span class="admin-card-icon" aria-hidden="true"></span><span class="action-kicker">اقدام سریع</span><h3>ساخت آزمون جدید</h3><p>برای یک کلاس و درس، آزمون تازه ایجاد کنید.</p><span class="action-link">شروع ساخت آزمون ←</span></a>
    </div>
</section>
<div class="admin-stat-grid">
    <div class="admin-stat-card admin-stat-users"><div class="admin-stat-label">کل کاربران</div><strong><?= number_format($summary['users']) ?></strong><span><?= number_format($summary['students']) ?> دانش‌آموز و <?= number_format($summary['teachers']) ?> معلم فعال</span></div>
    <div class="admin-stat-card admin-stat-classes"><div class="admin-stat-label">کلاس‌ها</div><strong><?= number_format($summary['classes']) ?></strong><span>کلاس ثبت‌شده در سامانه</span></div>
    <div class="admin-stat-card admin-stat-subjects"><div class="admin-stat-label">درس‌های فعال</div><strong><?= number_format($summary['subjects']) ?></strong><span>محتوای آموزشی قابل استفاده</span></div>
    <div class="admin-stat-card admin-stat-exams"><div class="admin-stat-label">آزمون‌ها</div><strong><?= number_format($summary['exams']) ?></strong><span><?= number_format($summary['published']) ?> آزمون منتشرشده</span></div>
    <div class="admin-stat-card admin-stat-attempts"><div class="admin-stat-label">دفعات شرکت</div><strong><?= number_format($summary['attempts']) ?></strong><span>تلاش معتبر ثبت‌شده</span></div>
    <div class="admin-stat-card admin-stat-average"><div class="admin-stat-label">میانگین عملکرد</div><strong><?= format_score($avgScore) ?>%</strong><span>بر اساس کارنامه‌های معتبر</span></div>
</div>
<section class="admin-section admin-attention"><div class="section-heading"><div><span class="eyebrow">نیازمند اقدام</span><h2>وضعیت بررسی</h2></div><span class="admin-quick-hint">نمای کلی امروز</span></div><div class="admin-attention-grid"><div class="admin-alert-stat"><strong><?= number_format($summary['pending_short']) ?></strong><div><span>پاسخ کوتاه در انتظار بررسی معلم</span><a class="btn btn-sm btn-primary" href="<?= e(url('teacher/results.php')) ?>">مشاهده نتایج</a></div></div><div class="admin-alert-stat"><strong><?= number_format($summary['published']) ?></strong><div><span>آزمون فعال برای دانش‌آموزان</span><a class="btn btn-sm btn-light" href="<?= e(url('admin/exams.php')) ?>">مدیریت آزمون‌ها</a></div></div></div></section>
<section class="admin-section"><div class="section-heading"><div><span class="eyebrow">نمای کلی</span><h2>آخرین آزمون‌ها</h2></div><a class="btn btn-sm btn-light" href="<?= e(url('admin/exams.php')) ?>">مشاهده همه</a></div><div class="card table-wrap"><table class="table"><thead><tr><th>آزمون</th><th>درس / کلاس</th><th>سؤال</th><th>تلاش</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php if (!$recentExams): ?><tr><td colspan="6" class="muted text-center">هنوز آزمونی ثبت نشده است.</td></tr><?php else: foreach ($recentExams as $exam): ?><tr><td><strong><?= e($exam['title']) ?></strong><br><small class="muted"><?= e($exam['created_at']) ?></small></td><td><?= e($exam['subject_title'] ?? '—') ?> / <?= e($exam['class_title'] ?? '—') ?></td><td><?= e((string) $exam['question_count']) ?></td><td><?= e((string) $exam['attempt_count']) ?></td><td><span class="badge <?= e($exam['status']) ?>"><?= e($exam['status']) ?></span></td><td><a class="btn btn-sm btn-light" href="<?= e(url('admin/exam_preview.php?id=' . $exam['id'])) ?>">پیش‌نمایش</a><a class="btn btn-sm btn-primary" href="<?= e(url('teacher/results.php?exam_id=' . $exam['id'])) ?>">نتایج</a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<section class="admin-section admin-note"><div><span class="eyebrow">پشتیبانی سامانه</span><h2>پشتیبان‌گیری منظم</h2><p class="muted">قبل از تغییرات مهم در آزمون‌ها یا کاربران، از دیتابیس و فایل‌های بارگذاری‌شده پشتیبان کامل بگیرید.</p></div><a class="btn btn-primary" href="<?= e(url('admin/backup.php')) ?>">دریافت پشتیبان</a></section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
