<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/docx_importer.php';
require_role('teacher','admin');
$examId = get_int('exam_id');
$s = db()->prepare('SELECT e.*,s.title subject_title,c.title class_title FROM exams e JOIN subjects s ON s.id=e.subject_id JOIN classes c ON c.id=e.class_id WHERE e.id=?');
$s->execute([$examId]);
$exam = $s->fetch();
if (!$exam) exit('آزمون پیدا نشد.');
$errors = [];
$imported = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!isset($_FILES['questions_file']) || $_FILES['questions_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'لطفاً یک فایل DOCX معتبر انتخاب کنید.';
    } else {
        $file = $_FILES['questions_file'];
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $mime = is_uploaded_file($file['tmp_name']) ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
        if ($ext !== 'docx') $errors[] = 'فقط فایل Word با پسوند DOCX پذیرفته می‌شود؛ فایل DOC قدیمی پشتیبانی نمی‌شود.';
        if ($file['size'] > 5 * 1024 * 1024) $errors[] = 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.';
        if (!is_uploaded_file($file['tmp_name'])) $errors[] = 'فایل آپلودشده معتبر نیست.';
        if (!in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true)) $errors[] = 'محتوای فایل با DOCX سازگار نیست.';
        if (!class_exists('ZipArchive')) $errors[] = 'افزونهٔ ZipArchive در PHP فعال نیست.';
        if (!$errors) {
            $zipCheck = new ZipArchive();
            if ($zipCheck->open($file['tmp_name']) !== true || $zipCheck->locateName('[Content_Types].xml') === false || $zipCheck->locateName('word/document.xml') === false) {
                $errors[] = 'ساختار فایل DOCX معتبر نیست.';
            }
            $zipCheck->close();
        }
        if (!$errors) {
            try {
                [$questions, $parseErrors] = parse_question_docx($file['tmp_name']);
                $errors = $parseErrors;
                if (!$errors && !$questions) $errors[] = 'هیچ بلوک QUESTION در فایل پیدا نشد.';
                if (!$errors) {
                    $pdo = db();
                    $pdo->beginTransaction();
                    $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM questions WHERE exam_id=?');
                    $orderStmt->execute([$examId]);
                    $order = (int)$orderStmt->fetchColumn();
                    foreach ($questions as $q) {
                        $order++;
                        $type = $q['type'] === 'MCQ' ? 'mcq' : ($q['type'] === 'TRUE_FALSE' ? 'true_false' : 'short');
                        $ins = $pdo->prepare('INSERT INTO questions (exam_id,question_text,question_type,score,sort_order,feedback,reference_answer,rubric) VALUES (?,?,?,?,?,?,?,?)');
                        $ins->execute([$examId,$q['text'],$type,$q['score'],$order,$q['feedback'],$q['reference_answer'],$q['rubric']]);
                        $questionId = (int)$pdo->lastInsertId();
                        $options = $type === 'true_false' ? ['A'=>'درست','B'=>'نادرست'] : ($type === 'mcq' ? $q['options'] : []);
                        $optionOrder = 0;
                        foreach ($options as $key => $text) {
                            $opt = $pdo->prepare('INSERT INTO question_options (question_id,option_text,option_key,is_correct,sort_order) VALUES (?,?,?,?,?)');
                            $opt->execute([$questionId,$text,$key,$key === $q['answer'] ? 1 : 0,$optionOrder++]);
                        }
                        $imported++;
                    }
                    $pdo->commit();
                    audit('import_questions','exam',$examId);
                    flash('success', $imported . ' پرسش با موفقیت وارد شد.');
                    redirect('teacher/exams.php?id='.$examId);
                }
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                app_log('DOCX import failed for exam ' . $examId, $e);
                $errors[] = 'پردازش فایل انجام نشد. قالب فایل و اطلاعات آن را بررسی کنید و دوباره تلاش نمایید.';
            }
        }
    }
}
$title='ورود گروهی پرسش‌ها'; require __DIR__.'/../partials/header.php';
?><h1>ورود گروهی پرسش‌ها از Word</h1><div class="card"><p>آزمون: <strong><?=e($exam['title'])?></strong> — درس: <?=e($exam['subject_title'])?> — کلاس: <?=e($exam['class_title'])?></p><div class="alert warning">فقط فایل <strong>.docx</strong> با الگوی مشخص سامانه پذیرفته می‌شود. فایل شما قبل از ثبت کامل اعتبارسنجی می‌شود؛ اگر خطایی وجود داشته باشد، هیچ پرسشی ثبت نخواهد شد.</div><?php if($errors):?><div class="alert danger"><strong>خطاهای فایل:</strong><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?><form method="post" enctype="multipart/form-data"><?=csrf_field()?><div class="form-group"><label for="questions_file">فایل Word</label><input id="questions_file" type="file" name="questions_file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required></div><button class="btn btn-success">اعتبارسنجی و ورود پرسش‌ها</button><a class="btn btn-light" href="<?=e(url('teacher/exams.php?id='.$examId))?>">بازگشت به آزمون</a></form></div><div class="card"><h2>الگوی فایل</h2><p>هر سؤال با خط <code>QUESTION</code> شروع و با خط <code>END</code> تمام شود. هر مشخصه در یک پاراگراف جداگانه با قالب <code>KEY: VALUE</code> نوشته شود.</p><pre>QUESTION
TYPE: MCQ
SCORE: 1
TEXT: پایتخت ایران کدام شهر است؟
A: تهران
B: شیراز
C: تبریز
D: اصفهان
ANSWER: A
FEEDBACK: پاسخ صحیح تهران است.
END

QUESTION
TYPE: TRUE_FALSE
SCORE: 1
TEXT: آب در دمای معمولی مایع است.
ANSWER: A
END

QUESTION
TYPE: SHORT
SCORE: 2
TEXT: دو مزیت مطالعهٔ منظم را بنویسید.
END</pre><p class="muted">در نوع TRUE_FALSE، مقدار A یعنی «درست» و B یعنی «نادرست». در نوع SHORT نیازی به گزینه و ANSWER نیست.</p></div><?php require __DIR__.'/../partials/footer.php'; ?>
