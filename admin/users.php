<?php
require_once __DIR__ . '/../lib/bootstrap.php'; require_once __DIR__ . '/../lib/user_excel_importer.php'; require_role('admin');
if (($_GET['download'] ?? '') === 'users_template') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename=users_template.csv');
  echo "\xEF\xBB\xBF";
  echo "نام و نام خانوادگی,موبایل,نقش,رمز عبور,کد ملی\n";
  echo "علی رضایی,09121234567,student,123456,0012345678\n";
  echo "مریم احمدی,09129876543,teacher,123456,\n";
  exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf(); $action=post_string('action');
  if ($action==='import_users') {
    $file=$_FILES['users_file']??null; $errors=[]; $imported=0; $skipped=0;
    if (!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) $errors[]='فایل کاربران انتخاب نشده یا بارگذاری آن ناموفق بود.';
    elseif ((int)$file['size']>5*1024*1024) $errors[]='حجم فایل نباید بیشتر از ۵ مگابایت باشد.';
    else {
      try {
        $rows=user_excel_rows((string)$file['tmp_name']);
        if (count($rows)<2) throw new RuntimeException('فایل حداقل باید شامل یک ردیف عنوان و یک کاربر باشد.');
        $map=user_excel_header_map($rows[0]);
        foreach(['full_name','mobile','role','password'] as $required) if(!array_key_exists($required,$map)) $errors[]='ستون الزامی «'.$required.'» در فایل پیدا نشد.';
        if (!$errors) {
          $pdo=db(); $pdo->beginTransaction(); $seen=[];
          foreach(array_slice($rows,1) as $rowNumber=>$row) {
            $excelRow=$rowNumber+2; $value=function($field)use($map,$row){return isset($map[$field])?trim((string)($row[$map[$field]]??'')):'';};
            $name=$value('full_name'); $mobile=normalize_mobile($value('mobile')); $role=user_excel_role($value('role')); $pass=$value('password'); $national=$value('national_code'); $rowErrors=[];
            if($name==='')$rowErrors[]='نام خالی است';
            if(strlen($mobile)<10||strlen($mobile)>15)$rowErrors[]='شماره موبایل معتبر نیست';
            if(!$role)$rowErrors[]='نقش معتبر نیست';
            if(strlen($pass)<6)$rowErrors[]='رمز کمتر از ۶ نویسه است';
            if(isset($seen[$mobile]))$rowErrors[]='شماره موبایل در همین فایل تکراری است';
            $seen[$mobile]=true;
            if($rowErrors){$skipped++;if(count($errors)<12)$errors[]='ردیف '.$excelRow.': '.implode('، ',$rowErrors);continue;}
            try{$q=$pdo->prepare('INSERT INTO users(role,full_name,mobile,password_hash,national_code) VALUES(?,?,?,?,?)');$q->execute([$role,$name,$mobile,password_hash($pass,PASSWORD_DEFAULT),$national!==''?$national:null]);$newId=(int)$pdo->lastInsertId();audit('import_create','user',$newId);$imported++;}
            catch(PDOException $e){$skipped++;if(count($errors)<12)$errors[]='ردیف '.$excelRow.': شماره موبایل تکراری است یا ثبت نشد.';}
          }
          $pdo->commit();
          if($imported>0)flash('success','ورود گروهی انجام شد: '.$imported.' کاربر ثبت و '.$skipped.' ردیف رد شد.');
          if($errors)flash('error',implode(' | ',$errors).(count($errors)>=12?' | موارد بیشتر نیز رد شده‌اند.':''));
        }
      } catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$errors[]=$e->getMessage();}
    }
    if($errors && $imported===0)flash('error',implode(' | ',$errors));
  }
  if ($action==='create') { $role=post_string('role'); $name=post_string('full_name',150); $mobile=normalize_mobile(post_string('mobile',20)); $pass=(string)($_POST['password']??''); $validation=[]; if(!in_array($role,['admin','teacher','student'],true)) $validation[]='نقش کاربر معتبر نیست.'; if($name==='') $validation[]='نام و نام خانوادگی الزامی است.'; if(strlen($mobile)<10 || strlen($mobile)>15) $validation[]='شماره موبایل باید بین ۱۰ تا ۱۵ رقم باشد.'; if(strlen($pass)<6) $validation[]='رمز عبور باید حداقل ۶ نویسه باشد.'; if($validation){flash('error',implode(' ',$validation));}else{try{$s=db()->prepare('INSERT INTO users(role,full_name,mobile,password_hash) VALUES(?,?,?,?)');$s->execute([$role,$name,$mobile,password_hash($pass,PASSWORD_DEFAULT)]);audit('create','user',(int)db()->lastInsertId());flash('success','کاربر با موفقیت ایجاد شد.');}catch(PDOException $e){flash('error','شماره موبایل تکراری است یا ثبت کاربر انجام نشد.');}}}
  if ($action==='toggle') { $id=get_int('id'); if($id!==current_user()['id']){$s=db()->prepare('UPDATE users SET is_active=1-is_active WHERE id=?');$s->execute([$id]);audit('toggle_active','user',$id);flash('success','وضعیت کاربر تغییر کرد.');} }
  redirect('admin/users.php');
}
$search=trim((string)($_GET['q']??''));
$roleFilter=trim((string)($_GET['role']??''));
$statusFilter=(string)($_GET['status']??'');
$pageSize=(int)($_GET['page_size']??20);if(!in_array($pageSize,[20,50,100],true))$pageSize=20;
$where=[];$params=[];
if($search!==''){$where[]='(full_name LIKE ? OR mobile LIKE ?)';$like='%'.$search.'%';$params[]=$like;$params[]=$like;}
if(in_array($roleFilter,['admin','teacher','student'],true)){$where[]='role=?';$params[]=$roleFilter;}
if($statusFilter==='active'||$statusFilter==='inactive'){$where[]='is_active=?';$params[]=$statusFilter==='active'?1:0;}
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
$countStmt=db()->prepare('SELECT COUNT(*) FROM users'.$whereSql);$countStmt->execute($params);$totalUsers=(int)$countStmt->fetchColumn();
$totalPages=max(1,(int)ceil($totalUsers/$pageSize));$page=max(1,(int)($_GET['page']??1));if($page>$totalPages)$page=$totalPages;$offset=($page-1)*$pageSize;
$listStmt=db()->prepare('SELECT id,role,full_name,mobile,is_active,created_at FROM users'.$whereSql.' ORDER BY id DESC LIMIT '.(int)$pageSize.' OFFSET '.(int)$offset);$listStmt->execute($params);$users=$listStmt->fetchAll();
$queryParams=['q'=>$search,'role'=>$roleFilter,'status'=>$statusFilter,'page_size'=>$pageSize];$paginationBase='admin/users.php?'.http_build_query($queryParams);
$title='مدیریت کاربران';require __DIR__.'/../partials/header.php';
?><div class="class-toolbar user-management-toolbar"><div><span class="eyebrow">مدیریت دسترسی</span><h1>مدیریت کاربران</h1><details class="card add-user-collapsible"><summary class="add-user-summary"><span><strong>افزودن کاربر جدید</strong><small>ثبت دانش‌آموز، معلم یا مدیر</small></span><span class="add-user-toggle">باز کردن فرم +</span></summary><div class="add-user-form"><form method="post" class="grid grid-2"><?=csrf_field()?><input type="hidden" name="action" value="create"><div class="form-group"><label>نام و نام خانوادگی</label><input name="full_name" required></div><div class="form-group"><label>شماره موبایل</label><input name="mobile" inputmode="tel" required></div><div class="form-group"><label>نقش</label><select name="role"><option value="student">دانش‌آموز</option><option value="teacher">معلم</option><option value="admin">مدیر</option></select></div><div class="form-group"><label>رمز عبور اولیه</label><input name="password" type="password" minlength="6" required></div><div><button class="btn btn-primary">ثبت کاربر</button></div></form></div></details><details class="card import-users-collapsible"><summary class="add-user-summary"><span><strong>ورود گروهی کاربران از Excel</strong><small>ثبت چند دانش‌آموز یا معلم با یک فایل</small></span><span class="add-user-toggle">باز کردن فرم +</span></summary><div class="add-user-form"><p class="muted">ستون‌های الزامی: نام و نام خانوادگی، موبایل، نقش و رمز عبور. فایل XLSX یا CSV و حداکثر ۵ مگابایت.</p><a class="btn btn-light btn-sm" href="<?=e(url('admin/users.php?download=users_template'))?>">دانلود فایل الگو</a><form method="post" enctype="multipart/form-data" class="import-users-form"><?=csrf_field()?><input type="hidden" name="action" value="import_users"><input type="file" name="users_file" accept=".xlsx,.csv" required><button class="btn btn-success" type="submit">وارد کردن کاربران</button></form><small class="muted">نقش‌ها: student یا دانش‌آموز، teacher یا معلم، admin یا مدیر. شماره موبایل را به‌صورت متن وارد کنید تا صفر ابتدای آن حفظ شود.</small></div></details><p class="muted">جست‌وجو و فیلتر کاربران بر اساس نام، شماره موبایل، نقش و وضعیت.</p></div><form method="get" class="search-form user-search-form"><input name="q" value="<?=e($search)?>" placeholder="نام، نام خانوادگی یا شماره موبایل" aria-label="جست‌وجوی کاربر"><select name="role" aria-label="فیلتر نقش"><option value="">همه نقش‌ها</option><option value="student" <?= $roleFilter==='student'?'selected':'' ?>>دانش‌آموز</option><option value="teacher" <?= $roleFilter==='teacher'?'selected':'' ?>>معلم</option><option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>مدیر</option></select><select name="status" aria-label="فیلتر وضعیت"><option value="">همه وضعیت‌ها</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>فعال</option><option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>غیرفعال</option></select><select name="page_size" aria-label="تعداد نمایش"><option value="20" <?= $pageSize===20?'selected':'' ?>>۲۰ کاربر</option><option value="50" <?= $pageSize===50?'selected':'' ?>>۵۰ کاربر</option><option value="100" <?= $pageSize===100?'selected':'' ?>>۱۰۰ کاربر</option></select><button class="btn btn-primary" type="submit">جست‌وجو و فیلتر</button><?php if($search!==''||$roleFilter!==''||$statusFilter!==''):?><a class="btn btn-light filter-reset" href="<?=e(url('admin/users.php'))?>">پاک کردن فیلتر</a><?php endif;?></form></div><div class="card table-wrap user-results-card"><div class="user-results-head"><div><h2>فهرست کاربران</h2><span class="muted"><?=number_format($totalUsers)?> کاربر پیدا شد</span></div><?php if($totalPages>1):?><span class="muted">صفحه <?=number_format($page)?> از <?=number_format($totalPages)?></span><?php endif;?></div><table class="table"><thead><tr><th>نام</th><th>موبایل</th><th>نقش</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php if(!$users):?><tr><td colspan="5" class="muted text-center">کاربری با این مشخصات پیدا نشد.</td></tr><?php else:foreach($users as $u):?><tr><td><strong><?=e($u['full_name'])?></strong></td><td><?=e($u['mobile'])?></td><td><?= $u['role']==='student'?'دانش‌آموز':($u['role']==='teacher'?'معلم':'مدیر') ?></td><td><span class="badge <?=$u['is_active']?'success':'warning'?>"><?= $u['is_active']?'فعال':'غیرفعال' ?></span></td><td><a class="btn btn-sm btn-primary" href="<?=e(url('admin/user_edit.php?id='.$u['id']))?>">ویرایش / ریست رمز</a> <?php if((int)$u['id']!==(int)current_user()['id']):?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn btn-sm btn-light">تغییر وضعیت</button></form><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table><?php if($totalPages>1):?><nav class="pagination" aria-label="صفحه‌بندی کاربران"><div class="pagination-links"><?php if($page>1):?><a class="btn btn-light btn-sm" href="<?=e(url($paginationBase.'&page='.($page-1)))?>">قبلی</a><?php endif;?><?php for($pg=max(1,$page-2);$pg<=min($totalPages,$page+2);$pg++):?><a class="btn btn-sm <?=$pg===$page?'btn-primary':'btn-light'?>" href="<?=e(url($paginationBase.'&page='.$pg))?>"><?=$pg?></a><?php endfor;?><?php if($page<$totalPages):?><a class="btn btn-light btn-sm" href="<?=e(url($paginationBase.'&page='.($page+1)))?>">بعدی</a><?php endif;?></div></nav><?php endif;?></div><?php require __DIR__.'/../partials/footer.php'; ?>
