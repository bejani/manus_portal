<?php
/**
 * پردازش فایل‌های XLSX ساده برای ورود گروهی کاربران؛ بدون نیاز به Composer.
 */
function user_excel_column_index(string $reference): int
{
    preg_match('/^[A-Z]+/i', $reference, $matches);
    $letters = strtoupper($matches[0] ?? 'A');
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }
    return max(0, $index - 1);
}

function user_excel_xml_value(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string)($cell['t'] ?? '');
    if ($type === 'inlineStr') {
        return trim((string)($cell->is->t ?? ''));
    }
    $value = trim((string)($cell->v ?? ''));
    if ($type === 's' && $value !== '' && isset($sharedStrings[(int)$value])) {
        return $sharedStrings[(int)$value];
    }
    return $value;
}

function user_excel_read_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('قابلیت ZipArchive روی PHP فعال نیست.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('فایل Excel قابل خواندن نیست.');
    }
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = @simplexml_load_string($sharedXml);
        if ($xml !== false) {
            foreach ($xml->si as $item) {
                $parts = [];
                foreach ($item->t as $text) $parts[] = (string)$text;
                foreach ($item->r as $run) $parts[] = (string)($run->t ?? '');
                $sharedStrings[] = implode('', $parts);
            }
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) throw new RuntimeException('برگهٔ اول فایل Excel پیدا نشد.');
    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false || !isset($sheet->sheetData->row)) {
        throw new RuntimeException('ساختار فایل Excel معتبر نیست.');
    }
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $values = [];
        foreach ($row->c as $cell) {
            $values[user_excel_column_index((string)$cell['r'])] = user_excel_xml_value($cell, $sharedStrings);
        }
        if ($values) {
            ksort($values);
            $max = max(array_keys($values));
            $normalized = array_fill(0, $max + 1, '');
            foreach ($values as $index => $value) $normalized[$index] = $value;
            $rows[] = $normalized;
        }
    }
    return $rows;
}

function user_excel_normalize_header(string $value): string
{
    $value = trim(strtolower($value));
    $value = str_replace(['‌', ' ', '-', '_'], '', $value);
    return $value;
}

function user_excel_header_map(array $header): array
{
    $aliases = [
        'full_name' => ['نام', 'نامونامخانوادگی', 'نامخانوادگی', 'fullname', 'name'],
        'mobile' => ['موبایل', 'شماره موبایل', 'شمارهتلفن', 'شمارهتماس', 'mobile', 'phone'],
        'role' => ['نقش', 'role'],
        'password' => ['رمز', 'رمزعبور', 'رمزعبوراولیه', 'password', 'pass'],
        'national_code' => ['کدملی', 'کدملی', 'nationalcode', 'nationalid'],
    ];
    $normalizedAliases = [];
    foreach ($aliases as $field => $items) {
        foreach ($items as $item) $normalizedAliases[user_excel_normalize_header($item)] = $field;
    }
    $map = [];
    foreach ($header as $index => $value) {
        $key = user_excel_normalize_header((string)$value);
        if (isset($normalizedAliases[$key])) $map[$normalizedAliases[$key]] = $index;
    }
    return $map;
}

function user_excel_role(string $value): ?string
{
    $value = trim(strtolower($value));
    return match ($value) {
        'student', 'دانشآموز', 'دانش آموز', 'دانش‌اموز' => 'student',
        'teacher', 'معلم', 'دبیر' => 'teacher',
        'admin', 'مدیر' => 'admin',
        default => null,
    };
}

function user_excel_rows(string $path, ?string $originalName = null): array
{
    $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));
    if ($extension === 'csv') {
        $rows = [];
        $handle = fopen($path, 'rb');
        while (($row = fgetcsv($handle, 0, ',')) !== false) $rows[] = array_map('trim', $row);
        fclose($handle);
        if (isset($rows[0][0])) $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        return $rows;
    }
    if ($extension !== 'xlsx') throw new RuntimeException('فقط فایل XLSX یا CSV پذیرفته می‌شود.');
    return user_excel_read_xlsx($path);
}
