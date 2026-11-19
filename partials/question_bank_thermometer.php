<?php
// Expects $bankThermometerGroups grouped by subject and chapter, including easy/medium/hard counts.
$thermometerQuestions = 0;
$thermometerSubjects = count($bankThermometerGroups);
$thermometerChapters = 0;
$thermometerDifficulty = ['easy' => 0, 'medium' => 0, 'hard' => 0];
$thermometerActiveGroups = [];
$thermometerInactiveGroups = [];
foreach ($bankThermometerGroups as $group) {
    $thermometerQuestions += (int)$group['total'];
    foreach (['easy', 'medium', 'hard'] as $level) {
        $thermometerDifficulty[$level] += (int)($group[$level] ?? 0);
    }
    foreach ($group['chapters'] as $chapter) {
        if ((int)$chapter['total'] > 0) $thermometerChapters++;
    }
    if (isset($group['subject_active']) && !(int)$group['subject_active']) {
        $thermometerInactiveGroups[] = $group;
    } else {
        $thermometerActiveGroups[] = $group;
    }
}
?>
<details class="bank-thermometer" aria-labelledby="bank-thermometer-title">
    <summary class="bank-thermometer-toggle" aria-controls="bank-thermometer-details">
        <span class="bank-thermometer-toggle-copy">
            <span class="eyebrow">نمای کلی موجودی</span>
            <span class="bank-thermometer-title" id="bank-thermometer-title" role="heading" aria-level="2">دماسنج بانک سؤال</span>
        </span>
        <span class="bank-thermometer-toggle-side">
            <span class="bank-thermometer-total"><strong><?= number_format($thermometerQuestions) ?></strong><span>سؤال در بانک</span></span>
            <span class="bank-thermometer-toggle-action">
                <span class="bank-thermometer-open-label">نمایش آمار</span>
                <span class="bank-thermometer-close-label">بستن آمار</span>
                <svg class="bank-thermometer-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
        </span>
    </summary>
    <div class="bank-thermometer-content" id="bank-thermometer-details">
        <p class="bank-thermometer-description muted">شمار سؤال‌ها به تفکیک درس، پودمان / فصل و سطح دشواری</p>
        <div class="bank-thermometer-stats">
            <div><strong><?= number_format($thermometerSubjects) ?></strong><span>درس</span></div>
            <div><strong><?= number_format($thermometerChapters) ?></strong><span>پودمان / فصل</span></div>
            <div class="thermo-level-easy"><strong><?= number_format($thermometerDifficulty['easy']) ?></strong><span>آسان</span></div>
            <div class="thermo-level-medium"><strong><?= number_format($thermometerDifficulty['medium']) ?></strong><span>متوسط</span></div>
            <div class="thermo-level-hard"><strong><?= number_format($thermometerDifficulty['hard']) ?></strong><span>عمیق</span></div>
        </div>
        <?php if (!$bankThermometerGroups): ?>
            <div class="bank-thermometer-empty">برای نمایش آمار، ابتدا یک درس و سؤال در بانک ثبت کنید.</div>
        <?php else: ?>
            <?php foreach ([
                ['title' => 'دروس فعال', 'groups' => $thermometerActiveGroups, 'class' => 'active'],
                ['title' => 'دروس غیرفعال', 'groups' => $thermometerInactiveGroups, 'class' => 'inactive'],
            ] as $subjectSection): ?>
                <?php if (!$subjectSection['groups']) continue; ?>
                <?php if ($subjectSection['class'] === 'inactive'): ?>
                    <details class="bank-thermometer-section bank-thermometer-section-inactive">
                    <summary class="bank-thermometer-section-head">
                        <h3><?= e($subjectSection['title']) ?></h3>
                        <span><?= number_format(count($subjectSection['groups'])) ?> درس</span>
                        <span class="bank-thermometer-section-icon" aria-hidden="true"></span>
                    </summary>
                <?php else: ?>
                    <section class="bank-thermometer-section bank-thermometer-section-active" aria-label="<?= e($subjectSection['title']) ?>">
                    <div class="bank-thermometer-section-head">
                        <h3><?= e($subjectSection['title']) ?></h3>
                        <span><?= number_format(count($subjectSection['groups'])) ?> درس</span>
                    </div>
                <?php endif; ?>
                    <div class="bank-thermometer-grid">
                        <?php foreach ($subjectSection['groups'] as $subjectGroup): ?>
                            <article class="bank-thermometer-subject<?= isset($subjectGroup['subject_active']) && !(int)$subjectGroup['subject_active'] ? ' subject-inactive' : '' ?>">
                                <div class="bank-thermometer-subject-head">
                                    <div><h3><?= e($subjectGroup['title']) ?></h3><small><?= number_format((int)$subjectGroup['total']) ?> سؤال</small></div>
                                    <?php if (isset($subjectGroup['subject_active']) && !(int)$subjectGroup['subject_active']): ?><span class="bank-thermometer-inactive subject-inactive">درس غیرفعال</span><?php endif; ?>
                                    <?php if ($subjectGroup['inactive'] > 0): ?><span class="bank-thermometer-inactive"><?= number_format((int)$subjectGroup['inactive']) ?> غیرفعال</span><?php endif; ?>
                                </div>
                                <div class="bank-thermometer-level-counts subject-level-counts" aria-label="تعداد سؤال‌ها در درس <?= e($subjectGroup['title']) ?> برحسب سطح دشواری">
                                    <span class="thermo-level-easy">آسان <b><?= number_format((int)($subjectGroup['easy'] ?? 0)) ?></b></span>
                                    <span class="thermo-level-medium">متوسط <b><?= number_format((int)($subjectGroup['medium'] ?? 0)) ?></b></span>
                                    <span class="thermo-level-hard">عمیق <b><?= number_format((int)($subjectGroup['hard'] ?? 0)) ?></b></span>
                                </div>
                                <?php $subjectChapterCount = count(array_filter($subjectGroup['chapters'], static fn($chapter) => (int)$chapter['total'] > 0)); ?>
                                <details class="bank-thermometer-chapter-toggle">
                                    <summary class="bank-thermometer-chapter-toggle-summary">
                                        <span>نمایش پودمان‌ها / فصل‌ها</span>
                                        <span class="bank-thermometer-chapter-toggle-count"><?= number_format($subjectChapterCount) ?></span>
                                        <span class="bank-thermometer-chapter-toggle-icon" aria-hidden="true"></span>
                                    </summary>
                                    <div class="bank-thermometer-chapter-toggle-content">
                                <?php if ($subjectGroup['total'] > 0): ?>
                                    <?php $maxChapterCount = max(array_map(static fn($chapter) => (int)$chapter['total'], $subjectGroup['chapters'])); ?>
                                    <div class="bank-thermometer-chapters">
                                        <?php foreach ($subjectGroup['chapters'] as $chapter): ?>
                                            <?php $width = $maxChapterCount > 0 ? max(5, (int)round((int)$chapter['total'] / $maxChapterCount * 100)) : 0; ?>
                                            <div class="bank-thermometer-row">
                                                <div class="bank-thermometer-label"><span title="<?= e($chapter['title']) ?>"><?= e($chapter['title']) ?></span><strong><?= number_format((int)$chapter['total']) ?></strong></div>
                                                <div class="bank-thermometer-track" role="img" aria-label="<?= e($chapter['title']) ?>: <?= number_format((int)$chapter['total']) ?> سؤال"><span style="width:<?= $width ?>%"></span></div>
                                                <div class="bank-thermometer-level-counts chapter-level-counts" aria-label="تعداد سؤال‌های <?= e($chapter['title']) ?> برحسب سطح دشواری">
                                                    <span class="thermo-level-easy">آسان <b><?= number_format((int)($chapter['easy'] ?? 0)) ?></b></span>
                                                    <span class="thermo-level-medium">متوسط <b><?= number_format((int)($chapter['medium'] ?? 0)) ?></b></span>
                                                    <span class="thermo-level-hard">عمیق <b><?= number_format((int)($chapter['hard'] ?? 0)) ?></b></span>
                                                </div>
                                                <?php if ($chapter['inactive'] > 0): ?><small class="bank-thermometer-chapter-inactive"><?= number_format((int)$chapter['inactive']) ?> غیرفعال از <?= number_format((int)$chapter['total']) ?></small><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="bank-thermometer-empty subject-empty">هنوز سؤالی در این درس ثبت نشده است.</div>
                                <?php endif; ?>
                                    </div>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php if ($subjectSection['class'] === 'inactive'): ?>
                    </details>
                <?php else: ?>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</details>
