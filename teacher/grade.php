<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_role('teacher','admin');
$attemptId = get_int('attempt_id');
$uid = (int)current_user()['id'];
$ownerFilter = current_user()['role'] === 'admin' ? '' : ' AND e.created_by=' . $uid;
$s = db()->prepare("SELECT a.*,e.title exam_title,e.total_score,e.created_by,e.id exam_id,u.full_name student_name,s.title subject_title FROM attempts a JOIN exams e ON e.id=a.exam_id JOIN users u ON u.id=a.student_id JOIN subjects s ON s.id=e.subject_id WHERE a.id=? $ownerFilter");
$s->execute([$attemptId]);
$attempt = $s->fetch();
if (!$attempt) exit('تلاش آزمون پیدا نشد یا شما مجوز داوری آن را ندارید.');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $pdo = db();
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT a.id answer_id,a.question_id,q.score,q.question_type FROM answers a JOIN questions q ON q.id=a.question_id WHERE a.attempt_id=?');
        $s->execute([$attemptId]); $answerRows = $s->fetchAll();
        foreach ($answerRows as $row) {
            if ($row['question_type'] !== 'short') continue;
            $answerId=(int)$row['answer_id']; $score=isset($_POST['score'][$answerId])?(float)$_POST['score'][$answerId]:0; $maxScore=(float)$row['score'];
            if($score<0||$score>$maxScore) throw new RuntimeException('نمرهٔ پاسخ تشریحی باید بین صفر و نمرهٔ کامل سؤال باشد.');
            $comment=mb_substr(trim((string)($_POST['comment'][$answerId]??'')),0,2000);
            $u=$pdo->prepare('UPDATE answers SET awarded_score=?,teacher_comment=?,is_correct=NULL WHERE id=? AND attempt_id=?');$u->execute([$score,$comment!==''?$comment:null,$answerId,$attemptId]);
        }
        $sum=$pdo->prepare('SELECT COALESCE(SUM(awarded_score),0) FROM answers WHERE attempt_id=?');$sum->execute([$attemptId]);$score=(float)$sum->fetchColumn();
        $max=(float)$pdo->query('SELECT COALESCE(SUM(score),0) FROM questions WHERE exam_id='.(int)$attempt['exam_id'])->fetchColumn();
        $pending=$pdo->prepare("SELECT COUNT(*) FROM answers a JOIN questions q ON q.id=a.question_id WHERE a.attempt_id=? AND q.question_type='short' AND a.awarded_score IS NULL");$pending->execute([$attemptId]);$status=(int)$pending->fetchColumn()===0?'reviewed':'submitted';$percentage=$max>0?($score/$max*100):0;
        $u=$pdo->prepare('UPDATE attempts SET score=?,percentage=?,status=? WHERE id=?');$u->execute([$score,$percentage,$status,$attemptId]);$pdo->commit();audit('grade_short_answers','attempt',$attemptId);flash('success','نمره و بازخورد پاسخ‌های تشریحی ذخیره شد.');redirect('teacher/grade.php?attempt_id='.$attemptId);
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());}
}
$s=db()->prepare('SELECT a.id answer_id,a.answer_text,a.awarded_score,a.teacher_comment,q.question_text,q.score,q.question_type,q.reference_answer,q.rubric FROM answers a JOIN questions q ON q.id=a.question_id WHERE a.attempt_id=? AND q.question_type="short" ORDER BY q.sort_order');$s->execute([$attemptId]);$answers=$s->fetchAll();
foreach($answers as &$answer){$answer['suggestion']=local_grade_suggestion((string)($answer['answer_text']??''),(string)($answer['rubric']??''),(float)$answer['score']);}unset($answer);
$title='داوری پاسخ‌های تشریحی';require __DIR__.'/../partials/header.php';
?><h1>داوری پاسخ‌های تشریحی</h1><div class="card"><p>آزمون: <strong><?=e($attempt['exam_title'])?></strong> — دانش‌آموز: <strong><?=e($attempt['student_name'])?></strong></p><p class="muted">نمرهٔ فعلی: <?=format_score($attempt['score'])?> | درصد: <?=format_score($attempt['percentage'])?>% | وضعیت: <?=e($attempt['status'])?></p><div class="alert warning">نمرهٔ پیشنهادی فقط بر اساس تطبیق محلی عبارت‌های معیار محاسبه شده و هیچ داده‌ای از سامانه خارج نمی‌شود. تصمیم نهایی با معلم است.</div></div><?php if(!$answers):?><div class="card"><p>این آزمون پاسخ تشریحی ندارد.</p></div><?php else:?><form method="post"><?=csrf_field()?><?php foreach($answers as $i=>$answer):$suggestion=$answer['suggestion'];?><div class="card question"><h2>پرسش <?=($i+1)?></h2><p><strong><?=e($answer['question_text'])?></strong></p><?php if($answer['reference_answer']):?><div class="alert" style="background:#eef6ff"><strong>پاسخ مرجع:</strong><br><?=nl2br(e($answer['reference_answer']))?></div><?php endif;?><div class="alert" style="background:#f7f9fc"><strong>پاسخ دانش‌آموز:</strong><br><?=nl2br(e($answer['answer_text']??'بدون پاسخ'))?></div><?php if($answer['rubric']):?><div class="alert success"><strong>نمرهٔ پیشنهادی محلی: <?=format_score($suggestion['score'])?> از <?=format_score($answer['score'])?></strong><?php if($suggestion['matched']):?><br>معیارهای یافت‌شده: <?=e(implode('، ',$suggestion['matched']))?><?php endif;?><?php if($suggestion['missing']):?><br>معیارهای یافت‌نشده: <?=e(implode('، ',$suggestion['missing']))?><?php endif;?></div><?php else:?><div class="alert warning">برای این سؤال rubric تعریف نشده است؛ نمرهٔ پیشنهادی صفر است.</div><?php endif;?><div class="grid grid-2"><div class="form-group"><label>نمرهٔ نهایی معلم (از <?=format_score($answer['score'])?>)</label><input type="number" name="score[<?=$answer['answer_id']?>]" min="0" max="<?=e($answer['score'])?>" step="0.01" value="<?=e($answer['awarded_score']!==null?$answer['awarded_score']:$suggestion['score'])?>" required></div><div class="form-group"><label>بازخورد معلم</label><textarea name="comment[<?=$answer['answer_id']?>]" placeholder="نکته یا راهنمایی برای دانش‌آموز..."><?=e($answer['teacher_comment'])?></textarea></div></div></div><?php endforeach;?><button class="btn btn-success" type="submit">تأیید نمره و ذخیرهٔ بازخورد</button><a class="btn btn-light" href="<?=e(url('teacher/results.php?exam_id='.$attempt['exam_id']))?>">بازگشت به نتایج</a></form><?php endif;?><?php require __DIR__.'/../partials/footer.php'; ?>
