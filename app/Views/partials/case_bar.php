<?php /** @var array $case */ ?>
<div class="case-bar">
    <div class="case-id">
        <div class="ico"><?= icon('folder', 22) ?></div>
        <div><span class="muted" style="font-size: 12px;">رقم المعاملة</span><a href="<?= site_url('cases/' . $case['id']) ?>"><strong class="num"><?= esc($case['case_no']) ?></strong></a></div>
    </div>
    <?php $accused = array_values(array_filter($parties ?? [], fn ($p) => $p['party_role'] === 'accused')); ?>
    <div class="kv"><span>الموظف محل التحقيق</span><span><?= esc(implode('، ', array_column($accused, 'name')) ?: '—') ?></span></div>
    <div class="kv"><span>المحقق</span><span><?= esc($case['investigator_name'] ?? '—') ?></span></div>
    <div class="kv"><span>الإدارة</span><span><?= esc($case['department_name'] ?? '—') ?></span></div>
    <div class="end"><?= badge($case['stage_name'], stage_tone($case['stage_code']), true) ?><?php if ($case['confidentiality'] !== 'normal'): ?><span class="secure"><?= icon('lock', 14) ?><?= esc(label('conf', $case['confidentiality'])) ?> — للاطلاع المصرح به فقط</span><?php endif ?></div>
</div>
