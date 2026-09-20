<label class="dropzone">
    <?= icon('upload', 28) ?>
    <strong><?= esc($text ?? 'اضغط لاختيار الملفات') ?></strong>
    <span class="hint">PDF، DOC، XLS، JPG، PNG · الحد الأقصى 20 ميجابايت للملف</span>
    <input type="file" name="<?= esc($name ?? 'attachments[]', 'attr') ?>" <?= ($multiple ?? true) ? 'multiple' : '' ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="sr-only">
    <span class="hint" data-files>لم يتم اختيار ملفات</span>
</label>
