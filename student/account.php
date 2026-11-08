<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('student');

$uid=(int)current_user()['id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $current=(string)($_POST['current_password']??'');
    $new=(string)($_POST['new_password']??'');
    $confirm=(string)($_POST['new_password_confirmation']??'');
    $errors=[];
    if($current==='')$errors[]='رمز عبور فعلی را وارد کنید.';
    if(strlen($new)<6)$errors[]='رمز عبور جدید باید حداقل ۶ نویسه باشد.';
    if($new!==$confirm)$errors[]='تکرار رمز عبور جدید با آن یکسان نیست.';
    $q=db()->prepare('SELECT password_hash FROM users WHERE id=? AND role=? AND is_active=1');
    $q->execute([$uid,'student']);
    $user=$q->fetch();
    if(!$user)$errors[]='حساب کاربری پیدا نشد.';
    elseif($current!==''&&!password_verify($current,$user['password_hash']))$errors[]='رمز عبور فعلی صحیح نیست.';
    if(!$errors){
        $update=db()->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $update->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);
        audit('change_password','user',$uid);
        flash('success','رمز عبور شما با موفقیت تغییر کرد.');
        redirect('student/account.php');
    }
    flash('error',implode(' ',$errors));
}
$title='حساب کاربری دانش‌آموز';
require __DIR__.'/../partials/header.php';
?>
<div class="page-hero account-hero">
    <div><span class="eyebrow">امنیت حساب</span><h1>حساب کاربری من</h1><p>سلام <?=e(current_user()['full_name'])?>؛ از این بخش می‌توانید رمز عبور خود را تغییر دهید.</p></div>
</div>
<div class="card account-card">
    <h2>تغییر رمز عبور</h2>
    <p class="muted">برای حفظ امنیت، ابتدا رمز عبور فعلی و سپس رمز جدید را وارد کنید.</p>
    <form method="post" class="grid account-password-form" autocomplete="off">
        <?=csrf_field()?>
        <div class="form-group"><label for="current_password">رمز عبور فعلی</label><input id="current_password" name="current_password" type="password" minlength="6" required autocomplete="current-password"></div>
        <div class="form-group"><label for="new_password">رمز عبور جدید</label><input id="new_password" name="new_password" type="password" minlength="6" required autocomplete="new-password"><small class="muted">حداقل ۶ نویسه</small></div>
        <div class="form-group"><label for="new_password_confirmation">تکرار رمز عبور جدید</label><input id="new_password_confirmation" name="new_password_confirmation" type="password" minlength="6" required autocomplete="new-password"></div>
        <div><button class="btn btn-primary" type="submit">ذخیره رمز جدید</button> <a class="btn btn-light" href="<?=e(url('student/index.php'))?>">بازگشت به پنل</a></div>
    </form>
</div>
<?php require __DIR__.'/../partials/footer.php'; ?>
