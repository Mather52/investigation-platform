<?php /** @var array $case @var array $memo @var array $sections */ ?>
<article class="doc">
    <div class="doc-head">
        <div><b style="font-size: 16px; display: block;">المدينة الطبية</b><span class="muted">إدارة الشؤون القانونية والالتزام — قسم التحقيق</span></div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
            <?php if ($case['confidentiality'] !== 'normal'): ?><span class="secure"><?= icon('lock', 14) ?><?= esc(label('conf', $case['confidentiality'])) ?> — للاطلاع المصرح به فقط</span><?php endif ?>
            <span class="muted num">الرقم: <?= esc($case['case_no']) ?> · النسخة <?= (int) ($version ?? $memo['version']) ?></span>
        </div>
    </div>
    <h2 class="doc-title">مذكرة عرض تفصيلية بنتائج التحقيق</h2>
    <?php $i = 1; foreach ($sections as $k => $title): ?>
        <section><h3><?= $i++ ?>. <?= esc($title) ?></h3><div class="body"><?= esc($memo[$k] ?? '') ?: '<span class="muted">—</span>' ?></div></section>
    <?php endforeach ?>
    <div class="row" style="gap: 48px; padding-top: 12px; border-top: 1px solid var(--line);">
        <div class="kv"><span>المحقق</span><strong><?= esc($case['investigator_name'] ?? '—') ?></strong><?php if (! empty($memo['submitted_at'])): ?><span class="hint" style="color: var(--g);">أُرسلت للمراجعة · <?= fdate($memo['submitted_at']) ?></span><?php endif ?></div>
        <?php foreach (($signatures ?? []) as $sg): ?>
            <div class="kv"><span><?= esc($sg['role_name']) ?></span><strong><?= esc($sg['approver']) ?></strong><span class="hint" style="color: var(--g);">اعتمد إلكترونياً · <?= fdate($sg['decided_at']) ?></span></div>
        <?php endforeach ?>
    </div>
</article>
