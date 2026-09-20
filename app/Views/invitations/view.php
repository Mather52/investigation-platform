<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="split">
    <div class="main">
        <?= partial('partials/card_open', ['icon' => 'mail', 'title' => 'نص الدعوة', 'right' => badge(label('inv_status', $inv['status']), tone('inv_status', $inv['status']))]) ?>
            <div class="letter"><?= esc($inv['body']) ?></div>
        <?= partial('partials/card_close') ?>
    </div>
    <div class="side">
        <?= partial('partials/card_open', ['icon' => 'cal', 'title' => 'تفاصيل الجلسة']) ?>
            <div class="kv"><span>المعاملة</span><strong class="num"><?= esc($inv['case_no']) ?></strong></div>
            <div class="kv"><span>المدعو</span><strong><?= esc($inv['name']) ?></strong></div>
            <div class="kv"><span>الموعد</span><strong class="num"><?= fdate($inv['session_at'], true) ?></strong></div>
            <div class="kv"><span>طريقة الحضور</span><strong><?= label('mode', $inv['attendance_mode']) ?></strong></div>
            <?php if ($inv['meeting_link']): ?><div class="kv"><span>رابط الجلسة</span><a class="num" dir="ltr" href="<?= esc($inv['meeting_link'], 'attr') ?>" target="_blank" rel="noopener"><?= esc($inv['meeting_link']) ?></a></div><?php endif ?>
            <?php if ($session): ?><a class="btn btn-primary" href="<?= site_url('sessions/' . $session['id']) ?>"><?= icon('video', 18) ?>الدخول إلى الجلسة</a><?php endif ?>
        <?= partial('partials/card_close') ?>
    </div>
</div>
<?= $this->endSection() ?>
