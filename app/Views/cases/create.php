<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<form id="caseForm" method="post" action="<?= site_url('cases') ?>" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <datalist id="employees">
        <?php foreach ($employees as $e): ?><option value="<?= esc($e['employee_no'] . ' — ' . $e['full_name']) ?>"><?= esc(($e['job_title'] ?? '') . ' · ' . ($e['department_name'] ?? '')) ?></option><?php endforeach ?>
    </datalist>

    <?= partial('partials/card_open', ['icon' => 'cal', 'title' => 'البيانات الأساسية']) ?>
        <div class="grid g3">
            <div class="field"><span class="lbl">رقم المعاملة</span><div class="ro"><span class="num">INV-<?= esc($preview) ?></span><span class="tag">يُولَّد آلياً</span></div></div>
            <div class="field"><span class="lbl">تاريخ الإنشاء</span><div class="ro"><span class="num"><?= date('Y-m-d') ?></span><span class="tag">آلي</span></div></div>
            <div class="field"><span class="lbl">حالة المعاملة</span><div class="ro"><span><?= badge('جديدة', 'teal') ?> <span class="muted">— تتحكم بها المنصة آلياً</span></span></div></div>

            <label class="field"><span class="lbl">نوع المعاملة <span class="req">*</span></span>
                <select class="select" name="case_type_id" required><option value="">اختر نوع المعاملة</option>
                    <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= old('case_type_id') == $t['id'] ? 'selected' : '' ?>><?= esc($t['name_ar']) ?></option><?php endforeach ?>
                </select><?= field_error('case_type_id') ?></label>
            <label class="field"><span class="lbl">مصدر المعاملة <span class="req">*</span></span>
                <select class="select" name="source_code" required>
                    <?php foreach ($sources as $s): $locked = $s['code'] !== 'manual' && ! $canImport; ?>
                        <option value="<?= esc($s['code']) ?>" <?= $locked ? 'disabled' : '' ?> <?= old('source_code', 'manual') === $s['code'] ? 'selected' : '' ?>><?= esc($s['name_ar']) ?><?= $locked ? ' (للشؤون القانونية ورئيس التحقيقات)' : '' ?></option>
                    <?php endforeach ?>
                </select><?= field_error('source_code') ?></label>
            <label class="field"><span class="lbl">تاريخ الاستلام <span class="req">*</span></span><input class="input" type="date" name="received_at" value="<?= esc(old('received_at', date('Y-m-d'))) ?>" required><?= field_error('received_at') ?></label>

            <label class="field"><span class="lbl">درجة السرية</span>
                <select class="select" name="confidentiality">
                    <?php foreach (['confidential', 'normal', 'top_secret'] as $c): ?><option value="<?= $c ?>" <?= old('confidentiality', 'confidential') === $c ? 'selected' : '' ?>><?= label('conf', $c) ?></option><?php endforeach ?>
                </select></label>
            <label class="field"><span class="lbl">الرقم المرجعي في النظام المصدر</span><input class="input" name="external_ref" value="<?= esc(old('external_ref')) ?>" placeholder="اختياري — رقم إتقان أو إفادة"></label>
        </div>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'user', 'title' => 'بيانات مقدم الشكوى']) ?>
        <label class="field" style="max-width: 900px;"><span class="lbl">مقدم الشكوى</span>
            <input class="input" name="complainant" list="employees" value="<?= esc(old('complainant')) ?>" placeholder="ابحث عن موظف بالاسم أو الرقم الوظيفي…" autocomplete="off">
            <span class="hint">اختر من القائمة. اتركه فارغاً إذا كان البلاغ صادراً من الإدارة.</span></label>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'shield', 'title' => 'بيانات الواقعة']) ?>
        <label class="field"><span class="lbl">موضوع المعاملة <span class="req">*</span></span><input class="input" name="subject" value="<?= esc(old('subject')) ?>" placeholder="عنوان مختصر يصف المخالفة أو الشكوى" required><?= field_error('subject') ?></label>
        <div class="field">
            <span class="lbl">الموظف / الموظفون محل التحقيق <span class="req">*</span></span>
            <div class="multi" id="accusedList">
                <?php foreach ((array) old('accused', ['']) as $a): ?>
                    <input class="input" name="accused[]" list="employees" value="<?= esc($a) ?>" placeholder="ابحث عن موظف بالاسم أو الرقم الوظيفي…" autocomplete="off">
                <?php endforeach ?>
            </div>
            <template id="accusedTpl"><input class="input" name="accused[]" list="employees" placeholder="ابحث عن موظف بالاسم أو الرقم الوظيفي…" autocomplete="off"></template>
            <div><button type="button" class="btn btn-sm" data-clone="accusedTpl" data-target="accusedList"><?= icon('plus', 16) ?>إضافة موظف آخر</button></div>
        </div>
        <div class="grid g3">
            <label class="field"><span class="lbl">تاريخ الواقعة <span class="req">*</span></span><input class="input" type="date" name="incident_date" value="<?= esc(old('incident_date')) ?>" max="<?= date('Y-m-d') ?>" required><?= field_error('incident_date') ?></label>
            <label class="field"><span class="lbl">الإدارة / القسم <span class="req">*</span></span>
                <select class="select" name="department_id" required><option value="">اختر الإدارة أو القسم</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= old('department_id') == $d['id'] ? 'selected' : '' ?>><?= esc($d['name_ar']) ?></option><?php endforeach ?>
                </select><?= field_error('department_id') ?></label>
            <label class="field"><span class="lbl">مكان الواقعة</span><input class="input" name="incident_place" value="<?= esc(old('incident_place')) ?>" placeholder="المبنى أو القسم"></label>
        </div>
        <label class="field"><span class="lbl">وصف المخالفة / الواقعة <span class="req">*</span></span>
            <textarea class="textarea" name="description" rows="6" minlength="20" placeholder="اكتب وصفاً واضحاً ومختصراً للواقعة أو المخالفة، مع ذكر الزمان والمكان والأطراف المعنية…" required><?= esc(old('description')) ?></textarea>
            <span class="hint">الحد الأدنى 20 حرفاً</span><?= field_error('description') ?></label>
    <?= partial('partials/card_close') ?>

    <?= partial('partials/card_open', ['icon' => 'clip', 'title' => 'المرفقات']) ?>
        <div class="row hint">الأنواع المدعومة: <?= badge('PDF', 'red') ?><?= badge('DOC', 'blue') ?><?= badge('XLS', 'green') ?><?= badge('JPG', 'yellow') ?><?= badge('PNG', 'gray') ?></div>
        <?= partial('partials/dropzone', ['text' => 'اضغط هنا لرفع مرفق']) ?>
    <?= partial('partials/card_close') ?>
</form>
<?= $this->endSection() ?>

<?= $this->section('actions') ?>
<div class="action-bar">
    <button class="btn btn-primary" type="submit" form="caseForm" name="action" value="send"><?= icon('send', 18) ?>حفظ وإرسال</button>
    <button class="btn" type="submit" form="caseForm" name="action" value="draft" formnovalidate><?= icon('save', 18) ?>حفظ كمسودة</button>
    <a class="btn btn-ghost" href="<?= site_url('dashboard') ?>">إلغاء</a>
</div>
<?= $this->endSection() ?>
