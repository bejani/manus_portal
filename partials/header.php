<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$title = $title ?? $config['app']['name'];
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$adminNav = [
    ['admin/index.php', '⌂', 'داشبورد'],
    ['admin/users.php', '♙', 'کاربران'],
    ['admin/classes.php', '▦', 'کلاس‌ها'],
    ['admin/subjects.php', '◫', 'درس‌ها'],
    ['admin/exams.php', '✎', 'آزمون‌ها'],
    ['admin/question_bank.php', '?', 'بانک سؤال'],
    ['admin/analytics.php', '▥', 'گزارش‌ها'],
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> | <?= e($config['app']['name']) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/app.css').'?v='.(string)filemtime(__DIR__.'/../assets/app.css')) ?>">
</head>
<body class="role-<?= e(current_user()['role'] ?? 'guest') ?><?= $currentPage === 'exams.php' ? ' page-exams' : '' ?>">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>"><?= e($config['app']['name']) ?></a>
        <?php if ($u = current_user()): ?>
            <div class="user-menu">
                <?php $unreadNotifications = function_exists('unread_notification_count') ? unread_notification_count((int)$u['id']) : 0; ?>
                <?php if (($u['role'] ?? '') === 'student'): ?><a class="account-header-link" href="<?= e(url('student/account.php')) ?>">حساب کاربری</a><?php endif; ?>
                <a class="notification-link" href="<?= e(url('notifications.php')) ?>">اعلان‌ها<?php if ($unreadNotifications > 0): ?><span class="notification-badge"><?= e((string)$unreadNotifications) ?></span><?php endif; ?></a>
                <span><?= e($u['full_name']) ?> — <?= e($u['role']) ?></span>
                <a class="btn btn-light btn-sm" href="<?= e(url('logout.php')) ?>">خروج</a>
            </div>
        <?php endif; ?>
    </div>
    <?php if (($u['role'] ?? '') === 'admin'): ?>
        <nav class="admin-nav" aria-label="ناوبری مدیریت">
            <div class="container admin-nav-inner">
                <span class="admin-nav-label">مدیریت سامانه</span>
                <div class="admin-nav-links">
                    <?php foreach ($adminNav as [$path, $icon, $label]): ?>
                        <?php $isActive = $currentPage === basename($path); ?>
                        <a class="admin-nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><span class="admin-nav-icon" aria-hidden="true"><?= e($icon) ?></span><span><?= e($label) ?></span></a>
                    <?php endforeach; ?>
                </div>
                <a class="admin-nav-more" href="<?= e(url('admin/audit_logs.php')) ?>"><span class="admin-nav-icon" aria-hidden="true">⚙</span><span>امنیت و پشتیبان‌گیری</span></a>
            </div>
        </nav>
    <?php endif; ?>
</header>
<main class="container page-content">
    <?php
    $flashDefinitions = [
        'success' => ['title' => 'با موفقیت انجام شد', 'icon' => '✓', 'role' => 'status', 'autoHide' => true],
        'error' => ['title' => 'انجام عملیات ممکن نشد', 'icon' => '!', 'role' => 'alert', 'autoHide' => false],
        'warning' => ['title' => 'نیاز به توجه', 'icon' => '!', 'role' => 'alert', 'autoHide' => false],
        'info' => ['title' => 'اطلاع‌رسانی', 'icon' => 'i', 'role' => 'status', 'autoHide' => true],
    ];
    $flashMessages = [];
    foreach ($flashDefinitions as $flashType => $flashMeta) {
        if ($flashText = flash($flashType)) $flashMessages[] = ['type' => $flashType, 'text' => $flashText] + $flashMeta;
    }
    ?>
    <?php if ($flashMessages): ?>
        <div class="flash-stack" aria-label="پیام‌های سامانه">
            <?php foreach ($flashMessages as $flashItem): ?>
                <div class="alert flash-alert <?= $flashItem['type'] === 'error' ? 'danger' : e($flashItem['type']) ?>" role="<?= e($flashItem['role']) ?>"<?= $flashItem['autoHide'] ? ' data-autohide="true"' : '' ?>>
                    <span class="flash-alert-icon" aria-hidden="true"><?= e($flashItem['icon']) ?></span>
                    <div class="flash-alert-copy"><strong><?= e($flashItem['title']) ?></strong><p><?= e($flashItem['text']) ?></p></div>
                    <button type="button" class="flash-alert-close" data-dismiss-alert aria-label="بستن پیام">×</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
