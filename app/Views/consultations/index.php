<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php use App\Libraries\ConsultationService; ?>
<?= partial('partials/card_open', [
    'icon' => 'msg', 'title' => 'استشاراتي',
    'right' => '<div class="row">' . ($isStaff ? '<a class="btn btn-sm" href="' . site_url('consultations') . '">' . icon('list', 16) . 'صندوق الوارد</a>' : '')
        . '<a class="btn btn-primary btn-sm" href="' . site_url('consultations/new') . '">' . icon('plus', 16) . 'طلب استشارة</a></div>',
]) ?>
    <?php if ($rows === []): ?>
        <div class="empty">لم تقدّم أي استشارة بعد. اضغط «طلب استشارة» لكتابة سؤالك إلى إدارة الشؤون القانونية والالتزام.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>الرقم المرجعي</th><th>الموضوع</th><th>التصنيف</th><th>تاريخ التقديم</th><th>الحالة</th><th>رد المستشار</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $hasAnswer = $r['answer'] !== null && $r['answer'] !== ''; $unreadAns = $tracksRead && $r['status'] === 'answered' && $r['owner_read_at'] === null && $hasAnswer; ?>
                <tr>
                    <td><a class="link-strong num" href="<?= site_url('consultations/' . $r['id']) ?>"><?= esc($r['ref_no']) ?></a><?php if (ConsultationService::isConfidential($r['confidentiality'])): ?> <span title="<?= esc(label('conf', $r['confidentiality'])) ?>" style="color: var(--y);"><?= icon('lock', 14) ?></span><?php endif ?></td>
                    <td style="max-width: 320px;"><?= esc($r['subject']) ?></td>
                    <td><?= esc($r['category_name'] ?? '—') ?></td>
                    <td class="num"><?= fdate($r['created_at']) ?></td>
                    <td><?= status_badge('cons_status', $r['status']) ?></td>
                    <td style="max-width: 360px;">
                        <?php if ($hasAnswer): ?>
                            <?= esc(mb_strimwidth($r['answer'], 0, 140, '…')) ?>
                            <div class="hint num"><?= esc($r['answered_by_name'] ?? '') ?> · <?= fdate($r['answered_at']) ?><?php if ($unreadAns): ?> <?= badge('جديد', 'teal') ?><?php endif ?></div>
                        <?php else: ?>
                            <span class="muted"><?= $r['status'] === 'closed' ? 'أُغلقت دون رد' : 'بانتظار رد المستشار' ?></span>
                        <?php endif ?>
                    </td>
                    <td><a class="btn btn-sm" href="<?= site_url('consultations/' . $r['id']) ?>">فتح</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table></div>
    <?php endif ?>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>
