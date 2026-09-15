<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
session_name($config['app']['session_name'] ?? 'student_exam_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db']['host'], $config['db']['name'], $config['db']['charset']);
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function text_substr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) return $length === null ? mb_substr($value, $start, null, 'UTF-8') : mb_substr($value, $start, $length, 'UTF-8');
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}
function text_lower(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value); }
function text_contains(string $haystack, string $needle): bool
{
    return $needle === '' || (function_exists('mb_stripos') ? mb_stripos($haystack, $needle, 0, 'UTF-8') !== false : stripos($haystack, $needle) !== false);
}
function url(string $path = ''): string { global $config; return rtrim($config['app']['base_url'], '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path))); exit; }
function flash(string $key, ?string $value = null): ?string { if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; } $v = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $v; }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(419); exit('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.'); } }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function login_user(array $user): void { session_regenerate_id(true); $_SESSION['user'] = ['id'=>(int)$user['id'], 'role'=>$user['role'], 'full_name'=>$user['full_name'], 'mobile'=>$user['mobile']]; try { audit('login', 'user', (int)$user['id']); } catch (Throwable $e) { /* لاگ نباید مانع ورود شود */ } }
function logout_user(): void { $_SESSION = []; if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); } session_destroy(); }
function require_login(): void { if (!current_user()) redirect('login.php'); }
function require_role(string ...$roles): void { require_login(); if (!in_array(current_user()['role'], $roles, true)) { http_response_code(403); exit('شما مجوز دسترسی به این بخش را ندارید.'); } }
function audit(string $action, ?string $entityType = null, ?int $entityId = null): void
{
    try {
        $u = current_user();
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$u['id'] ?? null, $action, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        app_log('Audit write failed: ' . $action, $e);
    }
}
function app_log(string $message, ?Throwable $exception = null): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return;
    $detail = $exception ? ' | ' . $exception::class . ': ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine() : '';
    error_log('[' . date('c') . '] ' . $message . $detail . ' | ip=' . ($_SERVER['REMOTE_ADDR'] ?? '-') . PHP_EOL, 3, $dir . '/app.log');
}
function notify_user(int $userId, string $title, ?string $message = null, ?string $link = null, string $type = 'info'): void
{
    if ($userId <= 0 || trim($title) === '') return;
    try {
        $stmt = db()->prepare('INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, text_substr($type, 0, 50), text_substr($title, 0, 200), $message !== null ? text_substr($message, 0, 5000) : null, $link !== null ? text_substr($link, 0, 500) : null]);
    } catch (Throwable $e) { app_log('Notification write failed for user ' . $userId, $e); }
}
function unread_notification_count(int $userId): int
{
    try { $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0'); $stmt->execute([$userId]); return (int)$stmt->fetchColumn(); }
    catch (Throwable $e) { app_log('Notification count failed for user ' . $userId, $e); return 0; }
}
function post_string(string $key, int $max = 10000): string { return text_substr(trim((string)($_POST[$key] ?? '')), 0, $max); }
function normalize_mobile(string $mobile): string { $mobile = strtr($mobile, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']); return preg_replace('/\D+/', '', $mobile) ?? ''; }
function get_int(string $key): int { return max(0, (int)($_GET[$key] ?? 0)); }
function format_score($score): string { return number_format((float)$score, 2, '.', ''); }

function local_grade_suggestion(string $answer, ?string $rubric, float $maxScore): array {
    $suggested = 0.0; $matched = []; $missing = [];
    $answerNorm = text_lower(trim($answer));
    $lines = preg_split('/\r\n|\r|\n/', (string)$rubric) ?: [];
    foreach ($lines as $line) {
        $line = trim($line); if ($line === '' || !str_contains($line, '|')) continue;
        [$criterion, $pointsText] = array_map('trim', explode('|', $line, 2));
        $points = (float)$pointsText; if ($criterion === '' || $points <= 0) continue;
        if (text_contains($answerNorm, text_lower($criterion)) ) { $suggested += $points; $matched[] = $criterion; }
        else { $missing[] = $criterion; }
    }
    return ['score'=>min($maxScore, max(0, $suggested)), 'matched'=>$matched, 'missing'=>$missing];
}

function upload_question_image(string $field, string $subdir = 'question_media'): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('آپلود تصویر انجام نشد.');
    if ((int)$file['size'] > 2 * 1024 * 1024) throw new RuntimeException('حجم تصویر نباید بیشتر از ۲ مگابایت باشد.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) throw new RuntimeException('فرمت تصویر مجاز نیست. فقط JPG، PNG، GIF و WEBP پذیرفته می‌شود.');
    global $config;
    $root = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) throw new RuntimeException('پوشهٔ ذخیره‌سازی تصویر ساخته نشد.');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $root . '/' . $name)) throw new RuntimeException('ذخیرهٔ تصویر انجام نشد.');
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}
function media_url(?string $path): string { return $path ? url($path) : ''; }

function store_question_image_bytes(string $data, string $extension, string $subdir = 'question_media'): ?string
{
    $allowed = ['jpg'=>'jpg','jpeg'=>'jpg','png'=>'png','gif'=>'gif','webp'=>'webp'];
    $extension = strtolower($extension); if (!isset($allowed[$extension]) || strlen($data) > 2 * 1024 * 1024) return null;
    if (@getimagesizefromstring($data) === false) return null;
    $root = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) throw new RuntimeException('پوشهٔ ذخیره‌سازی تصویر ساخته نشد.');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$extension];
    if (file_put_contents($root . '/' . $name, $data) === false) throw new RuntimeException('ذخیرهٔ تصویر انجام نشد.');
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}
function named_docx_image(array $images, int $number, ?string $suffix = null): ?string
{
    $base = 'q' . $number . ($suffix ? '_' . strtoupper($suffix) : '');
    foreach ($images as $name => $image) if (strtolower(pathinfo($name, PATHINFO_FILENAME)) === strtolower($base)) return store_question_image_bytes($image['data'], $image['extension']);
    return null;
}
