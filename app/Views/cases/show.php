<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\Access;
$id = $case['id'];
$accused = array_values(array_filter($parties, fn ($p) => $p['party_role'] === 'accused'));
$complainants = array_values(array_filter($parties, fn ($p) => $p['party_role'] === 'complainant'));
$tabs = [
    'data' => ['بيانات المعاملة', null, "cases/{$id}"],
    'attachments' => ['المرفقات', $counts['attachments'], "cases/{$id}?tab=attachments"],
    'sessions' => ['الجلسات', $counts['sessions'], "cases/{$id}?tab=sessions"],
    'statements' => ['الشهود والمختصون', $counts['statements'], "cases/{$id}?tab=statements"],
    'letters' => ['المراسلات', $counts['letters'], "cases/{$id}?tab=letters"],
    'log' => ['سجل الإجراءات', null, "cases/{$id}/log"],
];
?>
<div class="case-bar">
    <div class="case-id"><div class="ico" style="width: 52px; height: 52px;"><?= icon('folder', 26) ?></div><div><span class="muted" style="font-size: 13px;">رقم المعاملة</span><strong class="num" style="font-size: 22px;"><?= esc($case['case_no']) ?></strong></div></div>
    <div class="kv"><span>حالة المعاملة</span><span><?= badge($case['stage_name'], stage_tone($case['stage_code']), true) ?></span></div>
    <div class="kv"><span>درجة السرية</span><span><?= badge(label('conf', $case['confidentiality']), $case['confidentiality'] === 'normal' ? 'gray' : 'yellow') ?></span></div>
    <div class="kv"><span>تاريخ الاستلام</span><span class="num"><?= fdate($case['received_at']) ?></span></div>
    <div class="kv"><span>آخر تحديث</span><span class="num"><?= fdate($case['last_action_at'], true) ?></span></div>
    <div class="kv"><span>المدة</span><span><?= alert_badge($case['alert_level'], $case['idle_days']) ?></span></div>
    <div class="end">
        <?php if ($case['stage_code'] === 'draft' && (int) $case['created_by'] === (int) session('user_id')): ?>
            <form method="post" action="<?= site_url("cases/{$id}/submit") ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('send', 18) ?>إرسال المعاملة</button></form>
        <?php elseif ($canInvestigate): ?>
            <form method="post" action="<?= site_url("cases/{$id}/start") ?>"><?= csrf_field() ?><button class="btn btn-primary" style="height: 52px;"><?= icon('play', 18) ?><?= $started ? 'متابعة إجراءات التحقيق' : 'بدء إجراءات التحقيق' ?></button></form>
        <?php elseif (in_array($case['stage_code'], ['new', 'with_gm', 'referred'], true) && Access::hasAny(['legal', 'gm', 'head'])): ?>
            <a class="btn btn-primary" href="<?= site_url("cases/{$id}/referral") ?>"><?= icon('share', 18) ?>إحالة المعاملة</a>
        <?php elseif ($case['stage_code'] === 'approval'): ?>
            <a class="btn btn-primary" href="<?= site_url("cases/{$id}/review") ?>"><?= icon('eye', 18) ?>مراجعة المذكرة</a>
        <?php elseif ($case['stage_code'] === 'execution'): ?>
            <a class="btn btn-primary" href="<?= site_url("cases/{$id}/execution") ?>"><?= icon('list', 18) ?>تنفيذ التوصيات</a>
        <?php endif ?>
    </div>
</div>

<section class="card">
    <nav class="tabs" aria-label="أقسام المعاملة">
        <?php foreach ($tabs as $k => [$lbl, $cnt, $url]): ?>
            <a href="<?= site_url($url) ?>" class="<?= $tab === $k ? 'is-active' : '' ?>"><?= esc($lbl) ?><?php if ($cnt !== null): ?><span class="cnt num"><?= (int) $cnt ?></span><?php endif ?></a>
        <?php endforeach ?>
    </nav>
    <div class="card-body">
    <?php if ($tab === 'data'): ?>
        <div class="field"><span class="muted" style="font-size: 13px;">الموضوع</span><strong style="font-size: 17px;"><?= esc($case['subject']) ?></strong></div>
        <div class="field"><span class="muted" style="font-size: 13px;">وصف المخالفة</span><p style="line-height: 1.9; white-space: pre-wrap; max-width: 75em;"><?= esc($case['description']) ?></p></div>
        <div class="divider-line"></div>
        <div class="grid g4">
            <div class="kv"><span>نوع المعاملة</span><span><?= esc($case['type_name']) ?></span></div>
            <div class="kv"><span>مصدر المعاملة</span><span><?= esc($case['source_name']) ?><?= $case['external_ref'] ? ' · ' . esc($case['external_ref']) : '' ?></span></div>
            <div class="kv"><span>تاريخ الواقعة</span><span class="num"><?= fdate($case['incident_date']) ?></span></div>
            <div class="kv"><span>مكان الواقعة</span><span><?= esc($case['incident_place'] ?: '—') ?></span></div>
            <div class="kv"><span>الإدارة / القسم</span><span><?= esc($case['department_name'] ?? '—') ?></span></div>
            <div class="kv"><span>مقدم الشكوى</span><span><?= esc(implode('، ', array_column($complainants, 'name')) ?: 'من الإدارة') ?></span></div>
            <div class="kv"><span>رئيس التحقيقات</span><span><?= esc($case['head_name'] ?? '—') ?></span></div>
            <div class="kv"><span>المحقق المعيّن</span><span><?= esc($case['investigator_name'] ?? '—') ?></span></div>
        </div>
        <div class="field"><span class="muted" style="font-size: 13px;">الموظف / الموظفون محل التحقيق</span>
            <div class="row">
                <?php foreach ($accused as $p): ?><div style="border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px;"><?= partial('partials/person', ['name' => $p['name'], 'sub' => trim(($p['job_title'] ?? '') . ' · ' . ($p['employee_no'] ?? ''), ' ·')]) ?></div><?php endforeach ?>
            </div>
        </div>
    <?php elseif ($tab === 'attachments'): ?>
        <?php if ($attachments === []): ?><div class="empty">لا توجد مرفقات.</div><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>الملف</th><th>مرتبط بـ</th><th>الحجم</th><th>رفعه</th><th>التاريخ</th></tr></thead>
                <tbody><?php foreach ($attachments as $a): ?>
                    <tr><td><a class="row" href="<?= site_url('attachments/' . $a['id']) ?>" style="font-weight: 600;"><?= icon('clip', 16) ?><?= esc($a['original_name']) ?></a></td>
                        <td><?= esc(['case' => 'المعاملة', 'invitation' => 'دعوة', 'session' => 'جلسة', 'statement_request' => 'طلب إفادة', 'correspondence' => 'مراسلة', 'correspondence_reply' => 'رد مراسلة', 'memo' => 'المذكرة', 'recommendation' => 'إثبات تنفيذ'][$a['related_type']] ?? '') ?></td>
                        <td class="num"><?= file_size((int) $a['size_bytes']) ?></td><td><?= esc($a['uploader']) ?></td><td class="num"><?= fdate($a['created_at'], true) ?></td></tr>
                <?php endforeach ?></tbody>
            </table></div>
        <?php endif ?>
        <?php if (! $case['is_final']): ?>
            <form method="post" action="<?= site_url("cases/{$id}/attachments") ?>" enctype="multipart/form-data" class="stack" style="gap: 12px;">
                <?= csrf_field() ?>
                <?= partial('partials/dropzone', ['text' => 'اضغط لإضافة مرفقات للمعاملة']) ?>
                <div><button class="btn btn-primary"><?= icon('upload', 18) ?>رفع المرفقات</button></div>
            </form>
        <?php endif ?>
    <?php elseif ($tab === 'sessions'): ?>
        <?php if ($sessions === []): ?><div class="empty">لا توجد جلسات بعد.</div><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>الجلسة</th><th>الطرف</th><th>طريقة الحضور</th><th>البدء</th><th>الانتهاء</th><th>قيود المحضر</th><th>الحالة</th><th></th></tr></thead>
                <tbody><?php foreach ($sessions as $s): ?>
                    <tr><td class="num">جلسة <?= (int) $s['session_no'] ?></td><td><?= esc($s['party_name']) ?> <span class="muted">(<?= label('party', $s['party_role']) ?>)</span></td><td><?= label('mode', $s['mode']) ?></td>
                        <td class="num"><?= fdate($s['started_at'], true) ?></td><td class="num"><?= fdate($s['closed_at'], true) ?></td><td class="num"><?= (int) $s['entries'] ?></td>
                        <td><?= status_badge('session', $s['status']) ?></td><td><a class="btn btn-sm" href="<?= site_url('sessions/' . $s['id']) ?>">فتح</a></td></tr>
                <?php endforeach ?></tbody>
            </table></div>
        <?php endif ?>
        <div><a class="btn" href="<?= site_url("cases/{$id}/attendance") ?>"><?= icon('users', 18) ?>متابعة الحضور</a></div>
    <?php elseif ($tab === 'statements'): ?>
        <?php if ($statements === []): ?><div class="empty">لا توجد طلبات إفادة أو رأي مختص.</div><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>النوع</th><th>الشاهد / الجهة</th><th>الموضوع</th><th>التاريخ</th><th>الحالة</th></tr></thead>
                <tbody><?php foreach ($statements as $s): ?>
                    <tr><td><?= $s['request_type'] === 'witness' ? 'إفادة شاهد' : 'رأي مختص' ?></td><td><?= esc($s['party_name'] ?: ($s['department_name'] . ($s['specialty'] ? ' · ' . $s['specialty'] : ''))) ?></td>
                        <td><?= esc($s['subject']) ?></td><td class="num"><?= fdate($s['created_at']) ?></td><td><?= status_badge('req_status', $s['status']) ?></td></tr>
                <?php endforeach ?></tbody>
            </table></div>
        <?php endif ?>
        <div><a class="btn" href="<?= site_url("cases/{$id}/statements") ?>"><?= icon('users', 18) ?>إدارة الشهود والمختصين</a></div>
    <?php elseif ($tab === 'letters'): ?>
        <?php if ($letters === []): ?><div class="empty">لا توجد مراسلات.</div><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>الجهة</th><th>نوع الطلب</th><th>الموضوع</th><th>تاريخ الإرسال</th><th>الاستحقاق</th><th>حالة الرد</th></tr></thead>
                <tbody><?php foreach ($letters as $l): ?>
                    <tr><td><?= esc($l['department_name']) ?></td><td><?= esc($l['request_type']) ?></td><td><?= esc($l['subject']) ?></td><td class="num"><?= fdate($l['sent_at']) ?></td><td class="num"><?= fdate($l['due_date']) ?></td>
                        <td><?= $l['status'] === 'replied' ? badge('تم الرد', 'green', true) : ($l['due_date'] < date('Y-m-d') ? badge('متأخر', 'red', true) : badge('بانتظار الرد', 'yellow', true)) ?></td></tr>
                <?php endforeach ?></tbody>
            </table></div>
        <?php endif ?>
        <div><a class="btn" href="<?= site_url("cases/{$id}/letters") ?>"><?= icon('mail', 18) ?>إدارة المراسلات</a></div>
    <?php endif ?>
    </div>
</section>

<?php if ($canInvestigate): ?>
<?= partial('partials/card_open', ['icon' => 'target', 'title' => 'إجراءات التحقيق']) ?>
    <div class="grid g4">
        <?php foreach ([
            ['mail', 'إرسال دعوة', 'دعوة حضور لجلسة تحقيق', "cases/{$id}/invitations/new"],
            ['users', 'طلب إفادة شاهد', 'استدعاء شاهد للإدلاء بإفادته', "cases/{$id}/statements#witness"],
            ['target', 'طلب رأي مختص', 'رأي فني من جهة مختصة', "cases/{$id}/statements#expert"],
            ['list', 'محاور التحقيق', 'الأسئلة والإجابات', "cases/{$id}/topics"],
            ['video', 'متابعة الحضور والجلسات', 'حالة الدعوات وبدء الجلسة', "cases/{$id}/attendance"],
            ['msg', 'المراسلات', 'طلب مستندات من الإدارات', "cases/{$id}/letters"],
            ['file', 'مذكرة التحقيق', 'إعداد المذكرة وإرسالها للمراجعة', "cases/{$id}/memo"],
        ] as [$ic, $t, $s, $u]): ?>
            <a href="<?= site_url($u) ?>" class="row" style="gap: 14px; padding: 16px; border: 1px solid var(--line); border-radius: 14px; flex-wrap: nowrap; text-decoration: none; color: var(--ink);">
                <span class="kpi-ico" style="width: 44px; height: 44px; border-radius: 12px; background: var(--primary-soft); color: var(--primary);"><?= icon($ic, 22) ?></span>
                <span><b style="display: block; font-size: 15px;"><?= esc($t) ?></b><span class="hint"><?= esc($s) ?></span></span>
            </a>
        <?php endforeach ?>
    </div>
<?= partial('partials/card_close') ?>
<?php endif ?>
<?= $this->endSection() ?>
