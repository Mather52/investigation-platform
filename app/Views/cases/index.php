<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $open = partial('partials/card_open', [
    'icon' => 'folder', 'title' => $title,
    'right' => \App\Controllers\Cases::canCreate() ? '<a class="btn btn-primary btn-sm" href="' . site_url('cases/new') . '">' . icon('plus', 16) . 'معاملة جديدة</a>' : '',
]); ?>
<?= $open ?>
    <form class="row" method="get" action="<?= site_url('cases') ?>">
        <input type="hidden" name="view" value="<?= esc($viewKey) ?>">
        <input class="input" style="max-width: 320px;" type="search" name="q" value="<?= esc($q) ?>" placeholder="رقم المعاملة، الموضوع، اسم الموظف">
        <?php if ($viewKey === 'all'): ?>
            <select class="select" name="stage" style="max-width: 240px;">
                <option value="">كل المراحل</option>
                <?php foreach ($stages as $s): ?><option value="<?= esc($s['code']) ?>" <?= $stage === $s['code'] ? 'selected' : '' ?>><?= esc($s['name_ar']) ?></option><?php endforeach ?>
            </select>
        <?php endif ?>
        <button class="btn" type="submit"><?= icon('search2', 18) ?>بحث</button>
        <span class="muted num" style="margin-inline-start: auto;"><?= count($rows) ?> معاملة</span>
    </form>

    <?php if ($rows === []): ?>
        <div class="empty">لا توجد معاملات هنا حالياً.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>رقم المعاملة</th><th>الموضوع</th><th>النوع</th><th>المصدر</th><th>الحالة</th><th>المسؤول</th><th>آخر إجراء</th><th>المدة</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $url = $target ? "cases/{$r['id']}/{$target}" : "cases/{$r['id']}"; ?>
                <tr>
                    <td><a class="link-strong num" href="<?= site_url("cases/{$r['id']}") ?>"><?= esc($r['case_no']) ?></a><?php if ($r['confidentiality'] !== 'normal'): ?> <span title="<?= esc(label('conf', $r['confidentiality'])) ?>" style="color: var(--y);"><?= icon('lock', 14) ?></span><?php endif ?></td>
                    <td><?= esc($r['subject']) ?></td>
                    <td><?= esc($r['type_name']) ?></td>
                    <td><?= esc($r['source_name']) ?></td>
                    <td><?= badge($r['stage_name'], stage_tone($r['stage_code']), true) ?></td>
                    <td><?= esc($r['holder_code'] === 'investigator' && $r['investigator_name'] ? $r['investigator_name'] : ($r['holder_name'] ?? '—')) ?></td>
                    <td class="num"><?= fdate($r['last_action_at']) ?></td>
                    <td><?= alert_badge($r['alert_level'], $r['idle_days']) ?></td>
                    <td><a class="btn btn-sm" href="<?= site_url($url) ?>">فتح</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table></div>
    <?php endif ?>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>
