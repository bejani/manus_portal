<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('teacher','admin');
$uid=(int)current_user()['id'];
$eid=get_int('id');
$ownerSql=current_user()['role']==='admin'?'':' AND e.created_by=?';
$params=current_user()['role']==='admin'?[$eid]:[$eid,$uid];
$s=db()->prepare("SELECT e.*,s.title subject_title,c.title class_title FROM exams e JOIN subjects s ON s.id=e.subject_id JOIN classes c ON c.id=e.class_id WHERE e.id=?$ownerSql");
$s->execute($params);$exam=$s->fetch();
if(!$exam){http_response_code(404);exit('آزمون پیدا نشد یا دسترسی ندارید.');}
$s=db()->prepare('SELECT q.*,GROUP_CONCAT(CONCAT(o.option_key,"::",o.option_text,"::",COALESCE(o.image_path,"")) ORDER BY o.sort_order SEPARATOR "||") option_data FROM questions q LEFT JOIN question_options o ON o.question_id=q.id WHERE q.exam_id=? GROUP BY q.id ORDER BY q.sort_order');
$s->execute([$eid]);$questions=$s->fetchAll();
$title='پیش‌نمایش آزمون';require __DIR__.'/../partials/header.php';
?><div class="card class-toolbar"><div><span class="eyebrow">پیش‌نمایش مدرس</span><h1><?=e($exam['title'])?></h1><p class="muted"><?=e($exam['subject_title'])?> — <?=e($exam['class_title'])?> — وضعیت: <?=e($exam['status'])?></p></div><a class="btn btn-light" href="<?=e(url('teacher/exams.php?id='.$eid))?>">بازگشت به مدیریت آزمون</a></div><div class="card"><p><?=e($exam['instructions'])?></p><p class="muted">مدت: <?=e($exam['duration_minutes'])?> دقیقه | نمره کل: <?=e($exam['total_score'])?> | تعداد پرسش: <?=count($questions)?></p></div><div class="card"><h2>نمایش سؤال‌ها</h2><?php if(!$questions):?><p class="muted">هنوز پرسشی به این آزمون اضافه نشده است.</p><?php endif;?><?php foreach($questions as $i=>$q):?><div class="question"><strong><?=($i+1).'. '.e($q['question_text'])?></strong><?php if(!empty($q['image_path'])):?><img class="question-image" src="<?=e(url($q['image_path']))?>" alt="تصویر سؤال"><?php endif;?><div class="muted">نوع: <?=e($q['question_type'])?> | نمره: <?=e($q['score'])?></div><?php if(in_array($q['question_type'],['mcq','true_false'],true)):?><div class="option-list"><?php foreach(explode('||',(string)$q['option_data']) as $raw):[$ok,$ot,$oi]=array_pad(explode('::',$raw,3),3,'');if($ot==='')continue;?><div class="option"><strong><?=e($ok)?>)</strong> <?=e($ot)?><?php if($oi!==''):?><img class="option-image" src="<?=e(url($oi))?>" alt="تصویر گزینه"><?php endif;?></div><?php endforeach;?></div><?php else:?><div class="preview-answer">محل پاسخ کوتاه دانش‌آموز</div><?php endif;?></div><?php endforeach;?></div><?php require __DIR__.'/../partials/footer.php'; ?>
