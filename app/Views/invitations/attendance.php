<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$id = $case['id'];
$cnt = static fn ($s) => count(array_filter($rows, fn ($r) => $r['attendance_status'] === $s));
?>
<?= partial('partials/case_bar', ['case' => $case, 'parties' => $parties]) ?>
<div class="grid g4">
    <?php foreach ([['إجمالي المدعوين', count($rows), 'var(--ink)'], ['حضر', $cnt('attended'), 'var(--g)'], ['غياب بعذر', $cnt('excused'), 'var(--y)'], ['غياب بدون عذر', $cnt('unexcused'), 'var(--r)']] as [$l, $n, $c]): ?>
        <div class="card row between" style="padding: 16px 20px;"><span class="muted"><?= $l ?></span><strong class="num" style="font-size: 26px; color: <?= $c ?>;"><?= $n ?></strong></div>
    <?php endforeach ?>
</div>

<?= partial('partials/card_open', ['icon' => 'users', 'title' => 'المدعوون للجلسات', 'right' => $canAct ? '<a class="btn btn-primary btn-sm" href="' . site_url("cases/{$id}/invitations/new") . '">' . icon('plus', 16) . 'دعوة جديدة</a>' : '']) ?>
    <?php if ($rows === []): ?>
        <div class="empty">لم تُرسل دعوات بعد.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>الاسم</th><th>نوع الحضور</th><th>موعد الجلسة</th><th>حالة الدعوة</th><th>حالة الحضور</th><th>الإجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= partial('partials/person', ['name' => $r['name'], 'sub' => label('party', $r['party_role']), 'role' => $r['party_role']]) ?></td>
                <td><?= badge(label('mode', $r['attendance_mode']), $r['attendance_mode'] === 'remote' ? 'blue' : 'gray') ?></td>
                <td class="num"><?= fdate($r['session_at'], true) ?></td>
                <td><?= badge(label('inv_status', $r['status']), tone('inv_status', $r['status'])) ?></td>
                <td><?= status_badge('attendance', $r['attendance_status']) ?><?php if ($r['excuse_note']): ?><div class="hint"><?= esc($r['excuse_note']) ?></div><?php endif ?></td>
                <td>
                    <div class="row" style="gap: 8px;">
                    <?php if ($r['session_id']): ?>
                        <a class="btn btn-sm btn-primary" href="<?= site_url('sessions/' . $r['session_id']) ?>"><?= icon('video', 16) ?>الجلسة</a>
                    <?php endif ?>
                    <?php if ($canAct): ?>
                        <?php if ($r['status'] === 'draft'): ?>
                            <span class="hint">مسودة — أنشئ دعوة جديدة لإرسالها</span>
                        <?php elseif ($r['attendance_status'] === 'pending'): ?>
                            <form method="post" action="<?= site_url("invitations/{$r['id']}/attendance") ?>" class="row" style="gap: 6px; flex-wrap: nowrap;">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm" name="attendance_status" value="attended">حضر</button>
                                <button class="btn btn-sm" type="button" data-open="excuse<?= $r['id'] ?>">بعذر</button>
                                <button class="btn btn-sm" name="attendance_status" value="unexcused">بدون عذر</button>
                            </form>
                        <?php elseif ($r['attendance_status'] === 'attended' && ! $r['session_id']): ?>
                            <form method="post" action="<?= site_url("invitations/{$r['id']}/session") ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary"><?= icon('video', 16) ?>بدء الجلسة</button></form>
                        <?php elseif ($r['attendance_status'] === 'excused'): ?>
                            <?php if ($r['reissued']): ?><span class="hint">أُرسلت دعوة جديدة</span><?php else: ?>
                            <a class="btn btn-sm" href="<?= site_url("cases/{$id}/invitations/new?party={$r['pid']}&reissue={$r['id']}") ?>"><?= icon('refresh', 16) ?>إرسال دعوة جديدة</a><?php endif ?>
                        <?php elseif ($r['attendance_status'] === 'unexcused'): ?>
                            <?php if ($r['absence_action_at']): ?><?= badge('طُبق إجراء الغياب', 'red') ?><?php else: ?>
                            <form method="post" action="<?= site_url("invitations/{$r['id']}/absence") ?>" data-confirm="تطبيق إجراء الغياب بدون عذر وتوثيقه في سجل المعاملة؟"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= icon('alert', 16) ?>تطبيق إجراء الغياب</button></form><?php endif ?>
                        <?php endif ?>
                    <?php endif ?>
                    <a class="btn btn-sm btn-ghost" href="<?= site_url('invitations/' . $r['id']) ?>" title="عرض الدعوة"><?= icon('eye', 16) ?></a>
                    </div>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
    <?php endif ?>
    <div class="row hint" style="gap: 24px;">
        <span><?= status_badge('attendance', 'attended') ?> يبدأ المحقق الجلسة</span>
        <span><?= status_badge('attendance', 'excused') ?> تُرسل دعوة جديدة</span>
        <span><?= status_badge('attendance', 'unexcused') ?> يُطبق إجراء الغياب ويُتعامل وفق الإثباتات المتوفرة</span>
    </div>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php foreach ($rows as $r): if ($r['attendance_status'] !== 'pending' || ! $canAct) continue; ?>
<dialog class="modal" id="excuse<?= $r['id'] ?>">
    <form method="post" action="<?= site_url("invitations/{$r['id']}/attendance") ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="attendance_status" value="excused">
        <div class="modal-ico warn"><?= icon('clock', 28) ?></div>
        <div><h2>لم يحضر بعذر</h2><p><?= esc($r['name']) ?> — سجّل العذر ثم أرسل دعوة جديدة.</p></div>
        <label class="field"><span class="lbl">العذر</span><textarea class="textarea" name="excuse_note" placeholder="مثال: إجازة مرضية مثبتة"></textarea></label>
        <div class="row"><button class="btn btn-primary"><?= icon('check', 18) ?>حفظ</button><button class="btn" data-close>إلغاء</button></div>
    </form>
</dialog>
<?php endforeach ?>
<?= $this->endSection() ?>
