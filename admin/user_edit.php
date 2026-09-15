<?php
require_once __DIR__ . '/../lib/bootstrap.php'; require_role('admin');
$id=get_int('id');
$s=db()->prepare('SELECT id,role,full_name,mobile,is_active FROM users WHERE id=?');$s->execute([$id]);$user=$s->fetch();if(!$user)exit('کاربر پیدا نشد.');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$role=post_string('role');$name=post_string('full_name',150);$mobile=normalize_mobile(post_string('mobile',20));$pass=(string)($_POST['password']??'');$active=isset($_POST['is_active'])?1:0;$errors=[];
    if(!in_array($role,['admin','teacher','student'],true))$errors[]='نقش کاربر معتبر نیست.';
    if($name==='')$errors[]='نام و نام خانوادگی الزامی است.';
    if(strlen($mobile)<10||strlen($mobile)>15)$errors[]='شماره موبایل باید بین ۱۰ تا ۱۵ رقم باشد.';
    if($pass!==''&&strlen($pass)<6)$errors[]='رمز جدید باید حداقل ۶ نویسه باشد.';
    if((int)$id===(int)current_user()['id']){$role=current_user()['role'];$active=1;}
    if($errors){flash('error',implode(' ',$errors));}else{try{
        if($pass!==''){$q=db()->prepare('UPDATE users SET role=?,full_name=?,mobile=?,password_hash=?,is_active=? WHERE id=?');$q->execute([$role,$name,$mobile,password_hash($pass,PASSWORD_DEFAULT),$active,$id]);audit('reset_password','user',$id);}else{$q=db()->prepare('UPDATE users SET role=?,full_name=?,mobile=?,is_active=? WHERE id=?');$q->execute([$role,$name,$mobile,$active,$id]);}
        audit('update','user',$id);flash('success','اطلاعات کاربر با موفقیت به‌روزرسانی شد.');redirect('admin/users.php');
    }catch(PDOException $e){flash('error','شماره موبایل تکراری است یا ویرایش کاربر انجام نشد.');}}
}
$title='ویرایش کاربر';require __DIR__.'/../partials/header.php';
?><h1>ویرایش کاربر</h1><div class="card"><form method="post" class="grid grid-2"><?=csrf_field()?><div class="form-group"><label>نام و نام خانوادگی</label><input name="full_name" value="<?=e($user['full_name'])?>" required></div><div class="form-group"><label>شماره موبایل</label><input name="mobile" inputmode="tel" value="<?=e($user['mobile'])?>" required></div><div class="form-group"><label>نقش</label><select name="role" <?=((int)$id===(int)current_user()['id'])?'disabled':''?>><option value="student" <?=$user['role']==='student'?'selected':''?>>دانش‌آموز</option><option value="teacher" <?=$user['role']==='teacher'?'selected':''?>>معلم</option><option value="admin" <?=$user['role']==='admin'?'selected':''?>>مدیر</option></select><?php if((int)$id===(int)current_user()['id']):?><input type="hidden" name="role" value="<?=e($user['role'])?>"><?php endif;?></div><div class="form-group"><label>رمز جدید (اختیاری)</label><input name="password" type="password" minlength="6" placeholder="برای حفظ رمز فعلی خالی بگذارید"></div><div class="form-group"><label><input type="checkbox" name="is_active" value="1" <?=$user['is_active']?'checked':''?> <?=((int)$id===(int)current_user()['id'])?'disabled':''?>> کاربر فعال باشد</label></div><div><button class="btn btn-primary">ذخیره تغییرات</button> <a class="btn btn-light" href="<?=e(url('admin/users.php'))?>">انصراف</a></div></form></div><?php require __DIR__.'/../partials/footer.php'; ?>
