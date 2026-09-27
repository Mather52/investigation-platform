<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<form id="consForm" method="post" action="<?= site_url('consultations') ?>" class="stack">
    <?= csrf_field() ?>
    <?= partial('partials/card_open', ['icon' => 'msg', 'title' => 'بيانات الاستشارة']) ?>
        <div class="grid g3">
            <div class="field"><span class="lbl">الرقم المرجعي</span><div class="ro"><span class="num">CONS-<?= date('Y') ?>-XXXX</span><span class="tag">يُولَّد آلياً</span></div></div>
            <label class="field"><span class="lbl">التصنيف <span class="req">*</span></span>
                <select class="select" name="category_id" required>
                    <option value="">اختر تصنيف الاستشارة</option>
                    <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>><?= esc($cat['name']) ?></option><?php endforeach ?>
                </select><?= field_error('category_id') ?>
                <?php if ($categories === []): ?><span class="hint">لا توجد تصنيفات مفعّلة. تواصل مع مدير النظام.</span><?php endif ?></label>
            <label class="field"><span class="lbl">درجة السرية <span class="req">*</span></span>
                <select class="select" name="confidentiality" required>
                    <?php foreach ($levels as $lv): ?><option value="<?= esc($lv) ?>" <?= old('confidentiality', $levels[0]) === $lv ? 'selected' : '' ?>><?= esc(label('conf', $lv)) ?></option><?php endforeach ?>
                </select>
                <span class="hint">الاستشارة السرية لا تُرسل إلى أي جهة خارج المنصة.</span><?= field_error('confidentiality') ?></label>
        </div>
        <label class="field"><span class="lbl">الموضوع <span class="req">*</span></span>
            <input class="input" id="consSubject" name="subject" value="<?= esc(old('subject')) ?>" maxlength="255" placeholder="عنوان مختصر لسؤالك، مثال: احتساب رصيد الإجازة الاضطرارية" autocomplete="off" required><?= field_error('subject') ?></label>
        <label class="field"><span class="lbl">نص السؤال <span class="req">*</span></span>
            <textarea class="textarea" name="body" rows="7" minlength="10" placeholder="اشرح سؤالك ووقائعه بوضوح، ويُفضّل عدم ذكر أسماء أشخاص آخرين إلا عند الحاجة…" required><?= esc(old('body')) ?></textarea><?= field_error('body') ?></label>
    <?= partial('partials/card_close') ?>
</form>

<?= partial('partials/card_open', ['icon' => 'search2', 'title' => 'إجابات مشابهة', 'right' => '<span class="hint">من مكتبة الاستشارات المنشورة بعد إخفاء بيانات مقدميها</span>']) ?>
    <div class="similar" data-similar="<?= site_url('consultations/similar') ?>" data-source="consSubject">
        <div data-similar-list class="similar">
            <?php foreach ($similar as $r): ?>
                <details class="similar-item">
                    <summary><div><b><?= esc($r['subject']) ?></b><div class="hint num"><?= esc($r['ref_no']) ?> · <?= esc($r['category_name'] ?? '') ?></div></div><span class="btn btn-sm">عرض الإجابة</span></summary>
                    <div class="similar-body"><div class="answer-box"><div class="q-text"><?= esc($r['answer']) ?></div></div>
                        <div><a class="btn btn-sm btn-primary" href="<?= site_url('consultations?found=1') ?>"><?= icon('check', 16) ?>وجدت إجابتي</a></div></div>
                </details>
            <?php endforeach ?>
        </div>
        <div class="empty" data-similar-empty <?= $similar === [] ? '' : 'hidden' ?>>اكتب موضوع الاستشارة لعرض أقرب الإجابات المنشورة سابقاً.</div>
        <template>
            <details class="similar-item">
                <summary><div><b data-f="subject"></b><div class="hint num"><span data-f="ref"></span> · <span data-f="category"></span></div></div><span class="btn btn-sm">عرض الإجابة</span></summary>
                <div class="similar-body"><div class="answer-box"><div class="q-text" data-f="answer"></div></div>
                    <div><a class="btn btn-sm btn-primary" href="<?= site_url('consultations?found=1') ?>"><?= icon('check', 16) ?>وجدت إجابتي</a></div></div>
            </details>
        </template>
    </div>
<?= partial('partials/card_close') ?>
<?= $this->endSection() ?>

<?= $this->section('actions') ?>
<div class="action-bar">
    <button class="btn btn-primary" type="submit" form="consForm"><?= icon('send', 18) ?>إرسال الاستشارة</button>
    <a class="btn btn-ghost" href="<?= site_url('consultations') ?>">إلغاء</a>
    <span class="note"><?= icon('lock', 16) ?>يصلك الرد بعد مراجعته واعتماده من مستشار الشؤون القانونية</span>
</div>
<?= $this->endSection() ?>
