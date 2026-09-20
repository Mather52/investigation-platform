<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$id = $case['id'];
$filled = array_filter(array_keys($sections), fn ($k) => trim((string) ($memo[$k] ?? '')) !== '');
$pct = round(count($filled) / 5 * 100);
?>
<?php if ($memo['status'] === 'returned' && $lastReturn): ?>
    <div class="alert alert-warn"><?= icon('undo', 18) ?><span><b>أُعيدت المذكرة للتعديل</b> من <?= esc($lastReturn['approver'] ?? $lastReturn['role_name']) ?> (<?= esc($lastReturn['role_name']) ?>) بتاريخ <?= fdate($lastReturn['decided_at']) ?>: <?= esc($lastReturn['notes']) ?></span></div>
<?php elseif (! $canEdit): ?>
    <div class="alert alert-info"><?= icon('lock', 18) ?><span>المذكرة <?= esc(label('memo', $memo['status'])) ?> ولا يمكن تعديلها الآن.</span></div>
<?php endif ?>

<div class="split">
    <div class="side narrow" style="position: sticky; top: 16px;">
        <?= partial('partials/card_open', ['icon' => 'list', 'title' => 'أقسام المذكرة', 'bodyClass' => 'tight']) ?>
            <div class="row between"><span class="muted">الإنجاز</span><b class="num"><?= count($filled) ?> من 5</b></div>
            <div class="progress" style="width: 100%;"><i style="width: <?= $pct ?>%"></i></div>
            <nav class="memo-nav">
                <?php foreach ($sections as $k => $t): $on = in_array($k, $filled, true); ?>
                    <a href="#sec-<?= $k ?>"><span class="ck <?= $on ? 'on' : '' ?>"><?= $on ? icon('check', 12) : '' ?></span><?= esc($t) ?></a>
                <?php endforeach ?>
            </nav>
        <?= partial('partials/card_close') ?>
        <div class="card card-plain" style="gap: 8px; font-size: 13px;">
            <span class="row" style="gap: 6px;"><?= badge(label('memo', $memo['status']), tone('memo', $memo['status'])) ?> <?= badge('النسخة ' . $memo['version'], 'teal') ?></span>
            <span class="muted">آخر حفظ: <span class="num"><?= fdate($memo['updated_at'] ?? $memo['created_at'], true) ?></span></span>
            <span class="muted">المحقق: <?= esc($case['investigator_name'] ?? '—') ?></span>
        </div>
    </div>

    <div class="main">
        <div class="card card-plain">
            <div class="row between"><h2 style="font-size: 19px;">بيانات المعاملة</h2><?php if ($case['confidentiality'] !== 'normal'): ?><span class="secure"><?= icon('lock', 14) ?>سري — للاطلاع المصرح به فقط</span><?php endif ?></div>
            <div class="grid g4">
                <div class="kv"><span>رقم المعاملة</span><strong class="num"><?= esc($case['case_no']) ?></strong></div>
                <div class="kv"><span>نوع المعاملة</span><strong><?= esc($case['type_name']) ?></strong></div>
                <div class="kv"><span>الموظف محل التحقيق</span><strong><?= esc(implode('، ', array_column(array_filter($parties, fn ($p) => $p['party_role'] === 'accused'), 'name'))) ?></strong></div>
                <div class="kv"><span>المحقق</span><strong><?= esc($case['investigator_name'] ?? '—') ?></strong></div>
            </div>
        </div>

        <form id="memoForm" method="post" action="<?= site_url("cases/{$id}/memo") ?>" class="card card-plain" style="padding: 28px; gap: 28px;">
            <?= csrf_field() ?>
            <?php $i = 1; foreach ($sections as $k => $t): $on = in_array($k, $filled, true); ?>
                <div class="memo-sec" id="sec-<?= $k ?>">
                    <div class="row between">
                        <div class="row"><span class="sec-num num"><?= $i++ ?></span><h3 style="font-size: 18px;"><?= esc($t) ?></h3></div>
                        <div class="row">
                            <?php if ($canEdit && ! empty($suggest[$k])): ?><button type="button" class="btn btn-sm btn-ghost" onclick="var t=document.getElementById('f-<?= $k ?>'); if(!t.value.trim() || confirm('استبدال النص الحالي بالنص المقترح؟')) t.value=this.dataset.text;" data-text="<?= esc($suggest[$k], 'attr') ?>"><?= icon('refresh', 16) ?>تعبئة من بيانات المعاملة</button><?php endif ?>
                            <?= $on ? badge('مكتمل', 'green') : badge('بحاجة لإكمال', 'yellow') ?>
                        </div>
                    </div>
                    <textarea class="textarea" id="f-<?= $k ?>" name="<?= $k ?>" rows="<?= in_array($k, ['findings', 'recommendations'], true) ? 7 : 5 ?>" placeholder="اكتب هنا…" <?= $canEdit ? '' : 'readonly' ?>><?= esc($memo[$k] ?? '') ?></textarea>
                </div>
            <?php endforeach ?>
            <?php if ($canEdit): ?><p class="hint">اكتب كل توصية في سطر مستقل، لتُنقل كما هي إلى متابعة التنفيذ بعد الاعتماد.</p><?php endif ?>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('actions') ?>
<div class="action-bar">
    <?php if ($canEdit): ?>
        <button class="btn btn-primary" type="submit" form="memoForm" name="action" value="submit" onclick="return confirm('إرسال المذكرة إلى رئيس التحقيقات للمراجعة؟ لن تتمكن من تعديلها حتى تُعاد إليك.')"><?= icon('send', 18) ?>إرسال للمراجعة</button>
    <?php endif ?>
    <a class="btn" href="<?= site_url("cases/{$case['id']}/memo/preview") ?>"><?= icon('eye', 18) ?>معاينة المذكرة</a>
    <?php if ($canEdit): ?><button class="btn" type="submit" form="memoForm" name="action" value="save"><?= icon('save', 18) ?>حفظ مسودة</button><?php endif ?>
    <?php if (in_array($case['stage_code'], ['approval', 'execution', 'archived'], true)): ?><a class="btn btn-ghost" href="<?= site_url("cases/{$case['id']}/review") ?>">دورة الاعتماد</a><?php endif ?>
</div>
<?= $this->endSection() ?>
