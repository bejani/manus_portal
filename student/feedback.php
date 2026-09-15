<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('student');
$uid = (int) current_user()['id'];
$stmt = db()->prepare('SELECT f.*,a.full_name author_name,c.title class_title FROM feedback_notes f JOIN users a ON a.id=f.author_id LEFT JOIN classes c ON c.id=f.class_id WHERE f.student_id=? AND f.is_visible=1 ORDER BY f.created_at DESC,f.id DESC');
$stmt->execute([$uid]); $notes = $stmt->fetchAll();
$title='بازخوردهای آموزشی'; require __DIR__ . '/../partials/header.php';
?>
<div class="card class-toolbar"><div><span class="eyebrow">مسیر پیشرفت</span><h1>بازخوردهای آموزشی من</h1><p class="muted">نکات و برنامه‌های تمرینی ثبت‌شده توسط معلم یا مدیر.</p></div><a class="btn btn-light" href="<?= e(url('student/index.php')) ?>">بازگشت به پنل</a></div>
<div class="feedback-list"><?php if (!$notes): ?><div class="card student-empty"><h2>هنوز بازخوردی ثبت نشده است</h2><p class="muted">پس از بررسی عملکرد شما، بازخوردهای آموزشی در این بخش نمایش داده می‌شوند.</p></div><?php else: foreach ($notes as $note): ?><article class="card feedback-card priority-<?= e($note['priority']) ?>"><div class="section-heading"><div><span class="eyebrow"><?= e($note['priority']) ?></span><h2><?= e($note['title']) ?></h2></div><small class="muted"><?= e($note['created_at']) ?></small></div><p><?= nl2br(e($note['body'])) ?></p><p class="muted">ثبت‌شده توسط <?= e($note['author_name']) ?><?php if ($note['class_title']): ?> — کلاس <?= e($note['class_title']) ?><?php endif; ?></p></article><?php endforeach; endif; ?></div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
