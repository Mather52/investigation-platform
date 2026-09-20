<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$id = $case['id'];
$accused = array_column(array_filter($parties, fn ($p) => $p['party_role'] === 'accused'), 'name');
$compl = array_column(array_filter($parties, fn ($p) => $p['party_role'] === 'complainant'), 'name');
$order = ['draft' => 0, 'new' => 1, 'with_gm' => 1, 'referred' => 2, 'investigation' => 3];
$pos = $order[$case['stage_code']] ?? 4;
$steps = [['إنشاء المعاملة', fdate($case['created_at']) . ' · ' . ($case['creator_name'] ?? '')], ['الإحالة', $pos <= 2 ? 'بانتظار إجراء ' . ($case['holder_name'] ?? '') : 'تمت'], ['التحقيق', $case['investigator_name'] ? 'المحقق: ' . $case['investigator_name'] : 'يبدأ بعد تعيين المحقق']];
$state = static fn (int $i) => $i === 0 || ($i === 1 && $pos >= 3) || ($i === 2 && $pos >= 4) ? 'done' : (($i === 1 && $pos <= 2) || ($i === 2 && $pos === 3) ? 'now' : '');
?>
<?= partial('partials/card_open', ['icon' => 'folder', 'title' => 'ملخص المعاملة', 'right' => $case['confidentiality'] !== 'normal' ? '<span class="secure">' . icon('lock', 14) . 'سري — للاطلاع المصرح به فقط</span>' : '']) ?>
    <div class="grid g4">
        <div class="kv"><span>رقم المعاملة</span><strong class="num"><?= esc($case['case_no']) ?></strong></div>
        <div class="kv"><span>نوع المعاملة</span><strong><?= esc($case['type_name']) ?></strong></div>
        <div class="kv"><span>تاريخ الإنشاء</span><strong class="num"><?= fdate($case['created_at']) ?></strong></div>
        <div class="kv"><span>مصدر المعاملة</span><strong><?= esc($case['source_name']) ?></strong></div>
        <div class="kv"><span>مقدم الشكوى</span><strong><?= esc(implode('، ', $compl) ?: 'من الإدارة') ?></strong></div>
        <div class="kv"><span>الموظف / الموظفون محل التحقيق</span><strong><?= esc(implode('، ', $accused)) ?></strong></div>
        <div class="kv"><span>الإدارة / القسم</span><strong><?= esc($case['department_name'] ?? '—') ?></strong></div>
        <div class="kv"><span>حالة المعاملة</span><span><?= badge($case['stage_name'], stage_tone($case['stage_code']), true) ?></span></div>
    </div>
<?= partial('partials/card_close') ?>

<div class="split">
    <div class="main">
        <?= partial('partials/card_open', ['icon' => 'share', 'title' => 'بيانات الإحالة']) ?>
        <?php if ($actions === []): ?>
            <div class="alert alert-info"><?= icon('alert', 18) ?><span>لا يوجد إجراء إحالة متاح لك في هذه المرحلة. المعاملة الآن لدى: <?= esc($case['holder_name'] ?? '—') ?>.</span></div>
        <?php else: ?>
            <form id="refForm" method="post" action="<?= site_url("cases/{$id}/referral") ?>" class="stack" style="gap: 18px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="refAction" value="">
                <?php if (in_array('assign', $actions, true)): ?>
                    <div class="grid g2">
                        <div class="field"><span class="lbl">الجهة المحال إليها</span><div class="ro"><span>قسم التحقيق — محقق</span></div></div>
                        <label class="field"><span class="lbl">المحقق <span class="req">*</span></span>
                            <select class="select" name="investigator_user_id" required><option value="">اختر المحقق</option>
                                <?php foreach ($investigators as $u): ?><option value="<?= $u['id'] ?>"><?= esc($u['name']) ?></option><?php endforeach ?>
                            </select></label>
                    </div>
                <?php else: ?>
                    <div class="grid g2">
                        <div class="field"><span class="lbl">الجهة المحال إليها</span><div class="ro"><span>قسم التحقيق</span></div></div>
                        <label class="field"><span class="lbl">المسؤول / رئيس التحقيقات <span class="req">*</span></span>
                            <select class="select" name="head_user_id">
                                <?php foreach ($heads as $u): ?><option value="<?= $u['id'] ?>"><?= esc($u['name']) ?></option><?php endforeach ?>
                            </select></label>
                    </div>
                <?php endif ?>
                <label class="field"><span class="lbl">سبب الإحالة</span>
                    <select class="select" name="reason"><option value="">اختر سبب الإحالة</option>
                        <?php foreach (['مخالفة تستوجب التحقيق', 'شكوى تستوجب التحقق', 'توجيه من الإدارة العليا', 'استكمال إجراءات سابقة'] as $r): ?><option><?= $r ?></option><?php endforeach ?>
                    </select></label>
                <label class="field"><span class="lbl">ملاحظات الإحالة</span><textarea class="textarea" name="notes" placeholder="توجيهات أو ملاحظات للجهة المحال إليها…"><?= esc(old('notes')) ?></textarea><span class="hint">مطلوبة عند حفظ المعاملة دون إجراء.</span></label>
            </form>
        <?php endif ?>
        <?= partial('partials/card_close') ?>

        <?php if ($history !== []): ?>
        <?= partial('partials/card_open', ['icon' => 'clock', 'title' => 'سجل الإحالات']) ?>
            <div class="table-wrap"><table>
                <thead><tr><th>الإجراء</th><th>من</th><th>إلى</th><th>السبب / الملاحظات</th><th>التاريخ</th></tr></thead>
                <tbody><?php foreach ($history as $h): ?>
                    <tr><td><?= ['refer_head' => 'إحالة لرئيس التحقيقات', 'refer_gm' => 'إحالة للمدير العام', 'assign_investigator' => 'تعيين محقق'][$h['action']] ?></td>
                        <td><?= esc($h['from_name']) ?></td><td><?= esc($h['to_name'] ?? $h['role_name']) ?></td>
                        <td class="muted"><?= esc(trim(($h['reason'] ?? '') . ' ' . ($h['notes'] ?? '')) ?: '—') ?></td><td class="num"><?= fdate($h['created_at'], true) ?></td></tr>
                <?php endforeach ?></tbody>
            </table></div>
        <?= partial('partials/card_close') ?>
        <?php endif ?>
    </div>
    <div class="side narrow">
        <?= partial('partials/card_open', ['icon' => 'clock', 'title' => 'مسار المعاملة']) ?>
            <div class="vt">
                <?php foreach ($steps as $i => [$t, $s]): $st = $state($i); ?>
                    <div class="vt-item <?= $st ?>"><div class="vt-dot"><?= $st === 'done' ? icon('check', 16) : '<i></i>' ?></div><div class="vt-text"><b><?= esc($t) ?></b><small><?= esc($s) ?></small></div></div>
                <?php endforeach ?>
            </div>
        <?= partial('partials/card_close') ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('actions') ?>
<?php if ($actions !== []): ?>
<div class="action-bar">
    <?php if (in_array('refer_head', $actions, true)): ?><button class="btn btn-primary" data-open="confirmHead"><?= icon('share', 18) ?>إحالة إلى رئيس التحقيقات</button><?php endif ?>
    <?php if (in_array('refer_gm', $actions, true)): ?><button class="btn" data-open="confirmGm"><?= icon('check', 18) ?>موافقة وإحالة للمدير العام التنفيذي</button><?php endif ?>
    <?php if (in_array('assign', $actions, true)): ?><button class="btn btn-primary" data-open="confirmAssign"><?= icon('user', 18) ?>تعيين المحقق وبدء التحقيق</button><?php endif ?>
    <?php if (in_array('close', $actions, true)): ?><button class="btn btn-danger" data-open="confirmClose"><?= icon('archive', 18) ?>حفظ دون إجراء</button><?php endif ?>
    <a class="btn btn-ghost" href="<?= site_url('cases/' . $case['id']) ?>">إلغاء</a>
</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php foreach ([
    ['confirmHead', 'refer_head', 'share', '', 'تأكيد إحالة المعاملة', 'هل أنت متأكد من إحالة هذه المعاملة إلى رئيس التحقيقات؟', 'تأكيد الإحالة', 'btn-primary'],
    ['confirmGm', 'refer_gm', 'share', '', 'إحالة للمدير العام التنفيذي', 'ستُحال المعاملة للمدير العام التنفيذي للاطلاع والتوجيه قبل التحقيق.', 'تأكيد الإحالة', 'btn-primary'],
    ['confirmAssign', 'assign', 'user', '', 'تأكيد تعيين المحقق', 'سيُسند التحقيق للمحقق المختار وتبدأ مرحلة التحقيق.', 'تأكيد التعيين', 'btn-primary'],
    ['confirmClose', 'close', 'archive', 'danger', 'حفظ المعاملة دون إجراء', 'ستُغلق المعاملة ولن تُحال للتحقيق. تأكد من كتابة السبب في الملاحظات.', 'تأكيد الحفظ', 'btn-danger-solid'],
] as [$did, $act, $ic, $tone, $h, $p, $ok, $cls]): if (! in_array($act, $actions, true)) continue; ?>
<dialog class="modal" id="<?= $did ?>">
    <div class="modal-inner">
        <div class="modal-ico <?= $tone ?>"><?= icon($ic, 28) ?></div>
        <div><h2><?= $h ?></h2><p><?= $p ?></p></div>
        <div class="summary"><div><span class="muted">المعاملة</span><b class="num"><?= esc($case['case_no']) ?></b></div><div><span class="muted">الموضوع</span><b><?= esc($case['subject']) ?></b></div></div>
        <div class="row">
            <button class="btn <?= $cls ?>" type="submit" form="refForm" onclick="document.getElementById('refAction').value='<?= $act ?>'"><?= icon('check', 18) ?><?= $ok ?></button>
            <button class="btn" data-close>إلغاء</button>
        </div>
    </div>
</dialog>
<?php endforeach ?>
<?= $this->endSection() ?>
