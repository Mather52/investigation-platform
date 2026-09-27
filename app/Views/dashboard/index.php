<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$tones = ['teal' => ['var(--t-bg)', 'var(--t)'], 'blue' => ['var(--b-bg)', 'var(--b)'], 'gray' => ['var(--n-bg)', 'var(--n)'], 'yellow' => ['var(--y-bg)', 'var(--y)']];
$palette = ['#1E5AA8', '#5B8FD1', '#C08A2B', '#9DBEE6', '#D8C8A6', '#8A9294'];
?>
<section class="hero">
    <div class="hero-text">
        <span class="hero-date num"><?= icon('cal', 16) ?><?= esc($today) ?></span>
        <h1><?= esc($greeting) ?><?= $firstName !== '' ? '، ' . esc($firstName) : '' ?></h1>
        <p><?= $need === [] ? 'لا توجد معاملات بانتظار إجراء منك الآن.' : 'لديك <b class="num">' . count($need) . '</b> ' . (count($need) === 1 ? 'معاملة تحتاج' : 'معاملات تحتاج') . ' إلى إجراء.' ?></p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-light" href="<?= site_url('cases/new') ?>"><?= icon('plus', 18) ?>إنشاء مخالفة / شكوى</a>
        <a class="btn btn-glass" href="<?= site_url('consultations/new') ?>"><?= icon('msg', 18) ?>طلب استشارة</a>
        <?php if ($canReports): ?><a class="btn btn-glass" href="<?= site_url('reports') ?>"><?= icon('chart', 18) ?>التقارير</a><?php endif ?>
    </div>
</section>

<div class="stats">
    <?php foreach ($kpis as [$label, $value, $ic, $tone, $url]): ?>
        <a class="stat" href="<?= site_url($url) ?>">
            <span class="stat-ico" style="background: <?= $tones[$tone][0] ?>; color: <?= $tones[$tone][1] ?>;"><?= icon($ic, 20) ?></span>
            <strong class="num"><?= (int) $value ?></strong>
            <span class="stat-label"><?= esc($label) ?></span>
        </a>
    <?php endforeach ?>
</div>

<div class="grid g2">
    <?= partial('partials/card_open', ['icon' => 'chart', 'title' => 'المعاملات حسب الحالة']) ?>
        <?php $max = max(array_merge([1], array_map('intval', $counts))); ?>
        <div class="bars">
            <?php foreach ($stages as $s): $n = (int) ($counts[$s['code']] ?? 0); ?>
                <div class="bar"><span><?= esc($s['name_ar']) ?></span><div class="bar-track"><i style="width: <?= round($n / $max * 100) ?>%"></i></div><b class="num"><?= $n ?></b></div>
            <?php endforeach ?>
        </div>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'target', 'title' => 'المعاملات حسب النوع']) ?>
        <?php $total = array_sum(array_column($byType, 'n')); ?>
        <?php if ($total === 0): ?>
            <div class="empty">لا توجد معاملات بعد.</div>
        <?php else: $acc = 0; $segs = []; foreach ($byType as $i => $t) { $a = $acc / $total * 360; $acc += $t['n']; $segs[] = $palette[$i % 6] . ' ' . round($a, 1) . 'deg ' . round($acc / $total * 360, 1) . 'deg'; } ?>
            <div class="donut-wrap">
                <div class="donut" style="background: conic-gradient(<?= implode(', ', $segs) ?>);"><div class="donut-hole"><strong class="num"><?= $total ?></strong><span class="muted" style="font-size: 12px;">معاملة</span></div></div>
                <div class="legend"><?php foreach ($byType as $i => $t): ?><div><i style="background: <?= $palette[$i % 6] ?>;"></i><span><?= esc($t['label']) ?></span><b class="num"><?= (int) $t['n'] ?></b></div><?php endforeach ?></div>
            </div>
        <?php endif ?>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'mail', 'title' => 'المعاملات حسب المصدر']) ?>
        <?php $max = max(array_merge([1], array_map('intval', array_column($bySource, 'n')))); ?>
        <div class="bars">
            <?php foreach ($bySource as $s): ?>
                <div class="bar"><span><?= esc($s['label']) ?></span><div class="bar-track"><i style="width: <?= round($s['n'] / $max * 100) ?>%"></i></div><b class="num"><?= (int) $s['n'] ?></b></div>
            <?php endforeach ?>
        </div>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'clock', 'title' => 'متوسط مدة الإجراءات']) ?>
        <?php $maxd = max(array_merge([1], array_map(static fn ($d) => (float) $d[1], $durations))); ?>
        <div class="cols">
            <?php foreach ($durations as [$label, $avg, $n]): ?>
                <div><span class="num" style="font-size: 13px; font-weight: 600;"><?= $avg === null ? '—' : $avg ?></span><i style="height: <?= $avg === null ? 2 : max(4, round($avg / $maxd * 140)) ?>px;"></i><small><?= esc($label) ?></small></div>
            <?php endforeach ?>
        </div>
        <span class="hint">بالأيام · من المعاملات المكتملة لكل مرحلة خلال آخر 180 يوماً</span>
    <?= partial('partials/card_close') ?>
</div>

<?= partial('partials/card_open', ['icon' => 'alert', 'title' => 'المعاملات التي تحتاج إجراء', 'right' => '<a href="' . site_url('cases') . '" style="font-weight: 600;">عرض كل المعاملات</a>']) ?>
    <?php if ($need === []): ?>
        <div class="empty">لا توجد معاملات بانتظار إجراء منك.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>رقم المعاملة</th><th>نوع المعاملة</th><th>الحالة</th><th>المسؤول</th><th>تاريخ الاستحقاق</th><th>المدة</th><th>الإجراء</th></tr></thead>
            <tbody>
            <?php foreach ($need as $n): [$al, $au] = case_next($n); ?>
                <tr>
                    <td><a class="link-strong num" href="<?= site_url('cases/' . $n['id']) ?>"><?= esc($n['case_no']) ?></a></td>
                    <td><?= esc($n['type_name']) ?></td>
                    <td><?= badge($n['stage_name'], stage_tone($n['stage_code']), true) ?></td>
                    <td><?= esc($n['holder_code'] === 'investigator' && $n['investigator_name'] ? $n['investigator_name'] : ($n['holder_name'] ?? '—')) ?></td>
                    <td class="num"><?= esc($n['due']) ?></td>
                    <td><?= alert_badge($n['alert_level'], $n['idle_days']) ?></td>
                    <td><a class="btn btn-sm" href="<?= site_url($au) ?>"><?= esc($al) ?></a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table></div>
    <?php endif ?>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>
