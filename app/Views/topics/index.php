<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$id = $case['id'];
$total = count($topics);
$done = count(array_filter($topics, fn ($t) => $t['status'] === 'done'));
$current = min($total, $done + 1);
$prog = $total ? '<div class="row"><b class="num" style="font-size: 14px;">المحور ' . $current . ' من ' . $total . '</b><div class="progress"><i style="width: ' . round($done / $total * 100) . '%"></i></div></div>' : '';
?>
<?= partial('partials/case_bar', ['case' => $case, 'parties' => $parties]) ?>

<?= partial('partials/card_open', ['icon' => 'target', 'title' => 'موضوع التحقيق']) ?>
    <strong style="font-size: 16px;"><?= esc($case['subject']) ?></strong>
    <p style="line-height: 1.9; white-space: pre-wrap;"><?= esc($case['description']) ?></p>
<?= partial('partials/card_close') ?>

<?= partial('partials/card_open', ['icon' => 'list', 'title' => 'محاور وأسئلة التحقيق', 'right' => $prog]) ?>
    <?php if ($topics === []): ?><div class="empty">لا توجد محاور بعد. أضف أول محور للتحقيق.</div><?php endif ?>
    <?php foreach ($topics as $i => $t): $isOpen = $open ? (int) $t['id'] === $open : $i === $done; ?>
        <details class="topic" id="t<?= $t['id'] ?>" <?= $isOpen ? 'open' : '' ?>>
            <summary><span class="row"><span class="topic-num num" <?= $t['status'] === 'done' ? 'style="background: var(--primary); color: #fff;"' : '' ?>><?= $i + 1 ?></span><b style="font-size: 17px;"><?= esc($t['title']) ?></b><span class="hint num"><?= count($t['questions']) ?> أسئلة</span></span><?= badge(label('topic', $t['status']), tone('topic', $t['status'])) ?></summary>
            <form class="topic-body" method="post" action="<?= site_url('topics/' . $t['id']) ?>">
                <?= csrf_field() ?>
                <div class="grid g2">
                    <label class="field"><span class="lbl">محور التحقيق</span><input class="input" name="title" value="<?= esc($t['title']) ?>" <?= $canEdit ? '' : 'readonly' ?>></label>
                    <label class="field"><span class="lbl">حالة المحور</span><select class="select" name="status" <?= $canEdit ? '' : 'disabled' ?>><?php foreach (['not_started', 'in_progress', 'done'] as $st): ?><option value="<?= $st ?>" <?= $t['status'] === $st ? 'selected' : '' ?>><?= label('topic', $st) ?></option><?php endforeach ?></select></label>
                </div>
                <?php foreach ($t['questions'] as $n => $q): ?>
                    <div class="q-card">
                        <div class="row between"><b>السؤال <?= $n + 1 ?></b><?= $q['session_no'] ? badge('من محضر الجلسة ' . $q['session_no'], 'teal') : '' ?></div>
                        <label class="field"><span class="lbl">السؤال</span><input class="input" name="q[<?= $q['id'] ?>][question]" value="<?= esc($q['question']) ?>" <?= $canEdit ? '' : 'readonly' ?>></label>
                        <div class="grid g2">
                            <label class="field"><span class="lbl">الإجابة</span><textarea class="textarea" name="q[<?= $q['id'] ?>][answer]" rows="3" style="min-height: 90px;" placeholder="إجابة الموظف كما وردت في الجلسة…" <?= $canEdit ? '' : 'readonly' ?>><?= esc($q['answer']) ?></textarea></label>
                            <label class="field"><span class="lbl">ملاحظات المحقق</span><textarea class="textarea" name="q[<?= $q['id'] ?>][notes]" rows="3" style="min-height: 90px;" placeholder="ملاحظات داخلية لا تظهر للموظف…" <?= $canEdit ? '' : 'readonly' ?>><?= esc($q['investigator_notes']) ?></textarea></label>
                        </div>
                    </div>
                <?php endforeach ?>
                <?php if ($canEdit): ?>
                    <div class="q-card" style="background: #fff; border-style: dashed;">
                        <b>سؤال جديد</b>
                        <label class="field"><span class="lbl">السؤال</span><input class="input" name="new[question]" placeholder="اكتب السؤال…"></label>
                        <div class="grid g2">
                            <label class="field"><span class="lbl">الإجابة</span><textarea class="textarea" name="new[answer]" rows="2" style="min-height: 70px;"></textarea></label>
                            <label class="field"><span class="lbl">ملاحظات المحقق</span><textarea class="textarea" name="new[notes]" rows="2" style="min-height: 70px;"></textarea></label>
                        </div>
                    </div>
                    <div><button class="btn btn-primary"><?= icon('save', 18) ?>حفظ</button></div>
                <?php endif ?>
            </form>
        </details>
    <?php endforeach ?>

    <?php if ($canEdit): ?>
        <form method="post" action="<?= site_url("cases/{$id}/topics") ?>" class="row" style="flex-wrap: nowrap;">
            <?= csrf_field() ?>
            <input class="input" name="title" placeholder="عنوان المحور الجديد، مثال: الالتزام بإجراءات التسليم والاستلام" required>
            <button class="btn btn-primary" style="flex-shrink: 0;"><?= icon('plus', 18) ?>إضافة محور</button>
        </form>
    <?php endif ?>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>
<?= $this->section('actions') ?>
<div class="action-bar">
    <a class="btn" href="<?= site_url("cases/{$case['id']}/attendance") ?>"><?= icon('video', 18) ?>الجلسات</a>
    <a class="btn btn-primary" href="<?= site_url("cases/{$case['id']}/memo") ?>"><?= icon('file', 18) ?>الانتقال للمذكرة</a>
</div>
<?= $this->endSection() ?>
