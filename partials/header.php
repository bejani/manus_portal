<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$title = $title ?? $config['app']['name'];
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$adminNav = [
    ['admin/index.php', 'داشبورد'],
    ['admin/users.php', 'کاربران'],
    ['admin/classes.php', 'کلاس‌ها'],
    ['admin/subjects.php', 'درس‌ها'],
    ['admin/exams.php', 'آزمون‌ها'],
    ['admin/question_bank.php', 'بانک سؤال'],
    ['admin/analytics.php', 'گزارش‌ها'],
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> | <?= e($config['app']['name']) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>">
</head>
<body class="role-<?= e(current_user()['role'] ?? 'guest') ?>">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>"><?= e($config['app']['name']) ?></a>
        <?php if ($u = current_user()): ?>
            <div class="user-menu">
                <?php $unreadNotifications = function_exists('unread_notification_count') ? unread_notification_count((int)$u['id']) : 0; ?>
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
                    <?php foreach ($adminNav as [$path, $label]): ?>
                        <?php $isActive = $currentPage === basename($path); ?>
                        <a class="admin-nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <a class="admin-nav-more" href="<?= e(url('admin/audit_logs.php')) ?>">امنیت و پشتیبان‌گیری</a>
            </div>
        </nav>
    <?php endif; ?>
</header>
<main class="container page-content">
    <?php if ($msg = flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash('error')): ?><div class="alert danger"><?= e($msg) ?></div><?php endif; ?>
