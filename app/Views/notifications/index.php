<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Notifications;
$q = static fn (array $p) => site_url('notifications') . '?' . http_build_query(array_filter($p));
$ago = static function (string $t): string {
    $m = (int) floor((time() - strtotime($t)) / 60);
    return $m < 1 ? 'الآن' : ($m < 60 ? "قبل {$m} دقيقة" : ($m < 1440 ? 'قبل ' . floor($m / 60) . ' ساعة' : fdate($t, true)));
};
?>
<div class="row between">
    <div class="chips">
        <a class="chip <?= $category === '' ? 'is-active' : '' ?>" href="<?= $q(['level' => $level]) ?>">الكل <span class="n num"><?= array_sum($counts) ?></span></a>
        <?php foreach (Notifications::ICONS as $k => $ic): ?>
            <a class="chip <?= $category === $k ? 'is-active' : '' ?>" href="<?= $q(['category' => $k, 'level' => $level]) ?>"><?= icon($ic, 16) ?><?= label('category', $k) ?> <span class="n num"><?= (int) ($counts[$k] ?? 0) ?></span></a>
        <?php endforeach ?>
    </div>
</div>

<section class="card">
    <div class="card-head">
        <div class="card-title"><?= icon('bell', 22) ?><h2>الإشعارات</h2><?php if ($unread): ?><?= badge($unread . ' غير مقروءة', 'teal') ?><?php endif ?></div>
        <div class="row">
            <div class="chips">
                <?php foreach (['green' => 'عادي', 'yellow' => 'يقترب الموعد', 'red' => 'متأخر'] as $lv => $lt): ?>
                    <a class="chip <?= $level === $lv ? 'is-active' : '' ?>" style="height: 34px;" href="<?= $q(['category' => $category, 'level' => $level === $lv ? '' : $lv]) ?>"><i class="dot" style="width: 8px; height: 8px; border-radius: 50%; background: var(--<?= $lv[0] ?>);"></i><?= $lt ?></a>
                <?php endforeach ?>
            </div>
            <?php if ($unread): ?><form method="post" action="<?= site_url('notifications/read-all') ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= icon('check', 16) ?>تعليم الكل كمقروء</button></form><?php endif ?>
        </div>
    </div>
    <?php if ($rows === []): ?><div class="card-body"><div class="empty">لا توجد إشعارات.</div></div><?php endif ?>
    <?php foreach ($rows as $n): ?>
        <a class="notif <?= $n['level'] ?> <?= $n['read_at'] ? '' : 'unread' ?>" href="<?= site_url('notifications/' . $n['id']) ?>">
            <div class="notif-ico"><?= icon(Notifications::ICONS[$n['category']] ?? 'bell', 22) ?></div>
            <div class="notif-main">
                <div class="row" style="gap: 8px;"><b style="font-size: 16px;"><?= esc($n['title']) ?></b><?php if ($n['case_no']): ?><span class="muted num" style="font-size: 13px;"><?= esc($n['case_no']) ?></span><?php endif ?></div>
                <span class="muted" style="font-size: 14px;"><?= esc($n['body']) ?></span>
            </div>
            <div class="notif-end"><span class="num"><?= $ago($n['created_at']) ?></span><?= badge(label('level', $n['level']), $n['level']) ?><?php if (! $n['read_at']): ?><span class="unread-dot" title="غير مقروء"></span><?php endif ?></div>
        </a>
    <?php endforeach ?>
</section>
<?= $this->endSection() ?>
