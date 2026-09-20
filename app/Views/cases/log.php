<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$icons = ['created' => 'plus', 'submitted' => 'send', 'referred' => 'share', 'referred_gm' => 'share', 'investigator_assigned' => 'user',
    'investigation_started' => 'play', 'invitation_sent' => 'mail', 'invitation_draft' => 'save', 'attendance' => 'users', 'absence_action' => 'alert',
    'session_started' => 'video', 'session_paused' => 'pause', 'session_closed' => 'video', 'topic_added' => 'list', 'statement_requested' => 'users',
    'statement_answered' => 'checkc', 'letter_sent' => 'mail', 'letter_replied' => 'msg', 'memo_saved' => 'save', 'memo_submitted' => 'file',
    'step_approved' => 'eye', 'memo_returned' => 'undo', 'memo_approved' => 'checkc', 'recommendation_added' => 'list',
    'recommendation_updated' => 'refresh', 'attachment_added' => 'clip', 'archived' => 'archive', 'closed_no_action' => 'archive'];
?>
<?= partial('partials/case_bar', ['case' => $case, 'parties' => $parties]) ?>
<?= partial('partials/card_open', ['icon' => 'clock', 'title' => 'سجل التدقيق', 'right' => '<span class="readonly-tag">' . icon('lock', 14) . 'للقراءة فقط — لا يمكن التعديل أو الحذف</span>']) ?>
    <div class="audit-head"><span></span><span>الإجراء</span><span>المستخدم</span><span>التاريخ</span><span>الوقت</span><span>الملاحظات</span></div>
    <div>
        <?php foreach ($rows as $i => $r): $meta = $r['meta'] ? json_decode($r['meta'], true) : []; ?>
            <div class="audit-row">
                <div class="audit-ico" <?= $i === count($rows) - 1 ? 'style="background: var(--primary); color: #fff;"' : '' ?>><?= icon($icons[$r['action_code']] ?? 'dots', 20) ?></div>
                <b style="font-size: 15px;"><?= esc($r['description']) ?></b>
                <div><b style="display: block; font-size: 14px;"><?= esc($r['user_name'] ?? 'النظام') ?></b><span class="hint"><?= esc($r['job_title'] ?? '') ?></span></div>
                <span class="num"><?= date('Y-m-d', strtotime($r['created_at'])) ?></span>
                <span class="num muted"><?= date('H:i', strtotime($r['created_at'])) ?></span>
                <span class="muted"><?= esc($meta['note'] ?? '—') ?></span>
            </div>
        <?php endforeach ?>
    </div>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>
<?= $this->section('actions') ?>
<div class="action-bar no-print">
    <button class="btn" type="button" onclick="window.print()"><?= icon('file', 18) ?>طباعة السجل</button>
    <a class="btn btn-ghost" href="<?= site_url('cases/' . $case['id']) ?>">العودة للمعاملة</a>
    <span class="note">يُحفظ كل إجراء آلياً مع هوية المستخدم ووقت التنفيذ</span>
</div>
<?= $this->endSection() ?>
