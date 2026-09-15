<?php
require_once __DIR__ . '/../lib/bootstrap.php'; require_role('admin');

function backup_sql_dump(): string {
    $pdo = db(); $lines=[]; $lines[]='-- Student Exam System database backup'; $lines[]='-- Created: '.date('c'); $lines[]='SET NAMES utf8mb4;'; $lines[]='SET FOREIGN_KEY_CHECKS=0;';
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table){
        $quoted='`'.str_replace('`','``',$table).'`';
        $create=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_ASSOC); $createSql=$create['Create Table']??array_values($create)[1]??'';
        $lines[]='';$lines[]='DROP TABLE IF EXISTS '.$quoted.';';$lines[]=$createSql.';';
        $rows=$pdo->query('SELECT * FROM '.$quoted);$columns=$pdo->query('SHOW COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_COLUMN);
        foreach($rows as $row){$values=[];foreach($columns as $column){$value=$row[$column]??null;$values[]=$value===null?'NULL':$pdo->quote((string)$value);}$lines[]='INSERT INTO '.$quoted.' ('.implode(',',array_map(fn($c)=>'`'.str_replace('`','``',$c).'`',$columns)).') VALUES ('.implode(',',$values).');';}
    }
    $lines[]='SET FOREIGN_KEY_CHECKS=1;'; return implode("\n",$lines)."\n";
}
function add_directory_to_zip(ZipArchive $zip,string $source,string $prefix=''): void { if(!is_dir($source))return; $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));foreach($iterator as $file){if($file->isFile()){$relative=$prefix.str_replace('\\','/',substr($file->getPathname(),strlen($source)+1));$zip->addFile($file->getPathname(),$relative);}} }
$type=(string)($_GET['type']??'');
if($type==='sql'){audit('backup_sql_download','backup',null);$filename='student_exam_db_'.date('Ymd_His').'.sql';header('Content-Type: application/sql; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');echo backup_sql_dump();exit;}
if($type==='full'){audit('backup_full_download','backup',null);$tmp=tempnam(sys_get_temp_dir(),'student_exam_');$zip=new ZipArchive();if($zip->open($tmp,ZipArchive::OVERWRITE)!==true){http_response_code(500);exit('ساخت فایل پشتیبان ممکن نشد.');}$zip->addFromString('database.sql',backup_sql_dump());$zip->addFromString('BACKUP_README.txt',"Student Exam System backup\nCreated: ".date('c')."\nRestore database.sql with phpMyAdmin, then copy uploads/ into the application root.\n");add_directory_to_zip($zip,__DIR__.'/../uploads','uploads/');$zip->close();$filename='student_exam_full_'.date('Ymd_His').'.zip';header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');readfile($tmp);unlink($tmp);exit;}
$title='پشتیبان‌گیری';require __DIR__.'/../partials/header.php';
?><h1>پشتیبان‌گیری سامانه</h1><div class="card"><h2>قبل از شروع</h2><p>پشتیبان به‌صورت لحظه‌ای تولید می‌شود و روی سرور باقی نمی‌ماند. پس از دانلود، فایل را در رایانه یا فضای ذخیره‌سازی جداگانه نگهداری کنید.</p></div><div class="grid grid-2"><div class="card"><h2>پشتیبان دیتابیس</h2><p class="muted">کاربران، کلاس‌ها، درس‌ها، آزمون‌ها، پرسش‌ها، پاسخ‌ها، نمرات و تنظیمات دیتابیس.</p><a class="btn btn-primary" href="<?=e(url('admin/backup.php?type=sql'))?>">دانلود SQL دیتابیس</a></div><div class="card"><h2>پشتیبان کامل</h2><p class="muted">شامل SQL دیتابیس و پوشهٔ uploads برای فایل‌های بارگذاری‌شده.</p><a class="btn btn-success" href="<?=e(url('admin/backup.php?type=full'))?>">دانلود پشتیبان کامل ZIP</a></div></div><div class="card"><h2>بازیابی</h2><p>برای بازیابی، فایل SQL را در phpMyAdmin اجرا کنید و محتویات پوشهٔ uploads داخل بسته را در مسیر uploads پروژه کپی نمایید. قبل از بازیابی از وضعیت فعلی نیز یک پشتیبان جدید بگیرید.</p></div><?php require __DIR__.'/../partials/footer.php'; ?>
