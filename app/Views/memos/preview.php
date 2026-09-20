<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= partial('partials/memo_doc', ['case' => $case, 'memo' => $memo, 'sections' => $sections]) ?>
<?= $this->endSection() ?>
<?= $this->section('actions') ?>
<div class="action-bar no-print">
    <button class="btn btn-primary" type="button" onclick="window.print()"><?= icon('file', 18) ?>طباعة / حفظ PDF</button>
    <a class="btn" href="<?= site_url("cases/{$case['id']}/memo") ?>">العودة للمذكرة</a>
</div>
<?= $this->endSection() ?>
