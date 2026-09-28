<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= partial('partials/card_open', ['icon' => 'alert', 'title' => 'الاستشارات القانونية غير مهيأة بعد']) ?>
    <p>جداول الاستشارات غير موجودة في قاعدة البيانات، أو لا تطابق ما تتوقعه المنصة.</p>
    <?php if (\App\Libraries\Access::isAdmin()): ?>
        <div class="alert alert-info"><?= icon('file', 18) ?><span>استورد الملف <b dir="ltr">database/04_consultations.sql</b> في قاعدة البيانات <b dir="ltr">investigation_platform</b> من phpMyAdmin (تبويب Import)، ثم حدّث الصفحة.</span></div>
    <?php else: ?>
        <span class="muted">تواصل مع مدير النظام لتفعيلها.</span>
    <?php endif ?>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>
