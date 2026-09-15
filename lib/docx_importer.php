<?php
declare(strict_types=1);

/**
 * DOCX importer
 *
 * نکته مهم:
 * - در فایل Word بهتر است هر خط سؤال، هر گزینه و پاسخ صحیح با Enter
 *   در یک پاراگراف جدا قرار بگیرد.
 * - این نسخه علاوه بر پاراگراف‌های Word، شکست‌خط‌های داخلی (Shift+Enter)
 *   را نیز به عنوان خط جداگانه تشخیص می‌دهد.
 * - قالب گزینه‌ها می‌تواند با یا بدون پرانتز باشد:
 *      الف گزینه اول
 *      ( الف گزینه اول
 *      الف) گزینه اول
 *      (الف) گزینه اول
 */

/**
 * متن پاراگراف‌های DOCX را استخراج می‌کند.
 *
 * شکست‌خط‌های داخلی w:br / w:cr نیز جدا می‌شوند تا استفاده از Shift+Enter
 * باعث چسبیدن سؤال، گزینه‌ها و پاسخ به یک خط نشود.
 */
function docx_paragraphs(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('افزونه ZipArchive در PHP فعال نیست.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('فایل DOCX قابل خواندن نیست.');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        throw new RuntimeException('ساختار فایل DOCX معتبر نیست.');
    }

    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        throw new RuntimeException('فایل XML داخلی DOCX معتبر نیست.');
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace(
        'w',
        'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
    );

    $result = [];

    foreach ($xpath->query('//w:body/w:p') as $p) {
        $current = '';

        /*
         * با پیمایش تمام گره‌های داخل پاراگراف، w:t و شکست‌خط‌های
         * w:br / w:cr را به ترتیب واقعی آن‌ها استخراج می‌کنیم.
         */
        foreach ($xpath->query('.//w:t | .//w:br | .//w:cr', $p) as $node) {
            $localName = $node->localName;

            if ($localName === 't') {
                $current .= $node->nodeValue ?? '';
            } elseif ($localName === 'br' || $localName === 'cr') {
                $result[] = trim($current);
                $current = '';
            }
        }

        // آخرین بخش پاراگراف
        $result[] = trim($current);
    }

    return $result;
}

/**
 * پارسر قالب KEY: VALUE
 *
 * قالب نمونه:
 * QUESTION
 * TYPE: MCQ
 * SCORE: 1
 * TEXT: ...
 * A: ...
 * B: ...
 * C: ...
 * D: ...
 * ANSWER: A
 * END
 */
function parse_question_docx(string $path): array
{
    $lines = docx_paragraphs($path);
    $questions = [];
    $errors = [];
    $block = null;
    $lineNo = 0;

    foreach ($lines as $line) {
        $lineNo++;
        $line = trim(preg_replace('/\x{FEFF}/u', '', $line) ?? '');
        if ($line === '') {
            continue;
        }

        $upper = strtoupper($line);

        if ($upper === 'QUESTION') {
            if ($block !== null) {
                $errors[] = "خط {$lineNo}: بلوک قبلی با END بسته نشده است.";
            }

            $block = [
                'type' => 'MCQ',
                'score' => 1,
                'text' => '',
                'options' => ['A' => '', 'B' => '', 'C' => '', 'D' => ''],
                'answer' => '',
                'feedback' => '',
                'reference_answer' => '',
                'rubric' => ''
            ];
            continue;
        }

        if ($upper === 'END') {
            if ($block === null) {
                $errors[] = "خط {$lineNo}: END بدون QUESTION.";
                continue;
            }

            $block['_line'] = $lineNo;
            $questions[] = $block;
            $block = null;
            continue;
        }

        if ($block === null) {
            continue;
        }

        if (!str_contains($line, ':')) {
            $errors[] = "خط {$lineNo}: قالب باید KEY: VALUE باشد.";
            continue;
        }

        [$key, $value] = array_map('trim', explode(':', $line, 2));
        $key = strtoupper($key);

        if ($key === 'TYPE') {
            $block['type'] = strtoupper($value);
        } elseif ($key === 'SCORE') {
            $block['score'] = (float)$value;
        } elseif ($key === 'TEXT') {
            $block['text'] = $value;
        } elseif (in_array($key, ['A', 'B', 'C', 'D'], true)) {
            $block['options'][$key] = $value;
        } elseif ($key === 'ANSWER') {
            $block['answer'] = strtoupper($value);
        } elseif ($key === 'FEEDBACK') {
            $block['feedback'] = $value;
        } elseif ($key === 'REFERENCE') {
            $block['reference_answer'] = $value;
        } elseif ($key === 'RUBRIC') {
            $block['rubric'] = $value;
        } else {
            $errors[] = "خط {$lineNo}: کلید ناشناختهٔ {$key}.";
        }
    }

    if ($block !== null) {
        $errors[] = 'آخرین بلوک با END بسته نشده است.';
    }

    foreach ($questions as $i => $q) {
        $n = $i + 1;

        if ($q['text'] === '') {
            $errors[] = "پرسش {$n}: TEXT خالی است.";
        }

        if ($q['score'] <= 0) {
            $errors[] = "پرسش {$n}: SCORE باید بزرگ‌تر از صفر باشد.";
        }

        if (!in_array($q['type'], ['MCQ', 'TRUE_FALSE', 'SHORT'], true)) {
            $errors[] = "پرسش {$n}: TYPE نامعتبر است.";
        }

        if ($q['type'] === 'MCQ') {
            foreach (['A', 'B', 'C', 'D'] as $key) {
                if ($q['options'][$key] === '') {
                    $errors[] = "پرسش {$n}: گزینهٔ {$key} خالی است.";
                }
            }

            if (!in_array($q['answer'], ['A', 'B', 'C', 'D'], true)) {
                $errors[] = "پرسش {$n}: ANSWER باید یکی از A، B، C یا D باشد.";
            }
        } elseif ($q['type'] === 'TRUE_FALSE') {
            if (!in_array($q['answer'], ['A', 'B'], true)) {
                $errors[] = "پرسش {$n}: ANSWER برای TRUE_FALSE باید A یا B باشد.";
            }
        }
    }

    return [$questions, $errors];
}

/**
 * تبدیل ارقام فارسی و عربی به ASCII.
 */
function normalize_docx_digits(string $value): string
{
    return strtr($value, [
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9'
    ]);
}

/**
 * تشخیص گزینهٔ چندگزینه‌ای.
 *
 * قالب‌های پذیرفته‌شده:
 *   الف گزینه
 *   الف) گزینه
 *   الف. گزینه
 *   ( الف گزینه
 *   (الف) گزینه
 *   (الف گزینه
 *   1 گزینه
 *
 * $expected:
 *   1 = الف
 *   2 = ب
 *   3 = ج
 *   4 = د
 */
function paragraph_mcq_option(string $line, int $expected): ?string
{
    $line = trim(preg_replace('/\x{FEFF}/u', '', $line) ?? '');

    $labels = [
        1 => '(?:الف|ا|a|A|1)',
        2 => '(?:ب|b|B|2)',
        3 => '(?:ج|c|C|3)',
        4 => '(?:د|دال|d|D|4)'
    ];

    if (!isset($labels[$expected])) {
        return null;
    }

    /*
     * قبل از برچسب گزینه، پرانتز باز فارسی/انگلیسی اختیاری است.
     * بعد از برچسب نیز یکی از جداکننده‌های رایج یا فاصله پذیرفته می‌شود.
     */
    $pattern =
        '/^\s*[\(（]?\s*' .
        $labels[$expected] .
        '\s*(?:[\)\]）\].:：\-–—]|[\s])\s*(.*)$/u';

    if (preg_match($pattern, $line, $m)) {
        return trim($m[1]);
    }

    return null;
}

/**
 * شماره سؤال ابتدای متن را در صورت وجود حذف می‌کند.
 *
 * مثال:
 *   5 سؤال چیست؟
 *   5) سؤال چیست؟
 *   ۵. سؤال چیست؟
 *
 * به:
 *   سؤال چیست؟
 *
 * تبدیل می‌شود.
 */
function normalize_mcq_question_text(string $text): string
{
    $text = trim($text);

    $normalized = normalize_docx_digits($text);

    $normalized = preg_replace(
        '/^\s*[0-9]+\s*[\)\].:：\-–—]\s*/u',
        '',
        $normalized
    ) ?? $normalized;

    $normalized = preg_replace(
        '/^\s*[0-9]+\s+(?=\S)/u',
        '',
        $normalized
    ) ?? $normalized;

    return trim($normalized);
}

/**
 * پارسر اصلی قالب پاراگرافی MCQ:
 *
 * سؤال
 * ( الف گزینه اول
 * ( ب گزینه دوم
 * ( ج گزینه سوم
 * ( د گزینه چهارم
 * 3
 *
 * هر گزینه و پاسخ می‌تواند با Enter یا Shift+Enter از خط قبلی جدا شده باشد.
 */
function parse_paragraph_mcq_docx(string $path, float $score = 1): array
{
    $lines = docx_paragraphs($path);

    $questions = [];
    $errors = [];

    $questionText = [];
    $options = [];

    $state = 'question';
    $questionNo = 1;
    $optionNo = 1;

    foreach ($lines as $lineNo => $raw) {
        $lineNumber = $lineNo + 1;

        $line = trim(preg_replace('/\x{FEFF}/u', '', $raw) ?? '');

        if ($line === '') {
            continue;
        }

        if ($state === 'question') {
            $first = paragraph_mcq_option($line, 1);

            if ($first !== null) {
                if (!$questionText) {
                    $errors[] = "پرسش {$questionNo}: متن سؤال خالی است.";
                }

                $options = ['A' => $first];
                $optionNo = 2;
                $state = 'options';
            } else {
                $questionText[] = $line;
            }

            continue;
        }

        if ($state === 'options') {
            $key = ['A', 'B', 'C', 'D'][$optionNo - 1];
            $value = paragraph_mcq_option($line, $optionNo);

            if ($value === null) {
                $errors[] =
                    "پرسش {$questionNo}: گزینهٔ شمارهٔ {$optionNo} " .
                    "در پاراگراف {$lineNumber} پیدا نشد.";

                $state = 'invalid';
            } else {
                $options[$key] = $value;

                if ($optionNo === 4) {
                    $state = 'answer';
                } else {
                    $optionNo++;
                }
            }

            continue;
        }

        if ($state === 'answer') {
            $answer = normalize_docx_digits($line);
            $answer = trim($answer);

            if (!preg_match('/^[1-4]$/', $answer)) {
                $errors[] =
                    "پرسش {$questionNo}: پاسخ صحیح باید عددی بین ۱ تا ۴ باشد؛ " .
                    "خط {$lineNumber} مقدار «{$line}» دارد.";

                $state = 'invalid';
            } else {
                $questions[] = [
                    'type' => 'MCQ',
                    'score' => $score,
                    'text' => normalize_mcq_question_text(
                        implode(' ', $questionText)
                    ),
                    'options' => [
                        'A' => $options['A'] ?? '',
                        'B' => $options['B'] ?? '',
                        'C' => $options['C'] ?? '',
                        'D' => $options['D'] ?? ''
                    ],
                    'answer' => [
                        '1' => 'A',
                        '2' => 'B',
                        '3' => 'C',
                        '4' => 'D'
                    ][$answer],
                    'feedback' => '',
                    'reference_answer' => '',
                    'rubric' => ''
                ];

                $questionNo++;
                $questionText = [];
                $options = [];
                $state = 'question';
                $optionNo = 1;
            }

            continue;
        }

        if ($state === 'invalid') {
            /*
             * بعد از خطای یک سؤال، تا پیدا شدن گزینهٔ «الف» سؤال بعدی
             * ادامه می‌دهیم تا خطاهای بعدی نیز قابل گزارش باشند.
             */
            $first = paragraph_mcq_option($line, 1);

            if ($first !== null) {
                $questionText = [];
                $options = ['A' => $first];
                $optionNo = 2;
                $state = 'options';

                $questionNo++;
            }
        }
    }

    if ($state !== 'question') {
        $errors[] =
            "پرسش {$questionNo}: بلوک سؤال ناقص است و گزینه‌ها یا پاسخ صحیح کامل نیستند.";
    }

    return [$questions, $errors];
}

/**
 * Extracts embedded DOCX images by their original names, e.g. Q1.png or Q1_A.png.
 * Returns a map keyed by lowercase basename with binary data and MIME type.
 */
function docx_named_images(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('افزونه ZipArchive در PHP فعال نیست.');
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('فایل DOCX قابل خواندن نیست.');
    }

    $images = [];

    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (!is_string($name) || !str_starts_with($name, 'word/media/')) {
            continue;
        }

        $base = basename($name);
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        if (!isset($allowed[$ext])) {
            continue;
        }

        $data = $zip->getFromIndex($i);

        if ($data === false || strlen($data) > 2 * 1024 * 1024) {
            continue;
        }

        $images[strtolower($base)] = [
            'data' => $data,
            'mime' => $allowed[$ext],
            'extension' => $ext
        ];
    }

    $zip->close();

    return $images;
}

/**
 * پارسر سؤال درست/نادرست.
 */
function parse_true_false_docx(string $path, float $score = 1): array
{
    $lines = docx_paragraphs($path);
    $questions = [];
    $errors = [];
    $block = [];
    $no = 1;

    foreach ($lines as $lineNo => $raw) {
        $line = trim(preg_replace('/\x{FEFF}/u', '', $raw) ?? '');

        if ($line === '') {
            if ($block) {
                $questions[] = $block;
                $block = [];
                $no++;
            }
            continue;
        }

        if (!$block) {
            $block = ['text' => $line];
            continue;
        }

        $answer = trim($line);

        $map = [
            'درست' => 'A',
            'صحیح' => 'A',
            'true' => 'A',
            'نادرست' => 'B',
            'غلط' => 'B',
            'false' => 'B'
        ];

        $answerKey = $map[$answer] ?? $map[strtolower($answer)] ?? null;

        if ($answerKey !== null) {
            $questions[] = [
                'type' => 'TRUE_FALSE',
                'score' => $score,
                'text' => $block['text'],
                'options' => ['A' => 'درست', 'B' => 'نادرست'],
                'answer' => $answerKey,
                'reference_answer' => '',
                'rubric' => ''
            ];

            $block = [];
            $no++;
        } else {
            $errors[] = "بلوک {$no}: پاسخ باید درست یا نادرست باشد.";
            $block = [];
            $no++;
        }
    }

    if ($block) {
        $errors[] = "بلوک {$no}: پاسخ ناقص است.";
    }

    return [$questions, $errors];
}

/**
 * تبدیل سطح دشواری به کلید استاندارد.
 */
function normalize_docx_difficulty(string $value): string
{
    $value = trim($value);
    $key = strtolower($value);

    return match ($value) {
        'آسان', 'ساده', 'easy' => 'easy',
        'متوسط', 'میانه', 'medium' => 'medium',
        'سخت', 'دشوار', 'hard' => 'hard',
        default => match ($key) {
            'easy' => 'easy',
            'medium' => 'medium',
            'hard' => 'hard',
            default => 'medium'
        }
    };
}

/**
 * پارسر سؤال کوتاه.
 */
function parse_short_docx(string $path, float $defaultScore = 1): array
{
    $lines = docx_paragraphs($path);
    $questions = [];
    $errors = [];
    $b = null;
    $no = 1;

    foreach ($lines as $lineNo => $raw) {
        $line = trim(preg_replace('/\x{FEFF}/u', '', $raw) ?? '');

        if ($line === '') {
            continue;
        }

        if (preg_match('/^(سؤال|question)\s*:\s*(.*)$/iu', $line, $m)) {
            $b = [
                'text' => trim($m[2]),
                'score' => $defaultScore,
                'reference_answer' => '',
                'rubric' => '',
                'chapter' => '',
                'difficulty' => 'medium',
                'tags' => ''
            ];
            continue;
        }

        if (!$b) {
            continue;
        }

        if (preg_match('/^(پاسخ مرجع|reference)\s*:\s*(.*)$/iu', $line, $m)) {
            $b['reference_answer'] = trim($m[2]);
        } elseif (preg_match('/^(rubric|روبرک)\s*:\s*(.*)$/iu', $line, $m)) {
            $b['rubric'] = trim($m[2]);
        } elseif (preg_match('/^(سطح|difficulty)\s*:\s*(.*)$/iu', $line, $m)) {
            $b['difficulty'] = normalize_docx_difficulty($m[2]);
        } elseif (preg_match('/^(فصل|chapter)\s*:\s*(.*)$/iu', $line, $m)) {
            $b['chapter'] = trim($m[2]);
        } elseif (preg_match('/^(برچسب|tags)\s*:\s*(.*)$/iu', $line, $m)) {
            $b['tags'] = trim($m[2]);

            $questions[] = [
                'type' => 'SHORT',
                'score' => $b['score'],
                'text' => $b['text'],
                'options' => [],
                'answer' => '',
                'reference_answer' => $b['reference_answer'],
                'rubric' => $b['rubric'],
                'chapter' => $b['chapter'],
                'difficulty' => $b['difficulty'],
                'tags' => $b['tags']
            ];

            $b = null;
            $no++;
        }
    }

    if ($b) {
        $errors[] = "سؤال {$no}: خط برچسب برای پایان بلوک پیدا نشد.";
    }

    return [$questions, $errors];
}
