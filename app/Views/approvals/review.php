<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $id = $case['id']; ?>
<div class="split">
    <div class="main">
        <?= partial('partials/memo_doc', ['case' => $case, 'memo' => $doc, 'sections' => $sections, 'version' => $version, 'signatures' => $signatures]) ?>
    </div>
    <div class="side" style="width: 370px;">
        <?= partial('partials/card_open', ['icon' => 'checkc', 'title' => 'دورة الاعتماد', 'bodyClass' => 'tight']) ?>
            <div class="vt">
                <div class="vt-item done">
                    <div class="vt-dot"><?= icon('check', 16) ?></div>
                    <div class="step-card"><div class="row between"><span class="hint">المحقق</span><?= badge('تم الإرسال', 'green') ?></div><b><?= esc($case['investigator_name'] ?? '—') ?></b><span class="hint num"><?= fdate($submittedAt, true) ?></span><span class="step-note">النسخة <?= $version ?></span></div>
                </div>
                <?php foreach ($steps as $s): $st = $s['decision'] === 'approved' ? 'done' : ($pending && (int) $pending['id'] === (int) $s['id'] ? 'now' : ''); ?>
                    <div class="vt-item <?= $st ?>">
                        <div class="vt-dot"><?= $st === 'done' ? icon('check', 16) : ($st === 'now' ? icon('eye', 16) : icon('clock', 16)) ?></div>
                        <div class="step-card">
                            <div class="row between"><span class="hint"><?= esc($s['role_name']) ?></span><?= $st === 'now' ? badge('قيد المراجعة', 'teal') : badge(label('decision', $s['decision']), tone('decision', $s['decision'])) ?></div>
                            <b><?= esc($s['approver'] ?: '—') ?></b>
                            <span class="hint num"><?= $s['decided_at'] ? fdate($s['decided_at'], true) : ($st === 'now' ? 'وصلت ' . fdate($submittedAt) : '—') ?></span>
                            <?php if ($s['notes']): ?><span class="step-note"><?= esc($s['notes']) ?></span><?php endif ?>
                            <?php if ($s['signature_ref']): ?><span class="hint num" title="مرجع التوقيع"><?= icon('shield', 12) ?> <?= esc($s['signature_ref']) ?></span><?php endif ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?= partial('partials/card_close') ?>

        <?php if ($returns !== []): ?>
        <?= partial('partials/card_open', ['icon' => 'undo', 'title' => 'سجل الإعادة', 'bodyClass' => 'tight']) ?>
            <?php foreach ($returns as $r): ?>
                <div style="font-size: 14px; line-height: 1.8;"><div class="row between"><b>النسخة <?= (int) $r['memo_version'] ?> · أُعيدت</b><span class="hint num"><?= fdate($r['decided_at']) ?></span></div><span class="muted"><?= esc($r['approver']) ?>: <?= esc($r['notes']) ?></span></div>
            <?php endforeach ?>
        <?= partial('partials/card_close') ?>
        <?php endif ?>

        <?php if ($canDecide): ?>
        <?= partial('partials/card_open', ['icon' => 'pen', 'title' => 'قرارك', 'bodyClass' => 'tight']) ?>
            <form id="approveForm" method="post" action="<?= site_url("cases/{$id}/review") ?>" class="stack" style="gap: 12px;">
                <?= csrf_field() ?><input type="hidden" name="decision" value="approve">
                <label class="field"><span class="lbl">ملاحظات (اختياري)</span><textarea class="textarea" name="notes" rows="2" style="min-height: 70px;"></textarea></label>
            </form>
            <span class="hint row" style="gap: 6px;"><?= icon('shield', 14) ?>يُسجَّل الاعتماد بتوقيع إلكتروني مرتبط بحسابك.</span>
        <?= partial('partials/card_close') ?>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('actions') ?>
<div class="action-bar no-print">
    <?php if ($canDecide): ?>
        <button class="btn btn-primary" type="submit" form="approveForm" onclick="return confirm('اعتماد المذكرة وتوقيعها إلكترونياً؟')"><?= icon('check', 18) ?>اعتماد</button>
        <button class="btn btn-danger" data-open="returnMemo"><?= icon('undo', 18) ?>إعادة للتعديل</button>
    <?php elseif ($case['stage_code'] === 'execution'): ?>
        <a class="btn btn-primary" href="<?= site_url("cases/{$id}/execution") ?>"><?= icon('list', 18) ?>تنفيذ التوصيات</a>
    <?php endif ?>
    <button class="btn" type="button" onclick="window.print()"><?= icon('file', 18) ?>طباعة</button>
    <span class="note"><?= $pending ? 'بانتظار: ' . esc($pending['role_name']) : esc(label('memo', $memo['status'])) ?></span>
</div>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php if ($canDecide): ?>
<dialog class="modal" id="returnMemo">
    <form method="post" action="<?= site_url("cases/{$id}/review") ?>">
        <?= csrf_field() ?><input type="hidden" name="decision" value="return">
        <div class="modal-ico warn"><?= icon('undo', 28) ?></div>
        <div><h2>إعادة المذكرة للتعديل</h2><p>ستعود المذكرة إلى المحقق مع ملاحظاتك، وتبدأ دورة الاعتماد من جديد بعد إعادة إرسالها.</p></div>
        <label class="field"><span class="lbl">سبب الإعادة / الملاحظات <span class="req">*</span></span><textarea class="textarea" name="notes" rows="5" placeholder="وضّح التعديلات المطلوبة…" required></textarea></label>
        <div class="row"><button class="btn btn-primary"><?= icon('send', 18) ?>إرسال للمحقق</button><button class="btn" data-close>إلغاء</button></div>
    </form>
</dialog>
<?php endif ?>
<?= $this->endSection() ?>
