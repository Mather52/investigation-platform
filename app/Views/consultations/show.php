<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\ConsultationService;
$id       = $c['id'];
$isOpen   = in_array($c['status'], ConsultationService::OPEN_STATUSES, true);
$hasAns   = $c['answer'] !== null && $c['answer'] !== '';
$conf     = ConsultationService::isConfidential($c['confidentiality']);
$note     = 'الرد المعتمد يمثل رأي إدارة الشؤون القانونية والالتزام، ويصدر باسم المستشار المعتمِد.';
?>
<div class="case-bar">
    <div class="case-id"><div class="ico" style="width: 52px; height: 52px;"><?= icon('msg', 26) ?></div><div><span class="muted" style="font-size: 13px;">الرقم المرجعي</span><strong class="num" style="font-size: 22px;"><?= esc($c['ref_no']) ?></strong></div></div>
    <div class="kv"><span>الحالة</span><span><?= status_badge('cons_status', $c['status']) ?></span></div>
    <div class="kv"><span>التصنيف</span><span><?= esc($c['category_name'] ?? '—') ?></span></div>
    <div class="kv"><span>تاريخ التقديم</span><span class="num"><?= fdate($c['created_at'], true) ?></span></div>
    <?php if ($isStaff): ?><div class="kv"><span>المُسند إليه</span><span><?= esc($c['assigned_name'] ?? '—') ?></span></div><?php endif ?>
    <div class="end">
        <?php if ($conf): ?><span class="secure"><?= icon('lock', 14) ?><?= esc(label('conf', $c['confidentiality'])) ?> — للاطلاع المصرح به فقط</span><?php else: ?><?= badge(label('conf', $c['confidentiality']), 'gray') ?><?php endif ?>
        <?php if ((int) $c['is_published'] === 1 && $isStaff): ?><?= badge('منشورة في المكتبة', 'teal') ?><?php endif ?>
    </div>
</div>

<div class="split">
    <div class="main">
        <?= partial('partials/card_open', ['icon' => 'file', 'title' => 'السؤال']) ?>
            <div class="field"><span class="muted" style="font-size: 13px;">الموضوع</span><strong style="font-size: 17px;"><?= esc($c['subject']) ?></strong></div>
            <div class="field"><span class="muted" style="font-size: 13px;">نص السؤال</span><p class="q-text"><?= esc($c['body']) ?></p></div>
        <?= partial('partials/card_close') ?>

        <?= partial('partials/card_open', ['id' => 'reply', 'icon' => 'checkc', 'title' => 'الرد المعتمد']) ?>
            <?php if ($hasAns): ?>
                <div class="answer-box">
                    <div class="q-text"><?= esc($c['answer']) ?></div>
                    <div class="divider-line"></div>
                    <?= partial('partials/person', ['name' => $c['answered_by_name'] ?? '—', 'sub' => 'المستشار المعتمِد · ' . fdate($c['answered_at'], true)]) ?>
                </div>
                <div class="fixed-note"><?= icon('shield', 16) ?><span><?= esc($note) ?></span></div>
            <?php elseif ($c['status'] === 'closed'): ?>
                <div class="empty">أُغلقت الاستشارة دون رد.</div>
            <?php else: ?>
                <div class="empty">بانتظار مراجعة المستشار واعتماد الرد. سيصلك إشعار عند اعتماده.</div>
            <?php endif ?>
        <?= partial('partials/card_close') ?>

        <?php if ($isStaff): ?>
            <?= partial('partials/card_open', ['icon' => 'eye', 'title' => 'مراجعة الشؤون القانونية', 'right' => '<span class="readonly-tag">' . icon('lock', 14) . 'لا يظهر لمقدم الاستشارة</span>']) ?>
                <?php if ($c['ai_draft'] !== null && $c['ai_draft'] !== ''): ?>
                    <div class="draft-box">
                        <div class="row between">
                            <h3><?= icon('alert', 18) ?>مسودة مقترحة — غير معتمدة</h3>
                            <?php if ($isCounsel && $isOpen): ?><button type="button" class="btn btn-sm" data-copy-from="aiDraft" data-copy-to="answerText"><?= icon('file', 16) ?>نسخها إلى محرر الرد</button><?php endif ?>
                        </div>
                        <div class="q-text" id="aiDraft"><?= esc($c['ai_draft']) ?></div>
                        <span class="hint">المصدر: <?= esc(label('ai_source', $c['ai_source'])) ?> · <span class="num"><?= fdate($c['ai_generated_at'], true) ?></span>. راجع المسودة وتحقق منها قبل اعتماد أي جزء منها.</span>
                    </div>
                <?php else: ?>
                    <div class="empty">لا توجد مسودة مقترحة لهذه الاستشارة. اكتب الرد مباشرة في المحرر.</div>
                <?php endif ?>

                <?php if ($isOpen && $isCounsel): ?>
                    <form id="answer" method="post" action="<?= site_url("consultations/{$id}/answer") ?>" class="stack" style="gap: 16px;" data-confirm="سيُعتمد الرد ويُرسل إلى صاحب الاستشارة باسمك. هل تريد المتابعة؟">
                        <?= csrf_field() ?>
                        <label class="field"><span class="lbl">محرر الرد <span class="req">*</span></span>
                            <textarea class="textarea" id="answerText" name="answer" rows="10" minlength="20" placeholder="اكتب الرد القانوني المعتمد…" required><?= esc(old('answer')) ?></textarea>
                            <span class="hint">الحد الأدنى 20 حرفاً</span><?= field_error('answer') ?></label>
                        <div class="fixed-note"><?= icon('shield', 16) ?><span><?= esc($note) ?></span></div>
                        <label class="check"><input type="checkbox" name="publish" value="1" <?= old('publish') === '1' ? 'checked' : '' ?>><span>نشر الجواب في المكتبة بعد إخفاء بيانات مقدمه</span></label>
                        <div><button class="btn btn-primary"><?= icon('checkc', 18) ?>اعتماد الرد وإرساله</button></div>
                    </form>
                <?php elseif ($isOpen): ?>
                    <div class="alert alert-info"><?= icon('lock', 18) ?><span>اعتماد الرد متاح لمستشاري الشؤون القانونية فقط.</span></div>
                <?php endif ?>
            <?= partial('partials/card_close') ?>
        <?php endif ?>
    </div>

    <div class="side">
        <?php if ($isStaff): ?>
            <?= partial('partials/card_open', ['icon' => 'user', 'title' => 'مقدم الاستشارة']) ?>
                <?= partial('partials/person', ['name' => $c['owner_name'] ?? '—', 'sub' => trim(($c['owner_title'] ?? '') . ' · ' . ($c['owner_department'] ?? ''), ' ·')]) ?>
            <?= partial('partials/card_close') ?>
        <?php endif ?>

        <?php if ($isCounsel && $isOpen && $counsels !== []): ?>
            <?= partial('partials/card_open', ['icon' => 'share', 'title' => 'الإسناد']) ?>
                <form method="post" action="<?= site_url("consultations/{$id}/assign") ?>" class="stack" style="gap: 14px;">
                    <?= csrf_field() ?>
                    <label class="field"><span class="lbl">إسناد إلى مستشار</span>
                        <select class="select" name="assigned_to" required>
                            <option value="">اختر المستشار</option>
                            <?php foreach ($counsels as $u): ?><option value="<?= $u['id'] ?>" <?= (int) $c['assigned_to'] === (int) $u['id'] ? 'selected' : '' ?>><?= esc($u['name']) ?><?= (int) $u['id'] === (int) session('user_id') ? ' (أنا)' : '' ?></option><?php endforeach ?>
                        </select></label>
                    <div><button class="btn btn-primary"><?= icon('share', 18) ?>إسناد</button></div>
                </form>
            <?= partial('partials/card_close') ?>
        <?php endif ?>

        <?php if ($c['status'] !== 'closed' && ($isOwner || $isCounsel)): ?>
            <?= partial('partials/card_open', ['icon' => 'archive', 'title' => 'إغلاق الاستشارة']) ?>
                <span class="hint"><?= $isOpen ? ($isOwner ? 'إن لم تعد بحاجة إلى الرد يمكنك إغلاق الاستشارة.' : 'يُغلق الطلب دون رد.') : 'أغلق الاستشارة بعد الاطلاع على الرد.' ?></span>
                <form method="post" action="<?= site_url("consultations/{$id}/close") ?>" data-confirm="هل تريد إغلاق الاستشارة؟">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger"><?= icon('x', 18) ?>إغلاق الاستشارة</button>
                </form>
            <?= partial('partials/card_close') ?>
        <?php endif ?>

        <?php if ($isStaff): ?>
            <?= partial('partials/card_open', ['icon' => 'clock', 'title' => 'سجل الإجراءات']) ?>
                <?php if ($actions === []): ?><div class="empty">لا توجد إجراءات مسجلة.</div><?php else: ?>
                    <div class="vt">
                        <?php foreach ($actions as $a): ?>
                            <div class="vt-item done"><div class="vt-dot"><?= icon('check', 16) ?></div>
                                <div class="vt-text"><b><?= esc(label('cons_action', $a['action_code'])) ?></b><?php if ($a['description']): ?><small><?= esc($a['description']) ?></small><?php endif ?><small class="num"><?= esc($a['user_name'] ?? 'النظام') ?> · <?= fdate($a['created_at'], true) ?></small></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            <?= partial('partials/card_close') ?>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
