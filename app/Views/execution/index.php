<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$id = $case['id'];
$done = count(array_filter($recs, fn ($r) => $r['status'] === 'done'));
$total = count($recs);
$current = null;
foreach ($recs as $r) { if ((int) $r['id'] === $edit) { $current = $r; } }
?>
<?= partial('partials/card_open', ['icon' => 'checkc', 'title' => 'بيانات التحقيق المعتمد', 'right' => $case['confidentiality'] !== 'normal' ? '<span class="secure">' . icon('lock', 14) . 'سري — للاطلاع المصرح به فقط</span>' : '']) ?>
    <div class="grid g4">
        <div class="kv"><span>رقم المعاملة</span><strong class="num"><?= esc($case['case_no']) ?></strong></div>
        <div class="kv"><span>تاريخ الاعتماد</span><strong class="num"><?= fdate($memo['approved_at']) ?></strong></div>
        <div class="kv"><span>المعتمد</span><strong><?= esc(($gm['name'] ?? '—') . ' — المدير العام التنفيذي') ?></strong></div>
        <div class="kv"><span>حالة المعاملة</span><span><?= badge($case['stage_name'], stage_tone($case['stage_code']), true) ?></span></div>
    </div>
    <div class="row"><a class="btn btn-sm" href="<?= site_url("cases/{$id}/review") ?>"><?= icon('eye', 16) ?>عرض المذكرة المعتمدة</a></div>
<?= partial('partials/card_close') ?>

<?= partial('partials/card_open', ['icon' => 'list', 'title' => 'التوصيات المعتمدة', 'right' => '<span class="muted num">المكتمل ' . $done . ' من ' . $total . '</span>']) ?>
    <?php if ($recs === []): ?><div class="empty">لا توجد توصيات مسجلة.</div><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>التوصية</th><th>الجهة المسؤولة عن التنفيذ</th><th>تاريخ الاستحقاق</th><th>حالة التنفيذ</th><th>المرفقات</th><?= $canManage ? '<th></th>' : '' ?></tr></thead>
        <tbody><?php foreach ($recs as $r): $late = $r['status'] !== 'done' && $r['due_date'] && $r['due_date'] < date('Y-m-d'); ?>
            <tr <?= (int) $r['id'] === $edit && $canManage ? 'style="background: var(--primary-tint);"' : '' ?>>
                <td style="max-width: 380px;"><?= esc($r['body']) ?><?php if ($r['progress_notes']): ?><div class="hint"><?= esc($r['progress_notes']) ?></div><?php endif ?></td>
                <td><?= esc($r['department_name']) ?></td>
                <td class="num"><?= fdate($r['due_date']) ?><?= $late ? ' ' . badge('متأخرة', 'red') : '' ?></td>
                <td><?= status_badge('rec', $r['status']) ?></td>
                <td><?php foreach ($files[$r['id']] ?? [] as $f): ?><a class="row" style="gap: 6px; font-size: 13px; font-weight: 600;" href="<?= site_url('attachments/' . $f['id']) ?>"><?= icon('clip', 15) ?><?= esc($f['original_name']) ?></a><?php endforeach ?><?= empty($files[$r['id']]) ? '<span class="muted">—</span>' : '' ?></td>
                <?php if ($canManage): ?><td><a class="btn btn-sm" href="<?= site_url("cases/{$id}/execution?edit={$r['id']}#update") ?>">تحديث</a></td><?php endif ?>
            </tr>
        <?php endforeach ?></tbody>
    </table></div>
    <?php endif ?>
    <?php if ($canManage): ?>
        <form method="post" action="<?= site_url("cases/{$id}/recommendations") ?>" class="grid g4" style="align-items: end; padding-top: 8px; border-top: 1px solid var(--line);">
            <?= csrf_field() ?>
            <label class="field span-2"><span class="lbl">إضافة توصية</span><input class="input" name="body" placeholder="نص التوصية المعتمدة" required></label>
            <label class="field"><span class="lbl">الجهة المسؤولة</span><select class="select" name="responsible_department_id" required><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= (int) $d['id'] === (int) $case['department_id'] ? 'selected' : '' ?>><?= esc($d['name_ar']) ?></option><?php endforeach ?></select></label>
            <div class="row" style="flex-wrap: nowrap;"><input class="input" type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" aria-label="تاريخ الاستحقاق"><button class="btn" style="flex-shrink: 0;"><?= icon('plus', 16) ?>إضافة</button></div>
        </form>
    <?php endif ?>
<?= partial('partials/card_close') ?>

<?php if ($canManage && $current): ?>
<?= partial('partials/card_open', ['id' => 'update', 'icon' => 'refresh', 'title' => 'تحديث حالة التنفيذ']) ?>
    <form method="post" action="<?= site_url("recommendations/{$current['id']}") ?>" enctype="multipart/form-data" class="stack" style="gap: 18px;">
        <?= csrf_field() ?>
        <div class="grid g4">
            <div class="field span-2"><span class="lbl">التوصية</span><div class="ro"><span><?= esc($current['body']) ?></span></div></div>
            <label class="field"><span class="lbl">حالة التنفيذ <span class="req">*</span></span><select class="select" name="status"><?php foreach (['not_started', 'in_progress', 'done'] as $s): ?><option value="<?= $s ?>" <?= $current['status'] === $s ? 'selected' : '' ?>><?= label('rec', $s) ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="lbl">تاريخ الاستحقاق</span><input class="input" type="date" name="due_date" value="<?= esc($current['due_date']) ?>"></label>
            <label class="field span-2"><span class="lbl">الجهة المسؤولة</span><select class="select" name="responsible_department_id"><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= (int) $d['id'] === (int) $current['responsible_department_id'] ? 'selected' : '' ?>><?= esc($d['name_ar']) ?></option><?php endforeach ?></select></label>
            <div class="field span-2"><span class="lbl">تاريخ التحديث</span><div class="ro"><span class="num"><?= date('Y-m-d') ?></span><span class="tag">آلي</span></div></div>
        </div>
        <label class="field"><span class="lbl">ملاحظات التنفيذ</span><textarea class="textarea" name="progress_notes" rows="3" style="min-height: 90px;" placeholder="ما تم إنجازه حتى الآن…"><?= esc($current['progress_notes']) ?></textarea></label>
        <div class="field"><span class="lbl">إثبات التنفيذ</span><?= partial('partials/dropzone', ['name' => 'evidence[]', 'text' => 'ارفع مستند إثبات التنفيذ (مطلوب عند "تم التنفيذ")']) ?></div>
        <div><button class="btn btn-primary"><?= icon('refresh', 18) ?>تحديث حالة التنفيذ</button></div>
    </form>
<?= partial('partials/card_close') ?>
<?php endif ?>

<?php if ($case['stage_code'] === 'execution'): ?>
<?= partial('partials/card_open', ['icon' => 'archive', 'title' => 'الأرشفة']) ?>
    <div class="row between">
        <div><b>أرشفة المعاملة</b><div class="hint">تُتاح بعد اكتمال تنفيذ جميع التوصيات · المكتمل <?= $done ?> من <?= $total ?></div></div>
        <?php if ($canManage): ?><button class="btn <?= $done === $total ? 'btn-primary' : '' ?>" data-open="archiveCase" <?= $done === $total ? '' : 'disabled' ?>><?= icon('archive', 18) ?>أرشفة المعاملة</button><?php endif ?>
    </div>
<?= partial('partials/card_close') ?>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php if ($canManage && $done === $total): ?>
<dialog class="modal" id="archiveCase">
    <form method="post" action="<?= site_url("cases/{$case['id']}/archive") ?>">
        <?= csrf_field() ?>
        <div class="modal-ico"><?= icon('archive', 28) ?></div>
        <div><h2>تأكيد أرشفة المعاملة</h2><p>بعد الأرشفة سيتم حفظ جميع مستندات وسجلات المعاملة ضمن الأرشيف.</p></div>
        <div class="summary">
            <div><span class="row" style="gap: 8px;"><?= icon('checkc', 16) ?>تنفيذ التوصيات</span><b class="num"><?= $done ?> من <?= $total ?></b></div>
            <div><span class="row" style="gap: 8px;"><?= icon('checkc', 16) ?>المذكرة المعتمدة</span><b>محفوظة</b></div>
        </div>
        <div class="row"><button class="btn btn-primary"><?= icon('archive', 18) ?>تأكيد الأرشفة</button><button class="btn" data-close>إلغاء</button></div>
    </form>
</dialog>
<?php endif ?>
<?= $this->endSection() ?>
