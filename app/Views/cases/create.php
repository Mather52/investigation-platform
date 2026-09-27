<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<form id="caseForm" method="post" action="<?= site_url('cases') ?>" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <datalist id="employees">
        <?php foreach ($employees as $e): ?><option value="<?= esc($e['employee_no'] . ' — ' . $e['full_name']) ?>"><?= esc(($e['job_title'] ?? '') . ' · ' . ($e['department_name'] ?? '')) ?></option><?php endforeach ?>
    </datalist>

    <?php
    // [الأيقونة، الوصف، نص مساعدة الرقم المرجعي، نص مساعدة الملف]
    $srcInfo = [
        'manual' => ['file', 'تعبئة النموذج أدناه', '', ''],
        'paper'  => ['clip', 'رفع نسخة ممسوحة من المخالفة', 'رقم الصادر للمستند الورقي', 'النسخة الممسوحة من المستند الورقي الأصلي.'],
        'etqan'  => ['link', 'استيراد المخالفة آلياً', 'رقم المخالفة في نظام إتقان', 'ملف المخالفة كما صدر من نظام إتقان.'],
        'efada'  => ['msg', 'استيراد الشكوى آلياً', 'رقم الشكوى في نظام إفادة', 'ملف الشكوى كما صدر من نظام إفادة.'],
        'email'  => ['mail', 'رفع رسالة واردة', 'بريد المرسل للرسالة الواردة', 'ملف الرسالة نفسها (EML أو MSG) وليس نصاً منسوخاً منها.'],
    ];
    $byCode  = array_column($sources, null, 'code');
    $ordered = array_merge(array_intersect_key($srcInfo, $byCode), array_diff_key($byCode, $srcInfo));
    $picked  = (string) old('source_code', 'manual');
    if (! isset($byCode[$picked]) || ($picked !== 'manual' && ! $canImport)) {
        $picked = 'manual';
    }
    ?>
    <?= partial('partials/card_open', ['icon' => 'share', 'title' => 'مصدر المعاملة']) ?>
        <div class="source-cards" role="radiogroup" aria-label="مصدر المعاملة">
            <?php foreach (array_keys($ordered) as $code): $src = $byCode[$code]; [$ic, $desc, $refHint, $fileHint] = $srcInfo[$code] ?? ['file', '', '', '']; $locked = $code !== 'manual' && ! $canImport; ?>
                <label class="src-card <?= $locked ? 'is-locked' : '' ?>" <?= $locked ? 'title="متاح لمدير الشؤون القانونية ورئيس القسم فقط"' : '' ?>>
                    <input type="radio" name="source_code" value="<?= esc($code) ?>" <?= $picked === $code ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>
                           data-name="<?= esc($src['name_ar']) ?>" data-hint-ref="<?= esc($refHint) ?>" data-hint-file="<?= esc($fileHint) ?>">
                    <span class="src-ico"><?= icon($ic, 22) ?></span>
                    <b><?= esc($src['name_ar']) ?></b>
                    <small><?= esc($desc) ?></small>
                    <?= $code === 'manual' ? badge('لجميع الموظفين', 'green') : badge('الشؤون القانونية ورئيس القسم', 'yellow') ?>
                    <?php if ($locked): ?><span class="sr-only">غير متاح: يتطلب دور الشؤون القانونية أو رئيس القسم</span><?php endif ?>
                </label>
            <?php endforeach ?>
        </div>
        <?= field_error('source_code') ?>
        <?php if (! $canImport): ?><span class="hint"><?= icon('lock', 14) ?> الاستيراد من الأنظمة والمستندات الورقية والبريد متاح لمدير الشؤون القانونية ورئيس القسم فقط.</span><?php endif ?>
    <?= partial('partials/card_close') ?>

    <?php $refHint = $srcInfo[$picked][2] ?? ''; $fileHint = $srcInfo[$picked][3] ?? ''; ?>
    <section class="card" id="importSection" data-show-when="source_code!=manual" data-disable-hidden <?= $picked === 'manual' ? 'hidden' : '' ?>>
        <div class="card-head"><div class="card-title"><?= icon('upload', 22) ?><h2>بيانات الاستيراد</h2></div></div>
        <div class="card-body">
            <div class="grid g2">
                <label class="field"><span class="lbl">الرقم المرجعي في النظام المصدر <span class="req">*</span></span>
                    <input class="input" name="external_ref" value="<?= esc(old('external_ref')) ?>" maxlength="60" data-required <?= $picked === 'manual' ? 'disabled' : 'required' ?>>
                    <span class="hint" data-ref-hint><?= esc($refHint) ?></span><?= field_error('external_ref') ?></label>
                <label class="field"><span class="lbl">تاريخ المخالفة في النظام المصدر</span>
                    <input class="input" type="date" name="source_date" value="<?= esc(old('source_date')) ?>" max="<?= date('Y-m-d') ?>" <?= $picked === 'manual' ? 'disabled' : '' ?>>
                    <span class="hint">اختياري</span><?= field_error('source_date') ?></label>
            </div>
            <div class="row">
                <button type="button" class="btn" disabled><?= icon('refresh', 18) ?>استيراد البيانات</button>
                <div class="alert alert-warn" style="flex: 1; min-width: 260px;"><?= icon('alert', 18) ?><span>يُفعّل الاستيراد الآلي بعد اعتماد الربط التقني مع الأنظمة. أدخل الرقم المرجعي وأكمل الحقول يدوياً، وأرفق أصل المخالفة.</span></div>
            </div>
            <div class="field"><span class="lbl">أصل المخالفة من المصدر <span class="req">*</span></span>
                <label class="dropzone">
                    <?= icon('upload', 28) ?>
                    <strong>اضغط لاختيار ملف أصل المخالفة</strong>
                    <span class="hint" data-file-hint><?= esc($fileHint) ?></span>
                    <span class="hint">PDF، JPG، PNG، DOC، DOCX، EML، MSG · الحد الأقصى 20 ميجابايت</span>
                    <input type="file" name="source_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.eml,.msg" class="sr-only" data-required <?= $picked === 'manual' ? 'disabled' : 'required' ?>>
                    <span class="hint" data-files>لم يتم اختيار ملف</span>
                </label>
                <?= field_error('source_file') ?>
            </div>
        </div>
    </section>

    <?= partial('partials/card_open', ['icon' => 'cal', 'title' => 'البيانات الأساسية']) ?>
        <div class="grid g3">
            <div class="field"><span class="lbl">رقم المعاملة</span><div class="ro"><span class="num">INV-<?= esc($preview) ?></span><span class="tag">يُولَّد آلياً</span></div></div>
            <div class="field"><span class="lbl">تاريخ الإنشاء</span><div class="ro"><span class="num"><?= date('Y-m-d') ?></span><span class="tag">آلي</span></div></div>
            <div class="field"><span class="lbl">حالة المعاملة</span><div class="ro"><span><?= badge('جديدة', 'teal') ?> <span class="muted">— تتحكم بها المنصة آلياً</span></span></div></div>

            <label class="field"><span class="lbl">نوع المعاملة <span class="req">*</span></span>
                <select class="select" name="case_type_id" required><option value="">اختر نوع المعاملة</option>
                    <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= old('case_type_id') == $t['id'] ? 'selected' : '' ?>><?= esc($t['name_ar']) ?></option><?php endforeach ?>
                </select><?= field_error('case_type_id') ?></label>
            <div class="field"><span class="lbl">مصدر المعاملة</span><div class="ro"><span data-src-name><?= esc($byCode[$picked]['name_ar'] ?? '') ?></span><span class="tag">من الاختيار أعلاه</span></div></div>
            <label class="field"><span class="lbl">تاريخ الاستلام <span class="req">*</span></span><input class="input" type="date" name="received_at" value="<?= esc(old('received_at', date('Y-m-d'))) ?>" required><?= field_error('received_at') ?></label>

            <label class="field"><span class="lbl">درجة السرية</span>
                <select class="select" name="confidentiality">
                    <?php foreach (['confidential', 'normal', 'top_secret'] as $c): ?><option value="<?= $c ?>" <?= old('confidentiality', 'confidential') === $c ? 'selected' : '' ?>><?= label('conf', $c) ?></option><?php endforeach ?>
                </select></label>
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
