<?php
require_once __DIR__ . '/lib/bootstrap.php';
if (current_user()) redirect('index.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $mobile = preg_replace('/\D+/', '', post_string('mobile', 20));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE mobile = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$mobile]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user); audit('login'); redirect('index.php');
    }
    $error = 'شماره موبایل یا رمز عبور نادرست است.';
}
$title = 'ورود'; require __DIR__ . '/partials/header.php';
?><div class="card login-box"><h1>ورود به سامانه</h1><p class="muted">با شماره موبایل ثبت‌شده و رمز عبور وارد شوید.</p><?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?><form method="post" autocomplete="off"><?= csrf_field() ?><div class="form-group"><label for="mobile">شماره موبایل</label><input id="mobile" name="mobile" inputmode="tel" required placeholder="0912..." value="<?= e($_POST['mobile'] ?? '') ?>"></div><div class="form-group"><label for="password">رمز عبور</label><input id="password" name="password" type="password" required></div><button class="btn btn-primary" type="submit">ورود</button></form></div><?php require __DIR__ . '/partials/footer.php'; ?>
