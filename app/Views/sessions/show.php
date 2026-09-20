<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$live = $s['status'] === 'in_progress';
$closed = $s['status'] === 'closed';
$investigator = array_values(array_filter($participants, fn ($p) => $p['role_label'] === 'investigator'))[0] ?? null;
$others = array_values(array_filter($participants, fn ($p) => $p['role_label'] !== 'investigator'));
?>
<div class="card grid g6" style="padding: 18px 24px; align-items: center;">
    <div class="kv"><span>رقم المعاملة</span><strong class="num"><?= esc($case['case_no']) ?></strong></div>
    <div class="kv"><span>اسم الموظف</span><strong><?= esc($s['party_name']) ?></strong></div>
    <div class="kv"><span>اسم المحقق</span><strong><?= esc($s['investigator_name']) ?></strong></div>
    <div class="kv"><span>تاريخ الجلسة</span><strong class="num"><?= fdate($s['session_at'] ?? $s['created_at']) ?></strong></div>
    <div class="kv"><span>وقت الجلسة</span><strong class="num"><?= $s['session_at'] ? date('H:i', strtotime($s['session_at'])) : '—' ?></strong></div>
    <div class="kv"><span>حالة الجلسة</span><span><?= status_badge('session', $s['status']) ?> <span class="hint">جلسة <?= (int) $s['session_no'] ?></span></span></div>
</div>

<div class="split">
    <div class="main">
        <div class="stage-video">
            <div class="video-top">
                <span class="rec"><?php if ($live): ?><i></i>الجلسة جارية<?php else: ?><?= esc(label('session', $s['status'])) ?><?php endif ?></span>
                <span class="row" style="gap: 6px;"><?= icon('lock', 14) ?>اتصال آمن</span>
                <span class="num"><?= $s['started_at'] ? 'بدأت ' . date('H:i', strtotime($s['started_at'])) : '—' ?></span>
            </div>
            <div class="tile big">
                <div class="face"><?= esc(initial($s['party_name'])) ?></div>
                <span class="cap"><?= esc($s['party_name']) ?> · <?= label('party', $s['party_role']) ?></span>
                <span class="notice"><?php if ($s['mode'] === 'remote'): ?>الاتصال المرئي يتم عبر رابط الجلسة المعتمد، ويُفعّل داخل المنصة بعد ربط منصة الاجتماعات.<?php else: ?>جلسة حضورية — يُسجَّل المحضر كتابياً.<?php endif ?></span>
            </div>
            <div class="grid g2" style="gap: 12px;">
                <?php foreach (array_slice($participants, 0, 2) as $p): if ($p['role_label'] === $s['party_role'] && $p['party_id'] == $s['party_id']) continue; ?>
                    <div class="tile"><div class="face"><?= esc(initial($p['name'])) ?></div><span class="cap"><?= esc($p['name']) ?> · <?= $p['role_label'] === 'investigator' ? 'المحقق' : label('party', $p['role_label']) ?></span></div>
                <?php endforeach ?>
                <?php if (count($participants) < 3): ?><div class="tile"><span class="muted" style="color: #A9BFBE;">لا يوجد مشارك إضافي</span></div><?php endif ?>
            </div>
            <div class="controls">
                <?php if ($s['meeting_link']): ?><a class="icon-btn" href="<?= esc($s['meeting_link'], 'attr') ?>" target="_blank" rel="noopener" title="فتح رابط الجلسة"><?= icon('video', 22) ?></a><?php endif ?>
                <button class="icon-btn" type="button" title="كتم / تشغيل الصوت — يتحكم به تطبيق الاجتماعات" disabled><?= icon('mic', 22) ?></button>
                <button class="icon-btn" type="button" title="تشغيل / إيقاف الكاميرا — يتحكم به تطبيق الاجتماعات" disabled><?= icon('screen', 22) ?></button>
                <?php if ($isInvestigator && ! $closed): ?><button class="icon-btn end" type="button" data-open="endSession" title="إنهاء الجلسة"><?= icon('phone', 22) ?></button><?php endif ?>
            </div>
        </div>

        <?= partial('partials/card_open', ['id' => 'minutes', 'icon' => 'file', 'title' => 'محضر الجلسة', 'right' => badge($closed ? 'محفوظ ومقفل' : 'يُحفظ كنص مكتوب', $closed ? 'gray' : 'teal')]) ?>
            <?php if ($minutes === []): ?><div class="empty">لم يُسجَّل شيء في المحضر بعد.</div><?php endif ?>
            <?php foreach ($minutes as $i => $q): ?>
                <div class="minutes-item"><small>س <?= $i + 1 ?> · <?= esc($q['topic_title']) ?> · <?= $q['asked_at'] ? date('H:i', strtotime($q['asked_at'])) : '' ?></small><div style="line-height: 1.8;"><?= esc($q['question']) ?></div></div>
                <?php if ($q['answer']): ?>
                    <div class="minutes-item a"><small>ج <?= $i + 1 ?> · <?= esc($s['party_name']) ?></small><div style="line-height: 1.8; white-space: pre-wrap;"><?= esc($q['answer']) ?></div></div>
                <?php elseif ($isInvestigator && $live): ?>
                    <form method="post" action="<?= site_url("sessions/{$s['id']}/minutes") ?>" class="row" style="flex-wrap: nowrap;">
                        <?= csrf_field() ?><input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                        <input class="input" name="answer" placeholder="إجابة السؤال <?= $i + 1 ?>…" required><button class="btn">حفظ الإجابة</button>
                    </form>
                <?php endif ?>
            <?php endforeach ?>

            <?php if ($isInvestigator && $live): ?>
                <form method="post" action="<?= site_url("sessions/{$s['id']}/minutes") ?>" class="stack" style="gap: 12px; padding-top: 8px; border-top: 1px solid var(--line);">
                    <?= csrf_field() ?>
                    <div class="grid g3">
                        <label class="field"><span class="lbl">المحور</span>
                            <select class="select" name="topic_id"><option value="">أسئلة عامة</option><?php foreach ($topics as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['title']) ?></option><?php endforeach ?></select></label>
                        <label class="field span-2"><span class="lbl">السؤال</span><input class="input" name="question" required placeholder="اكتب السؤال…"></label>
                    </div>
                    <label class="field"><span class="lbl">الإجابة (اختياري الآن)</span><textarea class="textarea" name="answer" rows="2" style="min-height: 70px;" placeholder="إجابة الموظف كما وردت…"></textarea></label>
                    <div class="row between"><button class="btn btn-primary"><?= icon('plus', 18) ?>إضافة للمحضر</button><span class="row hint" style="color: var(--g);"><?= icon('checkc', 16) ?>كل قيد يُحفظ فوراً</span></div>
                </form>
            <?php elseif ($isInvestigator && ! $closed): ?>
                <div class="alert alert-info"><?= icon('alert', 18) ?><span>ابدأ التحقيق من الشريط السفلي لتفعيل تسجيل المحضر.</span></div>
            <?php endif ?>
        <?= partial('partials/card_close') ?>
    </div>

    <div class="side">
        <?= partial('partials/card_open', ['icon' => 'users', 'title' => 'المشاركون', 'bodyClass' => 'tight']) ?>
            <?php foreach ($participants as $p): ?>
                <div class="row between"><?= partial('partials/person', ['name' => $p['name'] ?? '—', 'sub' => $p['role_label'] === 'investigator' ? 'المحقق' : label('party', $p['role_label']), 'role' => $p['role_label']]) ?><?= $live ? badge('في الجلسة', 'green', true) : badge(label('session', $s['status']), 'gray') ?></div>
            <?php endforeach ?>
            <?php if ($isInvestigator && ! $closed): ?><a class="btn btn-sm" href="<?= site_url("cases/{$case['id']}/statements#witness") ?>"><?= icon('plus', 16) ?>طلب شاهد أو مختص</a><?php endif ?>
        <?= partial('partials/card_close') ?>

        <?= partial('partials/card_open', ['id' => 'chat', 'icon' => 'msg', 'title' => 'المحادثة الكتابية', 'bodyClass' => 'tight']) ?>
            <div class="chat">
                <?php if ($messages === []): ?><span class="hint">لا توجد رسائل.</span><?php endif ?>
                <?php foreach ($messages as $m): ?>
                    <div class="bubble <?= (int) $m['user_id'] === (int) session('user_id') ? 'me' : '' ?>"><small><?= esc($m['name']) ?> · <?= date('H:i', strtotime($m['created_at'])) ?></small><?= esc($m['body']) ?></div>
                <?php endforeach ?>
            </div>
            <?php if (! $closed): ?>
                <form method="post" action="<?= site_url("sessions/{$s['id']}/messages") ?>" class="row" style="flex-wrap: nowrap; gap: 8px;">
                    <?= csrf_field() ?><input class="input" style="height: 42px;" name="body" placeholder="اكتب رسالة…" required maxlength="2000">
                    <button class="btn btn-primary" style="width: 42px; height: 42px; padding: 0;" aria-label="إرسال"><?= icon('send', 18) ?></button>
                </form>
            <?php endif ?>
        <?= partial('partials/card_close') ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('actions') ?>
<?php if ($isInvestigator && ! $closed): ?>
<div class="action-bar">
    <form method="post" action="<?= site_url("sessions/{$s['id']}/status") ?>" class="row">
        <?= csrf_field() ?>
        <?php if ($s['status'] === 'scheduled'): ?><button class="btn btn-primary" name="to" value="start"><?= icon('play', 18) ?>بدء التحقيق</button><?php endif ?>
        <?php if ($s['status'] === 'in_progress'): ?><button class="btn" name="to" value="pause"><?= icon('pause', 18) ?>إيقاف مؤقت</button><?php endif ?>
        <?php if ($s['status'] === 'paused'): ?><button class="btn btn-primary" name="to" value="resume"><?= icon('play', 18) ?>استئناف</button><?php endif ?>
    </form>
    <button class="btn btn-danger" data-open="endSession"><?= icon('phone', 18) ?>إنهاء الجلسة</button>
    <span class="note"><?= icon('lock', 15) ?>الجلسة سرية ويُحفظ محضرها وفق الأنظمة</span>
</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php if ($isInvestigator && ! $closed): ?>
<dialog class="modal" id="endSession">
    <form method="post" action="<?= site_url("sessions/{$s['id']}/status") ?>">
        <?= csrf_field() ?><input type="hidden" name="to" value="end">
        <div class="modal-ico danger"><?= icon('phone', 28) ?></div>
        <div><h2>إنهاء جلسة التحقيق</h2><p>هل أنت متأكد من إنهاء جلسة التحقيق؟ سيُقفل المحضر ويُحفظ ضمن ملف المعاملة ولا يمكن تعديله بعد ذلك.</p></div>
        <div class="summary"><div><span class="muted">محضر الجلسة <?= (int) $s['session_no'] ?></span><b class="num"><?= count($minutes) ?> قيود</b></div></div>
        <div class="row"><button class="btn btn-danger-solid"><?= icon('check', 18) ?>إنهاء الجلسة وحفظ المحضر</button><button class="btn" data-close>متابعة الجلسة</button></div>
    </form>
</dialog>
<?php endif ?>
<?= $this->endSection() ?>
