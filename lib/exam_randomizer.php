<?php

/** Return validated per-difficulty quotas stored on an exam. */
function exam_random_question_counts(array $exam): array
{
    return [
        'easy' => max(0, (int) ($exam['random_easy_count'] ?? 0)),
        'medium' => max(0, (int) ($exam['random_medium_count'] ?? 0)),
        'hard' => max(0, (int) ($exam['random_hard_count'] ?? 0)),
    ];
}

/** Return a Persian validation message when a randomized exam cannot be served. */
function random_exam_inventory_error(PDO $pdo, array $exam): ?string
{
    if ((int) ($exam['random_per_student'] ?? 0) !== 1) {
        return null;
    }

    $chapter = trim((string) ($exam['chapter'] ?? ''));
    $counts = exam_random_question_counts($exam);
    $total = array_sum($counts);
    if ($chapter === '') {
        return 'پودمان آزمون مشخص نشده است.';
    }
    if ($total < 1 || $total > 500) {
        return 'مجموع سهمیهٔ سؤال‌ها باید بین ۱ تا ۵۰۰ باشد.';
    }

    $poolStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE exam_id=? AND bank_question_id IS NOT NULL');
    $poolStmt->execute([(int) ($exam['id'] ?? 0)]);
    if ((int) $poolStmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare('SELECT difficulty,COUNT(*) total FROM questions WHERE exam_id=? AND bank_question_id IS NOT NULL AND TRIM(chapter)=TRIM(?) GROUP BY difficulty');
        $stmt->execute([(int) ($exam['id'] ?? 0), $chapter]);
    } else {
        $stmt = $pdo->prepare("SELECT qb.difficulty,COUNT(*) total FROM question_bank qb WHERE qb.subject_id=? AND qb.is_active=1 AND TRIM(qb.chapter)=TRIM(?) AND (qb.question_type='short' OR (qb.question_type IN ('mcq','true_false') AND (SELECT COUNT(*) FROM question_bank_options o WHERE o.bank_question_id=qb.id)>=2 AND (SELECT COUNT(*) FROM question_bank_options o WHERE o.bank_question_id=qb.id AND o.is_correct=1)=1)) GROUP BY qb.difficulty");
        $stmt->execute([(int) ($exam['subject_id'] ?? 0), $chapter]);
    }
    $available = ['easy' => 0, 'medium' => 0, 'hard' => 0];
    foreach ($stmt as $row) {
        if (isset($available[$row['difficulty']])) {
            $available[$row['difficulty']] = (int) $row['total'];
        }
    }

    $labels = ['easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'دشوار'];
    foreach ($counts as $level => $required) {
        $requiredWithOneRetake = $required * 2;
        if ($requiredWithOneRetake > $available[$level]) {
            return 'برای سطح «' . $labels[$level] . '» با احتساب یک فرصت مجدد مجاز، به ' . $requiredWithOneRetake . ' سؤال نیاز دارید، اما فقط ' . $available[$level] . ' سؤال فعال در بانک این پودمان موجود است.';
        }
    }
    return null;
}

/**
 * Pick a fresh, exact quota of active bank questions for one attempt.
 * Questions used by a student's previous attempts can be excluded so a granted
 * retake gets a new set. The caller should run this inside the attempt transaction.
 */
function select_random_bank_questions(PDO $pdo, array $exam, array $excludedBankIds = []): array
{
    $subjectId = (int) ($exam['subject_id'] ?? 0);
    $chapter = trim((string) ($exam['chapter'] ?? ''));
    if ($subjectId < 1 || $chapter === '') {
        throw new RuntimeException('درس یا پودمان آزمون برای انتخاب تصادفی مشخص نشده است.');
    }

    $excludedBankIds = array_values(array_unique(array_filter(array_map('intval', $excludedBankIds), static fn($id) => $id > 0)));
    $counts = exam_random_question_counts($exam);
    $selected = [];
    $labels = ['easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'دشوار'];

    foreach ($counts as $difficulty => $needed) {
        if ($needed < 1) {
            continue;
        }

        $sql = "SELECT qb.* FROM question_bank qb WHERE qb.subject_id=? AND TRIM(qb.chapter)=TRIM(?) AND qb.is_active=1 AND qb.difficulty=? AND (qb.question_type='short' OR (qb.question_type IN ('mcq','true_false') AND (SELECT COUNT(*) FROM question_bank_options o WHERE o.bank_question_id=qb.id)>=2 AND (SELECT COUNT(*) FROM question_bank_options o WHERE o.bank_question_id=qb.id AND o.is_correct=1)=1))";
        $params = [$subjectId, $chapter, $difficulty];
        if ($excludedBankIds) {
            $sql .= ' AND qb.id NOT IN (' . implode(',', array_fill(0, count($excludedBankIds), '?')) . ')';
            $params = array_merge($params, $excludedBankIds);
        }
        $sql .= ' ORDER BY RAND() LIMIT ' . $needed;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== $needed) {
            throw new RuntimeException('سؤال تازهٔ کافی در سطح «' . $labels[$difficulty] . '» وجود ندارد.');
        }
        array_push($selected, ...$rows);
    }

    if (!$selected) {
        throw new RuntimeException('برای آزمون دست‌کم یک سهمیهٔ سؤال باید تعیین شود.');
    }
    return $selected;
}

/** Freeze all currently usable bank questions into the exam at publication time. */
function prepare_random_exam_question_pool(PDO $pdo, array $exam): ?string
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $error = random_exam_inventory_error($pdo, $exam);
        if ($error !== null) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return $error;
        }
        $exists = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE exam_id=? AND bank_question_id IS NOT NULL');
        $exists->execute([(int) $exam['id']]);
        if ((int) $exists->fetchColumn() > 0) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return null;
        }

        $stmt = $pdo->prepare("SELECT qb.* FROM question_bank qb WHERE qb.subject_id=? AND qb.is_active=1 AND TRIM(qb.chapter)=TRIM(?) AND qb.difficulty IN ('easy','medium','hard') AND (qb.question_type='short' OR (qb.question_type IN ('mcq','true_false') AND (SELECT COUNT(*) FROM question_bank_options o WHERE o.bank_question_id=qb.id)>=2 AND (SELECT COUNT(*) FROM question_bank_options o WHERE o.bank_question_id=qb.id AND o.is_correct=1)=1)) ORDER BY qb.id FOR UPDATE");
        $stmt->execute([(int) $exam['subject_id'], (string) $exam['chapter']]);
        $bankQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $available = ['easy' => 0, 'medium' => 0, 'hard' => 0];
        foreach ($bankQuestions as $bankQuestion) {
            if (isset($available[$bankQuestion['difficulty']])) {
                $available[$bankQuestion['difficulty']]++;
            }
        }
        $labels = ['easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'دشوار'];
        foreach (exam_random_question_counts($exam) as $level => $required) {
            $requiredWithOneRetake = $required * 2;
            if ($requiredWithOneRetake > $available[$level]) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return 'برای سطح «' . $labels[$level] . '» با احتساب یک فرصت مجدد مجاز، به ' . $requiredWithOneRetake . ' سؤال نیاز دارید، اما فقط ' . $available[$level] . ' سؤال قابل‌استفاده پیدا شد.';
            }
        }
        if (!$bankQuestions) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return 'در بانک سؤالِ قابل‌استفاده‌ای برای این پودمان وجود ندارد.';
        }
        foreach ($bankQuestions as $index => $bankQuestion) {
            snapshot_bank_question($pdo, (int) $exam['id'], $bankQuestion, $index);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[random_exam_pool] exam=' . (int) ($exam['id'] ?? 0) . ' error=' . $e->getMessage());
        if (!$ownsTransaction) {
            throw $e;
        }
        return 'آماده‌سازی نسخهٔ ثابت سؤال‌های آزمون انجام نشد. migration مربوط به آزمون تصادفی و ستون‌های snapshot را بررسی کنید.';
    }
    return null;
}

/** Select from the immutable exam pool, excluding bank items used by prior attempts. */
function select_random_exam_pool_questions(PDO $pdo, array $exam, array $excludedBankIds = []): array
{
    $counts = exam_random_question_counts($exam);
    $excludedBankIds = array_values(array_unique(array_filter(array_map('intval', $excludedBankIds), static fn($id) => $id > 0)));
    $selected = [];
    $labels = ['easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'دشوار'];
    foreach ($counts as $difficulty => $needed) {
        if ($needed < 1) {
            continue;
        }
        $sql = 'SELECT q.* FROM questions q WHERE q.exam_id=? AND q.bank_question_id IS NOT NULL AND TRIM(q.chapter)=TRIM(?) AND q.difficulty=?';
        $params = [(int) $exam['id'], (string) $exam['chapter'], $difficulty];
        if ($excludedBankIds) {
            $sql .= ' AND q.bank_question_id NOT IN (' . implode(',', array_fill(0, count($excludedBankIds), '?')) . ')';
            $params = array_merge($params, $excludedBankIds);
        }
        $sql .= ' ORDER BY RAND() LIMIT ' . $needed;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== $needed) {
            throw new RuntimeException('سؤال تازهٔ کافی در سطح «' . $labels[$difficulty] . '» وجود ندارد.');
        }
        array_push($selected, ...$rows);
    }
    if (!$selected) {
        throw new RuntimeException('برای آزمون دست‌کم یک سهمیهٔ سؤال باید تعیین شود.');
    }
    return $selected;
}

/** Copy a selected bank item into the exam's immutable question snapshot. */
function snapshot_bank_question(PDO $pdo, int $examId, array $bankQuestion, int $sortOrder): int
{
    $insertQuestion = $pdo->prepare(
        'INSERT INTO questions(exam_id,bank_question_id,question_text,chapter,image_path,question_type,score,sort_order,difficulty,tags,reference_answer,rubric) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insertQuestion->execute([
        $examId,
        (int) $bankQuestion['id'],
        $bankQuestion['question_text'],
        $bankQuestion['chapter'] ?? null,
        $bankQuestion['image_path'] ?? null,
        $bankQuestion['question_type'],
        $bankQuestion['score'],
        $sortOrder,
        $bankQuestion['difficulty'] ?? 'medium',
        $bankQuestion['tags'] ?? null,
        $bankQuestion['reference_answer'] ?? null,
        $bankQuestion['rubric'] ?? null,
    ]);
    $questionId = (int) $pdo->lastInsertId();

    $options = $pdo->prepare('SELECT option_text,image_path,option_key,is_correct,sort_order FROM question_bank_options WHERE bank_question_id=? ORDER BY sort_order,id FOR UPDATE');
    $options->execute([(int) $bankQuestion['id']]);
    $insertOption = $pdo->prepare('INSERT INTO question_options(question_id,option_text,image_path,option_key,is_correct,sort_order) VALUES(?,?,?,?,?,?)');
    foreach ($options as $option) {
        $insertOption->execute([
            $questionId,
            $option['option_text'],
            $option['image_path'] ?? null,
            $option['option_key'],
            $option['is_correct'],
            $option['sort_order'],
        ]);
    }

    return $questionId;
}
