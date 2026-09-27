<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\ConsultationService;
$empty = [
    'new'      => 'لا توجد استشارات جديدة بانتظار الإسناد أو الرد.',
    'mine'     => 'لا توجد استشارات مُسندة إليك حالياً.',
    'answered' => 'لم يُعتمد أي رد بعد.',
    'library'  => 'المكتبة فارغة. تُضاف إليها الإجابات التي يختار المستشار نشرها بعد إخفاء بيانات مقدميها.',
];
?>
<div class="row between">
    <span class="muted">المسودات المقترحة لا تظهر لمقدمي الاستشارات، ولا يصلهم إلا الرد المعتمد.</span>
    <div class="row">
        <a class="btn btn-sm" href="<?= site_url('consultations?tab=own') ?>"><?= icon('user', 16) ?>استشاراتي</a>
        <a class="btn btn-sm" href="<?= site_url('consultations/new') ?>"><?= icon('plus', 16) ?>طلب استشارة</a>
    </div>
</div>

<section class="card">
    <nav class="tabs" aria-label="صندوق الاستشارات">
        <?php foreach ($tabs as $k => $lbl): ?>
            <a href="<?= site_url('consultations?tab=' . $k) ?>" class="<?= $tab === $k ? 'is-active' : '' ?>"><?= esc($lbl) ?><span class="cnt num"><?= (int) $counts[$k] ?></span></a>
        <?php endforeach ?>
    </nav>
    <div class="card-body">
    <?php if ($rows === []): ?>
        <div class="empty"><?= esc($empty[$tab]) ?></div>
    <?php elseif ($tab === 'library'): ?>
        <div class="similar">
            <?php foreach ($rows as $r): ?>
                <details class="similar-item">
                    <summary>
                        <div><b><?= esc($r['subject']) ?></b><div class="hint num"><?= esc($r['ref_no']) ?> · <?= esc($r['category_name'] ?? '—') ?> · <?= fdate($r['answered_at']) ?></div></div>
                        <a class="btn btn-sm" href="<?= site_url('consultations/' . $r['id']) ?>">فتح</a>
                    </summary>
                    <div class="similar-body"><div class="answer-box"><div class="q-text"><?= esc($r['answer']) ?></div></div></div>
                </details>
            <?php endforeach ?>
        </div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>الرقم المرجعي</th><th>الموضوع</th><th>التصنيف</th><th>مقدم الاستشارة</th><th>التاريخ</th><th>الحالة</th><th><?= $tab === 'answered' ? 'المستشار' : 'المُسند إليه' ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a class="link-strong num" href="<?= site_url('consultations/' . $r['id']) ?>"><?= esc($r['ref_no']) ?></a><?php if (ConsultationService::isConfidential($r['confidentiality'])): ?> <span title="<?= esc(label('conf', $r['confidentiality'])) ?>" style="color: var(--y);"><?= icon('lock', 14) ?></span><?php endif ?></td>
                    <td style="max-width: 320px;"><?= esc($r['subject']) ?></td>
                    <td><?= esc($r['category_name'] ?? '—') ?></td>
                    <td><?= partial('partials/person', ['name' => $r['owner_name'] ?? '—', 'sub' => $r['owner_department'] ?? '']) ?></td>
                    <td class="num"><?= fdate($tab === 'answered' ? $r['answered_at'] : $r['created_at']) ?></td>
                    <td><?= status_badge('cons_status', $r['status']) ?><?php if ((int) $r['is_published'] === 1): ?> <?= badge('منشورة', 'teal') ?><?php endif ?></td>
                    <td><?= esc(($tab === 'answered' ? $r['answered_by_name'] : $r['assigned_name']) ?? '—') ?></td>
                    <td><a class="btn btn-sm <?= in_array($r['status'], ConsultationService::OPEN_STATUSES, true) ? 'btn-primary' : '' ?>" href="<?= site_url('consultations/' . $r['id']) ?>"><?= in_array($r['status'], ConsultationService::OPEN_STATUSES, true) ? 'مراجعة' : 'فتح' ?></a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table></div>
    <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
