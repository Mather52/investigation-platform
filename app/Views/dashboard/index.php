<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$tones   = ['teal' => ['var(--t-bg)', 'var(--t)'], 'blue' => ['var(--b-bg)', 'var(--b)'], 'gray' => ['var(--n-bg)', 'var(--n)'], 'yellow' => ['var(--y-bg)', 'var(--y)'], 'red' => ['var(--r-bg)', 'var(--r)']];
$palette = ['#0E74B8', '#2CA8E0', '#C08A2B', '#9DD3EE', '#D8C8A6', '#8A9294'];
$isStaff = in_array($role, ['head', 'legal', 'gm', 'admin'], true);

// الأزرار السريعة حسب الدور: [العنوان، الرابط، الأيقونة]
$actions = match ($role) {
    'employee'     => [['إنشاء مخالفة / شكوى', 'cases/new', 'plus'], ['طلب استشارة', 'consultations/new', 'msg']],
    'investigator' => [['المعاملات قيد التحقيق', 'cases?view=investigation', 'search2'], ['الجلسات', 'cases?view=sessions', 'video'], ['التقارير', 'reports', 'chart']],
    'head'         => [['المعاملات المحالة', 'cases?view=referred', 'share'], ['الاعتمادات', 'cases?view=approvals', 'checkc'], ['التقارير', 'reports', 'chart']],
    'legal'        => [['إنشاء مخالفة / شكوى', 'cases/new', 'plus'], ['المعاملات المحالة', 'cases?view=referred', 'share'], ['الاستشارات', 'consultations', 'msg']],
    'gm'           => [['المعاملات المحالة', 'cases?view=referred', 'share'], ['الاعتمادات', 'cases?view=approvals', 'checkc'], ['التقارير', 'reports', 'chart']],
    default        => [['إنشاء مخالفة / شكوى', 'cases/new', 'plus'], ['المستخدمون والإعدادات', 'admin/users', 'users'], ['التقارير', 'reports', 'chart']],
};
if ($role === 'employee') {
    $upcoming = (int) $kpis[2][1];
    $message  = $upcoming > 0 ? 'لديك <b class="num">' . $upcoming . '</b> ' . ($upcoming === 1 ? 'دعوة حضور قادمة.' : 'دعوات حضور قادمة.') : 'يمكنك تقديم مخالفة أو شكوى، أو طلب استشارة قانونية.';
} else {
    $message = $need === [] ? 'لا توجد معاملات بانتظار إجراء منك الآن.' : 'لديك <b class="num">' . count($need) . '</b> ' . (count($need) === 1 ? 'معاملة تحتاج' : 'معاملات تحتاج') . ' إلى إجراء.';
}
?>
<section class="hero">
    <img class="hero-art" src="<?= base_url('assets/img/emblem.png') ?>" alt="" aria-hidden="true">
    <div class="hero-text">
        <span class="hero-date num"><?= icon('cal', 16) ?><?= esc($today) ?></span>
        <h1><?= esc($greeting) ?><?= $firstName !== '' ? '، ' . esc($firstName) : '' ?></h1>
        <span class="hero-role"><?= icon('shield', 14) ?><?= esc($roleLabel) ?></span>
        <p><?= $message ?></p>
    </div>
    <div class="hero-actions">
        <?php foreach ($actions as $i => [$label, $url, $ic]): if ($url === 'reports' && ! $canReports) continue; ?>
            <a class="btn <?= $i === 0 ? 'btn-light' : 'btn-glass' ?>" href="<?= site_url($url) ?>"><?= icon($ic, 18) ?><?= esc($label) ?></a>
        <?php endforeach ?>
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

<?php if ($role === 'employee'): ?>
    <div class="grid g2" style="align-items: start;">
        <?= partial('partials/card_open', ['icon' => 'folder', 'title' => 'معاملاتي', 'right' => '<a href="' . site_url('cases') . '" style="font-weight: 600;">عرض الكل</a>']) ?>
            <?php if ($myCases === []): ?>
                <div class="empty">لم تقدّم أي مخالفة أو شكوى بعد.</div>
            <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>رقم المعاملة</th><th>الموضوع</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                    <tbody><?php foreach ($myCases as $r): ?>
                        <tr><td><a class="link-strong num" href="<?= site_url('cases/' . $r['id']) ?>"><?= esc($r['case_no']) ?></a></td>
                            <td><?= esc(mb_strimwidth($r['subject'], 0, 60, '…')) ?></td>
                            <td><?= badge($r['stage_name'], stage_tone($r['stage_code']), true) ?></td>
                            <td class="num"><?= fdate($r['created_at']) ?></td></tr>
                    <?php endforeach ?></tbody>
                </table></div>
            <?php endif ?>
        <?= partial('partials/card_close') ?>

        <?= partial('partials/card_open', ['icon' => 'cal', 'title' => 'دعوات الحضور']) ?>
            <?php if ($invites === []): ?>
                <div class="empty">لا توجد دعوات حضور موجهة إليك.</div>
            <?php else: ?>
                <div class="list-rows">
                    <?php foreach ($invites as $i): $soon = strtotime($i['session_at']) >= time() && $i['attendance_status'] === 'pending'; ?>
                        <a class="list-row" href="<?= site_url('invitations/' . $i['id']) ?>">
                            <span class="stat-ico" style="background: <?= $soon ? 'var(--y-bg)' : 'var(--n-bg)' ?>; color: <?= $soon ? 'var(--y)' : 'var(--n)' ?>;"><?= icon($i['attendance_mode'] === 'remote' ? 'video' : 'user', 18) ?></span>
                            <div><b class="num"><?= fdate($i['session_at'], true) ?></b><small><?= esc(label('mode', $i['attendance_mode'])) ?> · المعاملة <span class="num"><?= esc($i['case_no']) ?></span></small></div>
                            <?= $soon ? badge('قادمة', 'yellow') : status_badge('attendance', $i['attendance_status']) ?>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        <?= partial('partials/card_close') ?>
    </div>

    <?= partial('partials/card_open', ['icon' => 'msg', 'title' => 'استشاراتي القانونية', 'right' => '<a class="btn btn-primary btn-sm" href="' . site_url('consultations/new') . '">' . icon('plus', 16) . 'طلب استشارة</a>']) ?>
        <?php if ($consultations === []): ?>
            <div class="empty">لم تقدّم أي استشارة بعد.</div>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>الرقم المرجعي</th><th>الموضوع</th><th>الحالة</th><th>تاريخ التقديم</th></tr></thead>
                <tbody><?php foreach ($consultations as $r): ?>
                    <tr><td><a class="link-strong num" href="<?= site_url('consultations/' . $r['id']) ?>"><?= esc($r['ref_no']) ?></a></td>
                        <td><?= esc(mb_strimwidth($r['subject'], 0, 70, '…')) ?></td>
                        <td><?= status_badge('cons_status', $r['status']) ?></td>
                        <td class="num"><?= fdate($r['created_at']) ?></td></tr>
                <?php endforeach ?></tbody>
            </table></div>
        <?php endif ?>
    <?= partial('partials/card_close') ?>
<?php else: ?>

    <?php if ($role === 'investigator'): ?>
        <?= partial('partials/card_open', ['icon' => 'video', 'title' => 'الجلسات القادمة', 'right' => '<a href="' . site_url('cases?view=sessions') . '" style="font-weight: 600;">كل الجلسات</a>']) ?>
            <?php if ($sessions === []): ?>
                <div class="empty">لا توجد جلسات قادمة في المعاملات المسندة إليك.</div>
            <?php else: ?>
                <div class="list-rows">
                    <?php foreach ($sessions as $s): ?>
                        <a class="list-row" href="<?= site_url('cases/' . $s['case_id'] . '/attendance') ?>">
                            <span class="stat-ico" style="background: var(--t-bg); color: var(--t);"><?= icon($s['attendance_mode'] === 'remote' ? 'video' : 'user', 18) ?></span>
                            <div><b><?= esc($s['party_name']) ?></b><small><?= esc(label('party', $s['invitation_type'])) ?> · المعاملة <span class="num"><?= esc($s['case_no']) ?></span> · <?= esc(label('mode', $s['attendance_mode'])) ?></small></div>
                            <span class="num" style="font-weight: 600;"><?= fdate($s['session_at'], true) ?></span>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        <?= partial('partials/card_close') ?>
    <?php endif ?>

    <?= partial('partials/card_open', ['icon' => 'alert', 'title' => 'المعاملات التي تحتاج إجراء منك', 'right' => '<a href="' . site_url('cases') . '" style="font-weight: 600;">عرض كل المعاملات</a>']) ?>
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

    <?php if ($isStaff): ?>
        <?php if ($workload !== []): ?>
            <?= partial('partials/card_open', ['icon' => 'users', 'title' => 'توزيع العمل على المحققين']) ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>المحقق</th><th>قيد التحقيق</th><th>في دورة الاعتماد</th><th>إجمالي المسند</th></tr></thead>
                    <tbody><?php foreach ($workload as $w): ?><tr><td><?= partial('partials/person', ['name' => $w['name']]) ?></td><td class="num"><?= (int) $w['open_n'] ?></td><td class="num"><?= (int) $w['approval_n'] ?></td><td class="num"><?= (int) $w['total'] ?></td></tr><?php endforeach ?></tbody>
                </table></div>
            <?= partial('partials/card_close') ?>
        <?php endif ?>

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
    <?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
