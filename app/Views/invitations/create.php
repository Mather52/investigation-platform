<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$id = $case['id'];
$defaultBody = "المكرم/ " . ($party['name'] ?? '') . "  المحترم\nالسلام عليكم ورحمة الله وبركاته،\n\n"
    . "سبب الدعوة: نفيدكم بأنه تقرر دعوتكم للحضور أمام قسم التحقيق بإدارة الشؤون القانونية والالتزام، للإدلاء بأقوالكم بشأن المعاملة رقم {$case['case_no']}.\n"
    . "موعد الجلسة: {التاريخ} الساعة {الوقت}\n"
    . "طريقة الحضور: {طريقة_الحضور}\n"
    . "التعليمات: يُرجى الحضور قبل الموعد بعشر دقائق، وإبراز الهوية الوظيفية عند بدء الجلسة، وإحضار ما لديكم من مستندات. وفي حال تعذر الحضور يُرجى إبلاغ القسم بالعذر قبل الموعد.\n\n"
    . "قسم التحقيق — إدارة الشؤون القانونية والالتزام";
$ptype = $party['party_role'] ?? 'accused';
?>
<?= partial('partials/case_bar', ['case' => $case, 'parties' => $parties]) ?>
<form id="invForm" method="post" action="<?= site_url("cases/{$id}/invitations") ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="reissued_from_id" value="<?= (int) $reissue ?>">
    <?php if ($reissue): ?><div class="alert alert-warn"><?= icon('refresh', 18) ?><span>دعوة جديدة بعد تعذر الحضور بعذر.</span></div><?php endif ?>

    <?= partial('partials/card_open', ['icon' => 'user', 'title' => 'بيانات المدعو']) ?>
        <div class="grid g3">
            <div class="field"><span class="lbl">اسم الموظف <span class="req">*</span></span>
                <select class="select" name="party_id" onchange="location.href='<?= site_url("cases/{$id}/invitations/new") ?>?party=' + this.value<?= $reissue ? " + '&reissue={$reissue}'" : '' ?>">
                    <?php foreach ($parties as $p): ?><option value="<?= $p['id'] ?>" <?= $party && (int) $party['id'] === (int) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?> — <?= label('party', $p['party_role']) ?></option><?php endforeach ?>
                </select>
                <span class="hint">لإضافة شاهد أو مختص جديد استخدم صفحة <a href="<?= site_url("cases/{$id}/statements") ?>">الشهود والمختصون</a>.</span>
            </div>
            <div class="field"><span class="lbl">الرقم الوظيفي</span><div class="ro"><span class="num"><?= esc($party['employee_no'] ?? '—') ?></span><span class="tag">من الموارد البشرية</span></div></div>
            <div class="field"><span class="lbl">الإدارة / القسم</span><div class="ro"><span><?= esc($party['department_name'] ?? '—') ?></span><span class="tag">من الموارد البشرية</span></div></div>
            <div class="field"><span class="lbl">البريد الإلكتروني</span><div class="ro"><span class="num"><?= esc($party['email'] ?? '—') ?></span><span class="tag">من الموارد البشرية</span></div></div>
            <div class="field"><span class="lbl">رقم الجوال</span><div class="ro"><span class="num"><?= esc($party['mobile'] ?? '—') ?></span><span class="tag">من الموارد البشرية</span></div></div>
        </div>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'cal', 'title' => 'بيانات الدعوة']) ?>
        <div class="grid g3">
            <label class="field"><span class="lbl">نوع الدعوة <span class="req">*</span></span>
                <select class="select" name="invitation_type"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= old('invitation_type', $ptype) === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="lbl">تاريخ الجلسة <span class="req">*</span></span><input class="input" type="date" name="session_date" min="<?= date('Y-m-d') ?>" value="<?= esc(old('session_date')) ?>" required></label>
            <label class="field"><span class="lbl">وقت الجلسة <span class="req">*</span></span><input class="input" type="time" name="session_time" value="<?= esc(old('session_time', '10:00')) ?>" required></label>
        </div>
        <div class="field"><span class="lbl">طريقة الحضور <span class="req">*</span></span>
            <div class="choice-row" style="max-width: 760px;">
                <label class="choice"><input type="radio" name="attendance_mode" value="in_person" <?= old('attendance_mode') === 'in_person' ? 'checked' : '' ?>><span><b>حضوري</b><small>مقر قسم التحقيق</small></span></label>
                <label class="choice"><input type="radio" name="attendance_mode" value="remote" <?= old('attendance_mode', 'remote') === 'remote' ? 'checked' : '' ?>><span><b>عن بُعد</b><small>جلسة مرئية آمنة</small></span></label>
            </div>
        </div>
        <label class="field" style="max-width: 760px;" data-show-when="attendance_mode=remote"><span class="lbl">رابط الجلسة</span>
            <input class="input num" name="meeting_link" value="<?= esc(old('meeting_link', $link)) ?>" dir="ltr">
            <span class="hint">رابط مقترح يُولَّد آلياً. استبدله برابط منصة الاجتماعات المعتمدة لديكم.</span></label>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'file', 'title' => 'نص الدعوة', 'right' => '<div class="row hint">حالة الدعوة: ' . status_badge('inv_status', 'draft') . ' ← ' . status_badge('inv_status', 'sent') . ' ← ' . status_badge('inv_status', 'read') . '</div>']) ?>
        <textarea class="textarea letter" name="body" rows="11" required><?= esc(old('body', $defaultBody)) ?></textarea>
        <span class="hint">الرموز {التاريخ} و{الوقت} و{طريقة_الحضور} تُستبدل آلياً بالقيم المختارة عند الإرسال.</span>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'send', 'title' => 'وسائل الإرسال']) ?>
        <div class="choice-row">
            <label class="choice"><input type="checkbox" name="via[]" value="email" checked><span><b>البريد الإلكتروني</b><small class="num"><?= esc($party['email'] ?? 'غير متوفر') ?></small></span></label>
            <label class="choice"><input type="checkbox" name="via[]" value="sms" checked><span><b>الجوال</b><small>رسالة نصية</small></span></label>
            <label class="choice"><input type="checkbox" name="via[]" value="enjaz" checked><span><b>نظام إنجاز</b><small>إشعار داخل النظام</small></span></label>
        </div>
        <span class="hint">تُسجَّل الوسائل المختارة مع الدعوة، ويصل المدعو إشعار داخل المنصة إن كان لديه حساب. الإرسال الفعلي للبريد والجوال وإنجاز يُفعّل بعد الربط التقني.</span>
    <?= partial('partials/card_close') ?>
</form>
<?= $this->endSection() ?>
<?= $this->section('actions') ?>
<div class="action-bar">
    <button class="btn btn-primary" type="submit" form="invForm" name="action" value="send"><?= icon('send', 18) ?>إرسال الدعوة</button>
    <button class="btn" type="submit" form="invForm" name="action" value="draft"><?= icon('save', 18) ?>حفظ كمسودة</button>
    <a class="btn btn-ghost" href="<?= site_url("cases/{$case['id']}") ?>">إلغاء</a>
</div>
<?= $this->endSection() ?>
