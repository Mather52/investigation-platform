<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $id = $case['id']; ?>
<?= partial('partials/case_bar', ['case' => $case, 'parties' => $parties]) ?>

<?php if ($canEdit): ?>
<datalist id="employees"><?php foreach ($employees as $e): ?><option value="<?= esc($e['employee_no'] . ' — ' . $e['full_name']) ?>"><?= esc($e['dept'] ?? '') ?></option><?php endforeach ?></datalist>
<div class="grid g2" style="align-items: start;">
    <?= partial('partials/card_open', ['id' => 'witness', 'icon' => 'users', 'title' => 'طلب إفادة شاهد']) ?>
        <form method="post" action="<?= site_url("cases/{$id}/statements") ?>" class="stack" style="gap: 18px;">
            <?= csrf_field() ?><input type="hidden" name="request_type" value="witness">
            <label class="field"><span class="lbl">اسم الشاهد <span class="req">*</span></span><input class="input" name="witness" list="employees" value="<?= esc(old('witness')) ?>" placeholder="ابحث بالاسم أو الرقم الوظيفي…" autocomplete="off" required></label>
            <div class="field"><span class="lbl">الإدارة / القسم</span><div class="ro"><span class="muted">تُعبأ آلياً من بيانات الشاهد</span><span class="tag">آلي</span></div></div>
            <label class="field"><span class="lbl">سبب طلب الإفادة <span class="req">*</span></span>
                <select class="select" name="reason" required><option value="">اختر السبب</option><?php foreach (['شاهد على الواقعة', 'زميل في نفس المناوبة', 'مشرف مباشر', 'لديه معلومات ذات صلة'] as $r): ?><option <?= old('reason') === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="lbl">موضوع الإفادة <span class="req">*</span></span><textarea class="textarea" name="subject" rows="3" style="min-height: 90px;" placeholder="ما المطلوب أن يدلي به الشاهد…" required><?= esc(old('subject')) ?></textarea></label>
            <label class="field"><span class="lbl">الموعد المقترح <span class="req">*</span></span><input class="input" type="datetime-local" name="proposed_at" value="<?= esc(old('proposed_at')) ?>" required></label>
            <div><button class="btn btn-primary"><?= icon('send', 18) ?>إرسال طلب الإفادة</button></div>
        </form>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['id' => 'expert', 'icon' => 'target', 'title' => 'طلب رأي مختص']) ?>
        <form method="post" action="<?= site_url("cases/{$id}/statements") ?>" enctype="multipart/form-data" class="stack" style="gap: 18px;">
            <?= csrf_field() ?><input type="hidden" name="request_type" value="expert">
            <label class="field"><span class="lbl">التخصص المطلوب <span class="req">*</span></span><input class="input" name="specialty" value="<?= esc(old('specialty')) ?>" placeholder="مثال: سلامة المرضى، أنظمة الأشعة" required></label>
            <label class="field"><span class="lbl">الجهة / الإدارة <span class="req">*</span></span>
                <select class="select" name="department_id" required><option value="">اختر الجهة</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= old('department_id') == $d['id'] ? 'selected' : '' ?>><?= esc($d['name_ar']) ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="lbl">موضوع الطلب <span class="req">*</span></span><input class="input" name="subject" value="" placeholder="عنوان مختصر للطلب" required></label>
            <label class="field"><span class="lbl">تفاصيل المطلوب <span class="req">*</span></span><textarea class="textarea" name="details" rows="3" style="min-height: 90px;" placeholder="وصف الرأي الفني المطلوب…" required></textarea></label>
            <div class="field"><span class="lbl">المرفقات</span><?= partial('partials/dropzone', ['text' => 'إرفاق مستندات داعمة']) ?></div>
            <div><button class="btn btn-primary"><?= icon('send', 18) ?>إرسال طلب الرأي</button></div>
        </form>
    <?= partial('partials/card_close') ?>
</div>
<?php endif ?>

<?= partial('partials/card_open', ['icon' => 'list', 'title' => 'الطلبات المرسلة', 'right' => '<div class="row hint">' . status_badge('req_status', 'new') . status_badge('req_status', 'pending') . status_badge('req_status', 'answered') . '</div>']) ?>
    <?php if ($rows === []): ?><div class="empty">لا توجد طلبات.</div><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>الجهة / الشخص</th><th>نوع الطلب</th><th>الموضوع</th><th>الموعد / التاريخ</th><th>الحالة</th><th>الإفادة / الرأي</th><?= $canEdit ? '<th></th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $isW = $r['request_type'] === 'witness'; ?>
            <tr>
                <td><?= partial('partials/person', $isW ? ['name' => $r['party_name'], 'sub' => 'شاهد · ' . ($r['department_name'] ?? ''), 'role' => 'witness'] : ['name' => $r['department_name'], 'sub' => 'رأي مختص · ' . $r['specialty'], 'role' => 'expert']) ?></td>
                <td><?= $isW ? 'إفادة شاهد' : 'رأي مختص' ?><?= $isW && $r['reason'] ? '<div class="hint">' . esc($r['reason']) . '</div>' : '' ?></td>
                <td style="max-width: 280px;"><?= esc($r['subject']) ?></td>
                <td class="num"><?= $r['proposed_at'] ? fdate($r['proposed_at'], true) : fdate($r['created_at']) ?></td>
                <td><?= status_badge('req_status', $r['status']) ?></td>
                <td style="max-width: 280px;" class="muted"><?= esc($r['response'] ?? '—') ?></td>
                <?php if ($canEdit): ?>
                <td><div class="row" style="gap: 6px; flex-wrap: nowrap;">
                    <?php if ($isW): ?><a class="btn btn-sm" href="<?= site_url("cases/{$id}/invitations/new?party={$r['party_id']}") ?>"><?= icon('mail', 16) ?>دعوة</a><?php endif ?>
                    <?php if ($r['status'] !== 'answered'): ?><button class="btn btn-sm btn-primary" data-open="resp<?= $r['id'] ?>">تسجيل الإفادة</button><?php endif ?>
                </div></td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
    <?php endif ?>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php if ($canEdit): foreach ($rows as $r): if ($r['status'] === 'answered') continue; ?>
<dialog class="modal" id="resp<?= $r['id'] ?>">
    <form method="post" action="<?= site_url("statements/{$r['id']}/respond") ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="modal-ico"><?= icon('checkc', 28) ?></div>
        <div><h2>تحديث الطلب</h2><p><?= esc($r['subject']) ?></p></div>
        <label class="field"><span class="lbl">الحالة</span><select class="select" name="status"><option value="answered">تمت الإفادة</option><option value="pending">قيد الانتظار</option></select></label>
        <label class="field"><span class="lbl">ملخص الإفادة / الرأي</span><textarea class="textarea" name="response" placeholder="ملخص ما ورد…"></textarea></label>
        <?= partial('partials/dropzone', ['text' => 'إرفاق الإفادة أو الرأي']) ?>
        <div class="row"><button class="btn btn-primary"><?= icon('save', 18) ?>حفظ</button><button class="btn" data-close>إلغاء</button></div>
    </form>
</dialog>
<?php endforeach; endif ?>
<?= $this->endSection() ?>
