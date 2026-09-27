<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$labels = ['weekly' => 'التقرير الأسبوعي', 'monthly' => 'التقرير الشهري', 'quarterly' => 'التقرير الربعي'];
$bars = static function (array $rows): string {
    if ($rows === []) { return '<div class="empty">لا توجد بيانات في هذه الفترة.</div>'; }
    $max = max(array_merge([1], array_map('intval', array_column($rows, 'n'))));
    $h = '<div class="bars">';
    foreach ($rows as $r) { $h .= '<div class="bar"><span>' . esc($r['label']) . '</span><div class="bar-track"><i style="width: ' . round($r['n'] / $max * 100) . '%"></i></div><b class="num">' . (int) $r['n'] . '</b></div>'; }
    return $h . '</div>';
};
$recTotal = (int) ($recs['total'] ?? 0);
?>
<div class="row between no-print">
    <div class="chips">
        <?php foreach ($labels as $k => $l): ?><a class="chip <?= $period === $k ? 'is-active' : '' ?>" href="<?= site_url('reports?period=' . $k) ?>"><?= $l ?></a><?php endforeach ?>
    </div>
    <button class="btn" type="button" onclick="window.print()"><?= icon('file', 18) ?>طباعة / حفظ PDF</button>
</div>

<div class="card card-plain">
    <div class="row between"><h2 style="font-size: 20px;"><?= $labels[$period] ?? '' ?></h2><span class="muted num">الفترة: <?= fdate($from) ?> — <?= fdate($to) ?></span></div>
    <div class="grid" style="grid-template-columns: repeat(5, minmax(0, 1fr));">
        <?php foreach ($kpis as [$l, $v, $ic]): ?>
            <div style="border: 1px solid var(--line); border-radius: 14px;" class="kpi"><div class="kpi-top"><span><?= $l ?></span><span class="kpi-ico" style="background: var(--primary-soft); color: var(--primary);"><?= icon($ic, 18) ?></span></div><strong class="num"><?= esc((string) $v) ?></strong></div>
        <?php endforeach ?>
    </div>
</div>

<div class="grid g2">
    <?= partial('partials/card_open', ['icon' => 'target', 'title' => 'الوارد حسب النوع']) ?><?= $bars($byType) ?><?= partial('partials/card_close') ?>
    <?= partial('partials/card_open', ['icon' => 'folder', 'title' => 'الوارد حسب الإدارة']) ?><?= $bars($byDept) ?><?= partial('partials/card_close') ?>
    <?= partial('partials/card_open', ['icon' => 'chart', 'title' => 'توزيع المعاملات الحالي حسب المرحلة']) ?><?= $bars($byStage) ?><?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'list', 'title' => 'متابعة تنفيذ التوصيات']) ?>
        <?php if ($recTotal === 0): ?><div class="empty">لا توجد توصيات مسجلة.</div><?php else: ?>
            <div class="grid g4">
                <?php foreach ([['الإجمالي', $recTotal, 'var(--ink)'], ['تم التنفيذ', $recs['done'], 'var(--g)'], ['قيد التنفيذ', $recs['progress'], 'var(--y)'], ['متأخرة', $recs['late'], 'var(--r)']] as [$l, $n, $c]): ?>
                    <div class="kv"><span><?= $l ?></span><strong class="num" style="font-size: 26px; color: <?= $c ?>;"><?= (int) $n ?></strong></div>
                <?php endforeach ?>
            </div>
            <div class="bar-track" style="height: 14px;"><i style="width: <?= round($recs['done'] / $recTotal * 100) ?>%; background: var(--g);"></i></div>
            <span class="hint">نسبة الإنجاز <?= round($recs['done'] / $recTotal * 100) ?>%</span>
        <?php endif ?>
    <?= partial('partials/card_close') ?>
</div>

<?= partial('partials/card_open', ['icon' => 'msg', 'title' => 'الاستشارات القانونية', 'right' => '<span class="muted num">الشهر الحالي: ' . date('Y-m') . '</span>']) ?>
    <?php if ($consultations === null): ?><div class="empty">تعذّر تحميل إحصاءات الاستشارات.</div><?php else: ?>
        <div class="grid g4">
            <?php foreach ([['استشارات هذا الشهر', $consultations['total']], ['المُجاب عليها', $consultations['answered']], ['متوسط زمن الرد (يوم)', $consultations['avg_days'] ?? '—']] as [$l, $n]): ?>
                <div class="kv"><span><?= $l ?></span><strong class="num" style="font-size: 26px;"><?= esc((string) $n) ?></strong></div>
            <?php endforeach ?>
            <div class="kv"><span>أكثر التصنيفات تكراراً</span><strong><?= esc($consultations['top'][0]['label'] ?? '—') ?></strong></div>
        </div>
        <?= $bars($consultations['top']) ?>
    <?php endif ?>
<?= partial('partials/card_close') ?>

<?= partial('partials/card_open', ['icon' => 'users', 'title' => 'توزيع العمل على المحققين']) ?>
    <?php if ($workload === []): ?><div class="empty">لا توجد معاملات مسندة.</div><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>المحقق</th><th>قيد التحقيق والاعتماد</th><th>أُنجزت في الفترة</th><th>إجمالي المسند</th></tr></thead>
        <tbody><?php foreach ($workload as $w): ?><tr><td><?= partial('partials/person', ['name' => $w['name']]) ?></td><td class="num"><?= (int) $w['open_n'] ?></td><td class="num"><?= (int) $w['closed_n'] ?></td><td class="num"><?= (int) $w['total'] ?></td></tr><?php endforeach ?></tbody>
    </table></div>
    <?php endif ?>
<?= partial('partials/card_close') ?>

<?= partial('partials/card_open', ['icon' => 'send', 'title' => 'مستلمو التقرير']) ?>
    <div class="row" style="gap: 12px;">
        <?php foreach ($recipients as [$n, $r]): ?><div style="border: 1px solid var(--line); border-radius: 12px; padding: 10px 14px;"><?= partial('partials/person', ['name' => $n, 'sub' => $r]) ?></div><?php endforeach ?>
    </div>
    <span class="hint">الإرسال الآلي الدوري بالبريد يُفعّل بعد ربط خادم البريد. حالياً اطبع التقرير أو احفظه PDF.</span>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>
