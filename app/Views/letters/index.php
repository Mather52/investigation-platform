<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$id = $case['id'];
$fileLinks = static function (array $list): string {
    $out = '';
    foreach ($list as $a) { $out .= '<a class="row" style="gap: 6px; font-size: 13px; font-weight: 600;" href="' . site_url('attachments/' . $a['id']) . '">' . icon('clip', 15) . esc($a['original_name']) . '</a>'; }
    return $out;
};
$replies = array_values(array_filter($rows, fn ($r) => $r['status'] === 'replied'));
?>
<?= partial('partials/case_bar', ['case' => $case, 'parties' => $parties]) ?>

<?= partial('partials/card_open', ['icon' => 'mail', 'title' => 'المراسلات الصادرة']) ?>
    <?php if ($rows === []): ?><div class="empty">لا توجد مراسلات.</div><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>الجهة</th><th>نوع الطلب</th><th>الموضوع</th><th>تاريخ الإرسال</th><th>تاريخ الاستحقاق</th><th>حالة الرد</th><?= $canEdit ? '<th></th>' : '' ?></tr></thead>
        <tbody><?php foreach ($rows as $r): $late = $r['status'] !== 'replied' && $r['due_date'] < date('Y-m-d'); ?>
            <tr><td><?= esc($r['department_name']) ?></td><td><?= esc($r['request_type']) ?></td><td><?= esc($r['subject']) ?><?= $fileLinks($atts['correspondence'][$r['id']] ?? []) ?></td>
                <td class="num"><?= fdate($r['sent_at']) ?></td><td class="num"><?= fdate($r['due_date']) ?></td>
                <td><?= $r['status'] === 'replied' ? badge('تم الرد', 'green', true) : ($late ? badge('متأخر', 'red', true) : badge('بانتظار الرد', 'yellow', true)) ?></td>
                <?php if ($canEdit): ?><td><?php if ($r['status'] !== 'replied'): ?><button class="btn btn-sm" data-open="reply<?= $r['id'] ?>"><?= icon('msg', 16) ?>تسجيل الرد</button><?php endif ?></td><?php endif ?></tr>
        <?php endforeach ?></tbody>
    </table></div>
    <?php endif ?>
<?= partial('partials/card_close') ?>

<div class="split">
    <div class="main">
        <?php if ($canEdit): ?>
        <?= partial('partials/card_open', ['icon' => 'send', 'title' => 'إرسال مراسلة']) ?>
            <form method="post" action="<?= site_url("cases/{$id}/letters") ?>" enctype="multipart/form-data" class="stack" style="gap: 18px;">
                <?= csrf_field() ?>
                <div class="grid g3">
                    <label class="field"><span class="lbl">الجهة المستلمة <span class="req">*</span></span><select class="select" name="department_id" required><option value="">اختر الجهة</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= old('department_id') == $d['id'] ? 'selected' : '' ?>><?= esc($d['name_ar']) ?></option><?php endforeach ?></select></label>
                    <label class="field"><span class="lbl">نوع الطلب <span class="req">*</span></span><select class="select" name="request_type" required><?php foreach (['طلب مستندات', 'طلب إيضاحات', 'طلب تقرير', 'طلب سجل وظيفي', 'أخرى'] as $t): ?><option <?= old('request_type') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach ?></select></label>
                    <label class="field"><span class="lbl">تاريخ الاستحقاق <span class="req">*</span></span><input class="input" type="date" name="due_date" min="<?= date('Y-m-d') ?>" value="<?= esc(old('due_date', date('Y-m-d', strtotime('+3 days')))) ?>" required></label>
                </div>
                <label class="field"><span class="lbl">الموضوع <span class="req">*</span></span><input class="input" name="subject" value="<?= esc(old('subject')) ?>" placeholder="موضوع المراسلة" required></label>
                <label class="field"><span class="lbl">نص المراسلة <span class="req">*</span></span><textarea class="textarea" name="body" rows="5" placeholder="نص الطلب الموجه للجهة…" required><?= esc(old('body')) ?></textarea></label>
                <div class="field"><span class="lbl">المرفقات</span><?= partial('partials/dropzone', ['text' => 'إرفاق ملفات']) ?></div>
                <div><button class="btn btn-primary"><?= icon('send', 18) ?>إرسال</button></div>
            </form>
        <?= partial('partials/card_close') ?>
        <?php endif ?>
    </div>
    <div class="side" style="width: 400px;">
        <?= partial('partials/card_open', ['icon' => 'msg', 'title' => 'الردود', 'bodyClass' => 'tight']) ?>
            <?php if ($replies === []): ?><div class="empty">لم ترد ردود بعد.</div><?php endif ?>
            <?php foreach ($replies as $r): ?>
                <div style="border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px;">
                    <div class="row between"><b><?= esc($r['department_name']) ?></b><span class="hint num"><?= fdate($r['replied_at']) ?></span></div>
                    <span class="hint"><?= esc($r['subject']) ?><?= $r['replied_by'] ? ' · ' . esc($r['replied_by']) : '' ?></span>
                    <p style="font-size: 14px; line-height: 1.8; white-space: pre-wrap;"><?= esc($r['reply_body']) ?></p>
                    <?= $fileLinks($atts['correspondence_reply'][$r['id']] ?? []) ?>
                </div>
            <?php endforeach ?>
        <?= partial('partials/card_close') ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php if ($canEdit): foreach ($rows as $r): if ($r['status'] === 'replied') continue; ?>
<dialog class="modal" id="reply<?= $r['id'] ?>">
    <form method="post" action="<?= site_url("letters/{$r['id']}/reply") ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="modal-ico"><?= icon('msg', 28) ?></div>
        <div><h2>تسجيل رد</h2><p><?= esc($r['department_name']) ?> — <?= esc($r['subject']) ?></p></div>
        <label class="field"><span class="lbl">اسم المرسل</span><input class="input" name="replied_by" placeholder="اختياري"></label>
        <label class="field"><span class="lbl">نص الرد <span class="req">*</span></span><textarea class="textarea" name="reply_body" required></textarea></label>
        <?= partial('partials/dropzone', ['text' => 'إرفاق مستندات الرد']) ?>
        <div class="row"><button class="btn btn-primary"><?= icon('save', 18) ?>حفظ الرد</button><button class="btn" data-close>إلغاء</button></div>
    </form>
</dialog>
<?php endforeach; endif ?>
<?= $this->endSection() ?>
