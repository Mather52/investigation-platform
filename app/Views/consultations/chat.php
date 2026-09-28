<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\ConsultationService;
$cur   = $current;
$me    = (int) session('user_id');
$short = static fn (?string $t, int $n) => mb_strimwidth(trim(preg_replace('/\s+/u', ' ', (string) $t)), 0, $n, '…');
$when  = static function (?string $t): string {
    if (! $t) { return ''; }
    return date('Y-m-d', strtotime($t)) === date('Y-m-d') ? date('H:i', strtotime($t)) : date('Y-m-d', strtotime($t));
};
$statusText = static fn (string $s, bool $staff) => match ($s) {
    'answered' => $staff ? 'تم الرد' : 'وصلك رد',
    'closed'   => 'مغلقة',
    default    => $staff ? 'بانتظار ردك' : 'بانتظار الرد',
};
?>
<div class="chat-app <?= $cur || $composeNew ? 'has-open' : '' ?>">
    <aside class="chat-list" aria-label="المحادثات">
        <div class="chat-list-head">
            <h2><?= $isStaff ? 'المحادثات' : 'استشاراتي' ?></h2>
            <?php if (! $isStaff): ?><a class="btn btn-primary btn-sm" href="<?= site_url('consultations?new=1') ?>"><?= icon('plus', 16) ?>محادثة جديدة</a><?php endif ?>
        </div>
        <?php if ($isStaff): ?>
            <div class="chat-tabs">
                <a class="<?= $tab === 'waiting' ? 'is-active' : '' ?>" href="<?= site_url('consultations') ?>">بانتظار الرد <span class="num"><?= (int) $waiting ?></span></a>
                <a class="<?= $tab === 'all' ? 'is-active' : '' ?>" href="<?= site_url('consultations?tab=all') ?>">كل المحادثات</a>
            </div>
        <?php endif ?>
        <div class="chat-items">
            <?php if ($list === []): ?>
                <div class="chat-empty-list"><?= $isStaff ? ($tab === 'waiting' ? 'لا توجد محادثات بانتظار الرد.' : 'لا توجد محادثات بعد.') : 'لا توجد محادثات بعد. ابدأ محادثة جديدة.' ?></div>
            <?php endif ?>
            <?php foreach ($list as $r): $open = $cur && (int) $cur['id'] === (int) $r['id'];
                $unread = $isStaff ? in_array($r['status'], ConsultationService::OPEN_STATUSES, true) : ($r['status'] === 'answered' && $r['owner_read_at'] === null); ?>
                <a class="chat-item <?= $open ? 'is-open' : '' ?> <?= $unread ? 'is-unread' : '' ?>" href="<?= site_url('consultations?c=' . $r['id'] . ($isStaff && $tab === 'all' ? '&tab=all' : '')) ?>">
                    <span class="chat-avatar"><?= esc(initial($isStaff ? $r['owner_name'] : 'ش')) ?></span>
                    <span class="chat-item-main">
                        <span class="chat-item-top"><b><?= esc($isStaff ? ($r['owner_name'] ?? '—') : $short($r['subject'], 40)) ?></b><time class="num"><?= $when($r['last_message_at'] ?? $r['created_at']) ?></time></span>
                        <span class="chat-item-sub"><?= $isStaff ? esc($short($r['subject'], 40)) . ' · ' : '' ?><?= esc($short($r['last_message'] ?? $r['body'], 60)) ?></span>
                    </span>
                    <?php if ($unread): ?><i class="chat-dot" title="<?= esc($statusText($r['status'], $isStaff)) ?>"></i><?php endif ?>
                </a>
            <?php endforeach ?>
        </div>
    </aside>

    <section class="chat-main">
        <?php if ($cur): ?>
            <header class="chat-head">
                <a class="chat-back" href="<?= site_url('consultations' . ($isStaff && $tab === 'all' ? '?tab=all' : '')) ?>" aria-label="رجوع إلى المحادثات"><?= icon('back', 20) ?></a>
                <span class="chat-avatar lg"><?= esc(initial($isStaff ? $cur['owner_name'] : 'ش')) ?></span>
                <div class="chat-head-text">
                    <b><?= esc($cur['subject']) ?></b>
                    <small><span class="num"><?= esc($cur['ref_no']) ?></span> · <?= esc($cur['category_name'] ?? '') ?><?= $isStaff ? ' · ' . esc($cur['owner_name'] ?? '') . ($cur['owner_department'] ? ' — ' . esc($cur['owner_department']) : '') : ' · إدارة الشؤون القانونية والالتزام' ?></small>
                </div>
                <div class="chat-head-end">
                    <?php if (ConsultationService::isConfidential($cur['confidentiality'])): ?><span class="secure"><?= icon('lock', 14) ?><?= esc(label('conf', $cur['confidentiality'])) ?></span><?php endif ?>
                    <?= badge($statusText($cur['status'], $isStaff), ['answered' => 'green', 'closed' => 'gray'][$cur['status']] ?? 'yellow', true) ?>
                    <?php if ($cur['status'] !== 'closed'): ?>
                        <form method="post" action="<?= site_url('consultations/' . $cur['id'] . '/close') ?>" data-confirm="هل تريد إغلاق المحادثة؟ لن يمكن إرسال رسائل بعدها."><?= csrf_field() ?><button class="btn btn-sm btn-ghost" title="إغلاق المحادثة"><?= icon('x', 16) ?>إغلاق</button></form>
                    <?php endif ?>
                </div>
            </header>

            <div class="chat-thread" data-autoscroll>
                <?php $lastDay = ''; foreach ($messages as $m): $day = date('Y-m-d', strtotime($m['created_at'])); $mine = $m['user_id'] === $me; ?>
                    <?php if ($day !== $lastDay): $lastDay = $day; ?><div class="chat-day num"><span><?= $day === date('Y-m-d') ? 'اليوم' : esc($day) ?></span></div><?php endif ?>
                    <div class="msg <?= $mine ? 'mine' : 'theirs' ?> <?= $m['from_owner'] ? '' : 'legal' ?>">
                        <?php if (! $mine): ?><span class="msg-name"><?= esc($m['from_owner'] ? ($m['name'] ?? '') : 'الشؤون القانونية' . ($isStaff ? ' · ' . ($m['name'] ?? '') : '')) ?></span><?php endif ?>
                        <div class="msg-bubble"><?= nl2br(esc($m['body'])) ?></div>
                        <time class="num"><?= date('H:i', strtotime($m['created_at'])) ?></time>
                    </div>
                <?php endforeach ?>
                <span id="end"></span>
            </div>

            <?php if ($cur['status'] === 'closed'): ?>
                <div class="chat-closed"><?= icon('lock', 16) ?>أُغلقت هذه المحادثة. <?php if (! $isStaff): ?><a href="<?= site_url('consultations?new=1') ?>">ابدأ محادثة جديدة</a><?php endif ?></div>
            <?php else: ?>
                <form class="chat-composer" method="post" action="<?= site_url('consultations/' . $cur['id'] . '/messages') ?>">
                    <?= csrf_field() ?>
                    <textarea name="body" rows="1" placeholder="اكتب رسالتك…" aria-label="نص الرسالة" required data-autogrow></textarea>
                    <button class="chat-send" aria-label="إرسال"><?= icon('send', 20) ?></button>
                </form>
                <?php if ($isStaff && ! $isOwner): ?><span class="chat-note">ردك يصدر باسم إدارة الشؤون القانونية والالتزام · Ctrl + Enter للإرسال</span><?php endif ?>
            <?php endif ?>

        <?php elseif ($composeNew && ! $isStaff): ?>
            <div class="chat-new">
                <div class="chat-new-head"><span class="chat-avatar lg"><?= icon('msg', 22) ?></span><div><b>محادثة جديدة مع الشؤون القانونية</b><small>اكتب سؤالك وسيصلك الرد في هذه الصفحة</small></div></div>
                <?php if ($categories === []): ?><div class="alert alert-warn"><?= icon('alert', 18) ?><span>لا توجد تصنيفات مفعّلة للاستشارات. تواصل مع مدير النظام.</span></div><?php endif ?>
                <form method="post" action="<?= site_url('consultations') ?>" class="stack" style="gap: 16px;">
                    <?= csrf_field() ?>
                    <div class="grid g2">
                        <label class="field"><span class="lbl">التصنيف <span class="req">*</span></span>
                            <select class="select" name="category_id" required><option value="">اختر التصنيف</option>
                                <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>><?= esc($cat['name']) ?></option><?php endforeach ?>
                            </select><?= field_error('category_id') ?></label>
                        <label class="field"><span class="lbl">درجة السرية</span>
                            <select class="select" name="confidentiality">
                                <?php foreach ($levels as $lv): ?><option value="<?= esc($lv) ?>" <?= old('confidentiality', $levels[0]) === $lv ? 'selected' : '' ?>><?= esc(label('conf', $lv)) ?></option><?php endforeach ?>
                            </select></label>
                    </div>
                    <label class="field"><span class="lbl">الموضوع <span class="req">*</span></span>
                        <input class="input" name="subject" value="<?= esc(old('subject')) ?>" maxlength="255" placeholder="عنوان مختصر لسؤالك" required><?= field_error('subject') ?></label>
                    <label class="field"><span class="lbl">رسالتك <span class="req">*</span></span>
                        <textarea class="textarea" name="body" rows="5" placeholder="اكتب سؤالك بوضوح…" required><?= esc(old('body')) ?></textarea><?= field_error('body') ?></label>
                    <div><button class="btn btn-primary"><?= icon('send', 18) ?>إرسال</button></div>
                </form>
            </div>

        <?php else: ?>
            <div class="chat-placeholder">
                <span class="chat-avatar xl"><?= icon('msg', 32) ?></span>
                <b><?= $isStaff ? 'اختر محادثة للرد عليها' : 'ابدأ محادثة مع الشؤون القانونية' ?></b>
                <span class="muted"><?= $isStaff ? 'المحادثات التي تنتظر ردك تظهر في تبويب «بانتظار الرد».' : 'اكتب سؤالك وسيرد عليك مستشار من إدارة الشؤون القانونية والالتزام.' ?></span>
                <?php if (! $isStaff): ?><a class="btn btn-primary" href="<?= site_url('consultations?new=1') ?>"><?= icon('plus', 18) ?>محادثة جديدة</a><?php endif ?>
            </div>
        <?php endif ?>
    </section>
</div>
<?= $this->endSection() ?>
