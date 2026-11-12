<?php
// Expects $bankThermometerGroups: grouped subject/chapter totals from the caller.
$thermometerQuestions = 0;
$thermometerSubjects = count($bankThermometerGroups);
$thermometerChapters = 0;
foreach ($bankThermometerGroups as $group) {
    $thermometerQuestions += (int)$group['total'];
    foreach ($group['chapters'] as $chapter) { if ((int)$chapter['total'] > 0) $thermometerChapters++; }
}
?>
<section class="bank-thermometer" aria-labelledby="bank-thermometer-title">
    <div class="bank-thermometer-head">
        <div>
            <span class="eyebrow">نمای کلی موجودی</span>
            <h2 id="bank-thermometer-title">دماسنج بانک سؤال</h2>
            <p class="muted">تعداد سؤال‌های ثبت‌شده به تفکیک درس و پودمان / فصل</p>
        </div>
        <div class="bank-thermometer-total"><strong><?= number_format($thermometerQuestions) ?></strong><span>سؤال در بانک</span></div>
    </div>
    <div class="bank-thermometer-stats">
        <div><strong><?= number_format($thermometerSubjects) ?></strong><span>درس</span></div>
        <div><strong><?= number_format($thermometerChapters) ?></strong><span>پودمان / فصل</span></div>
    </div>
    <?php if (!$bankThermometerGroups): ?>
        <div class="bank-thermometer-empty">برای نمایش آمار، ابتدا یک درس و سؤال در بانک ثبت کنید.</div>
    <?php else: ?>
        <div class="bank-thermometer-grid">
            <?php foreach ($bankThermometerGroups as $subjectGroup): ?>
                <article class="bank-thermometer-subject">
                    <div class="bank-thermometer-subject-head">
                        <div><h3><?= e($subjectGroup['title']) ?></h3><small><?= number_format((int)$subjectGroup['total']) ?> سؤال</small></div>
                        <?php if (isset($subjectGroup['subject_active']) && !(int)$subjectGroup['subject_active']): ?><span class="bank-thermometer-inactive subject-inactive">درس غیرفعال</span><?php endif; ?>
                        <?php if ($subjectGroup['inactive'] > 0): ?><span class="bank-thermometer-inactive"><?= number_format((int)$subjectGroup['inactive']) ?> غیرفعال</span><?php endif; ?>
                    </div>
                    <?php if ($subjectGroup['total'] > 0): ?>
                        <?php $maxChapterCount = max(array_map(static fn($chapter) => (int)$chapter['total'], $subjectGroup['chapters'])); ?>
                        <div class="bank-thermometer-chapters">
                            <?php foreach ($subjectGroup['chapters'] as $chapter): ?>
                                <?php $width = $maxChapterCount > 0 ? max(5, (int)round((int)$chapter['total'] / $maxChapterCount * 100)) : 0; ?>
                                <div class="bank-thermometer-row">
                                    <div class="bank-thermometer-label"><span title="<?= e($chapter['title']) ?>"><?= e($chapter['title']) ?></span><strong><?= number_format((int)$chapter['total']) ?></strong></div>
                                    <div class="bank-thermometer-track" role="img" aria-label="<?= e($chapter['title']) ?>: <?= number_format((int)$chapter['total']) ?> سؤال"><span style="width:<?= $width ?>%"></span></div>
                                    <?php if ($chapter['inactive'] > 0): ?><small class="bank-thermometer-chapter-inactive"><?= number_format((int)$chapter['inactive']) ?> غیرفعال از <?= number_format((int)$chapter['total']) ?></small><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bank-thermometer-empty subject-empty">هنوز سؤالی در این درس ثبت نشده است.</div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
