<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_login();
$uid = (int) current_user()['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0');
    $stmt->execute([$uid]);
    flash('success', 'همهٔ اعلان‌ها خوانده‌شده علامت‌گذاری شدند.');
    redirect('notifications.php');
}
$stmt = db()->prepare('SELECT id, type, title, message, link, is_read, created_at, read_at FROM notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 100');
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll();
$title = 'اعلان‌ها';
require __DIR__ . '/partials/header.php';
?>
<div class="class-toolbar card"><div><span class="eyebrow">مرکز اطلاع‌رسانی</span><h1>اعلان‌های من</h1><p class="muted">آخرین اطلاع‌رسانی‌های مربوط به آزمون‌ها، نمرات و بازخوردها.</p></div><form method="post"><?= csrf_field() ?><button class="btn btn-light" type="submit">خوانده‌شدن همه</button></form></div>
<div class="card notification-list">
<?php if (!$notifications): ?>
    <div class="empty-state"><h2>اعلانی وجود ندارد</h2><p class="muted">اعلان‌های جدید در این بخش نمایش داده می‌شوند.</p></div>
<?php else: foreach ($notifications as $notification): ?>
    <article class="notification-item <?= $notification['is_read'] ? 'is-read' : 'is-unread' ?>">
        <div class="notification-item-main"><div class="notification-item-title"><span class="notification-dot"></span><strong><?= e($notification['title']) ?></strong></div><?php if ($notification['message']): ?><p><?= nl2br(e($notification['message'])) ?></p><?php endif; ?><small class="muted"><?= e($notification['created_at']) ?></small></div>
        <?php if ($notification['link']): ?><a class="btn btn-sm btn-primary" href="<?= e(url($notification['link'])) ?>">مشاهده</a><?php endif; ?>
    </article>
<?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
