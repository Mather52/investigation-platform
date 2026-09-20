<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$tabs = ['users' => 'المستخدمون والصلاحيات', 'employees' => 'الموظفون', 'departments' => 'الإدارات والأقسام', 'settings' => 'مدد الإنذار'];
$roleName = array_column($roles, 'name_ar', 'code');
?>
<section class="card">
    <nav class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= site_url('admin/users?tab=' . $k) ?>"><?= $l ?></a><?php endforeach ?></nav>
    <div class="card-body">
    <?php if ($tab === 'users'): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>المستخدم</th><th>اسم الدخول</th><th>الأدوار</th><th>الحالة</th><th>آخر دخول</th><th></th></tr></thead>
            <tbody><?php foreach ($users as $u): $codes = array_filter(explode(',', (string) $u['role_codes'])); $locked = $u['locked_until'] && strtotime($u['locked_until']) > time(); ?>
                <tr>
                    <td><?= partial('partials/person', ['name' => $u['full_name'] ?? $u['username'], 'sub' => trim(($u['employee_no'] ?? '') . ' · ' . ($u['department_name'] ?? ''), ' ·')]) ?></td>
                    <td class="num" dir="ltr" style="text-align: right;"><?= esc($u['username']) ?></td>
                    <td><div class="row" style="gap: 4px;"><?php foreach ($codes as $c): ?><?= badge($roleName[$c] ?? $c, $c === 'admin' ? 'yellow' : 'teal') ?><?php endforeach ?></div></td>
                    <td><?= $u['is_active'] ? ($locked ? badge('مقفل مؤقتاً', 'red', true) : badge('نشط', 'green', true)) : badge('موقوف', 'gray', true) ?></td>
                    <td class="num"><?= fdate($u['last_login_at'], true) ?></td>
                    <td><button class="btn btn-sm" data-open="user<?= $u['id'] ?>">تعديل</button></td>
                </tr>
            <?php endforeach ?></tbody>
        </table></div>

        <h3 style="font-size: 17px;">إنشاء حساب</h3>
        <form method="post" action="<?= site_url('admin/users') ?>" class="stack" style="gap: 16px;">
            <?= csrf_field() ?>
            <div class="grid g3">
                <label class="field"><span class="lbl">الموظف <span class="req">*</span></span><select class="select" name="employee_id" required><option value="">اختر الموظف</option><?php foreach ($employees as $e): if ($e['user_id']) continue; ?><option value="<?= $e['id'] ?>" <?= old('employee_id') == $e['id'] ? 'selected' : '' ?>><?= esc($e['full_name'] . ' — ' . $e['employee_no']) ?></option><?php endforeach ?></select><span class="hint">إن لم يظهر الموظف أضفه من تبويب "الموظفون".</span></label>
                <label class="field"><span class="lbl">اسم الدخول <span class="req">*</span></span><input class="input" name="username" dir="ltr" value="<?= esc(old('username')) ?>" required></label>
                <label class="field"><span class="lbl">كلمة المرور <span class="req">*</span></span><input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required></label>
            </div>
            <div class="field"><span class="lbl">الأدوار <span class="req">*</span></span><div class="row"><?php foreach ($roles as $r): ?><label class="check" style="border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px;"><input type="checkbox" name="roles[]" value="<?= $r['id'] ?>" <?= $r['code'] === 'employee' ? 'checked' : '' ?>><span><?= esc($r['name_ar']) ?></span></label><?php endforeach ?></div></div>
            <div><button class="btn btn-primary"><?= icon('plus', 18) ?>إنشاء الحساب</button></div>
        </form>

    <?php elseif ($tab === 'employees'): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>الرقم الوظيفي</th><th>الاسم</th><th>المسمى</th><th>الإدارة</th><th>البريد</th><th>الجوال</th><th>حساب</th></tr></thead>
            <tbody><?php foreach ($employees as $e): ?>
                <tr><td class="num"><?= esc($e['employee_no']) ?></td><td><?= esc($e['full_name']) ?></td><td><?= esc($e['job_title'] ?? '—') ?></td><td><?= esc($e['department_name'] ?? '—') ?></td><td class="num"><?= esc($e['email'] ?? '—') ?></td><td class="num"><?= esc($e['mobile'] ?? '—') ?></td><td><?= $e['user_id'] ? badge('لديه حساب', 'green') : badge('بدون', 'gray') ?></td></tr>
            <?php endforeach ?></tbody>
        </table></div>
        <h3 style="font-size: 17px;">إضافة موظف</h3>
        <p class="hint">إلى حين الربط مع نظام الموارد البشرية تُدخل بيانات الموظفين يدوياً.</p>
        <form method="post" action="<?= site_url('admin/employees') ?>" class="grid g3">
            <?= csrf_field() ?>
            <label class="field"><span class="lbl">الرقم الوظيفي <span class="req">*</span></span><input class="input" name="employee_no" value="<?= esc(old('employee_no')) ?>" required></label>
            <label class="field"><span class="lbl">الاسم الكامل <span class="req">*</span></span><input class="input" name="full_name" value="<?= esc(old('full_name')) ?>" required></label>
            <label class="field"><span class="lbl">المسمى الوظيفي</span><input class="input" name="job_title" value="<?= esc(old('job_title')) ?>"></label>
            <label class="field"><span class="lbl">الإدارة <span class="req">*</span></span><select class="select" name="department_id" required><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= esc($d['name_ar']) ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="lbl">البريد الإلكتروني</span><input class="input" type="email" name="email" dir="ltr" value="<?= esc(old('email')) ?>"></label>
            <label class="field"><span class="lbl">الجوال</span><input class="input" name="mobile" dir="ltr" value="<?= esc(old('mobile')) ?>"></label>
            <div><button class="btn btn-primary"><?= icon('plus', 18) ?>إضافة</button></div>
        </form>

    <?php elseif ($tab === 'departments'): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>الإدارة / القسم</th><th>الرمز</th><th>عدد الموظفين</th></tr></thead>
            <tbody><?php foreach ($departments as $d): ?><tr><td><?= esc($d['name_ar']) ?></td><td class="num"><?= esc($d['code'] ?? '—') ?></td><td class="num"><?= (int) $d['emp_count'] ?></td></tr><?php endforeach ?></tbody>
        </table></div>
        <form method="post" action="<?= site_url('admin/departments') ?>" class="row" style="align-items: end;">
            <?= csrf_field() ?>
            <label class="field" style="flex: 1;"><span class="lbl">اسم الإدارة</span><input class="input" name="name_ar" required></label>
            <label class="field" style="width: 180px;"><span class="lbl">الرمز</span><input class="input" name="code" dir="ltr"></label>
            <button class="btn btn-primary"><?= icon('plus', 18) ?>إضافة</button>
        </form>

    <?php else: ?>
        <p class="muted">تُحسب حالة كل معاملة من تاريخ آخر إجراء عليها. تظهر الألوان في لوحة التحكم والقوائم، وتُرسل إشعارات الأصفر والأحمر آلياً.</p>
        <form method="post" action="<?= site_url('admin/settings') ?>" class="stack" style="gap: 16px;">
            <?= csrf_field() ?>
            <div class="table-wrap"><table>
                <thead><tr><th>القاعدة</th><th><?= badge('أخضر', 'green') ?> بعد (يوم)</th><th><?= badge('أصفر', 'yellow') ?> بعد (يوم)</th><th><?= badge('أحمر', 'red') ?> بعد (يوم)</th></tr></thead>
                <tbody><?php foreach ($settings as $s): ?>
                    <tr><td><b><?= $s['rule_code'] === 'new_case' ? 'معاملة جديدة لم يُتخذ عليها إجراء' : 'بعد آخر إجراء على المعاملة' ?></b></td>
                        <?php foreach (['green_days', 'yellow_days', 'red_days'] as $f): ?><td><input class="input num" style="width: 110px;" type="number" min="1" max="90" name="s[<?= esc($s['rule_code']) ?>][<?= $f ?>]" value="<?= (int) $s[$f] ?>" required></td><?php endforeach ?></tr>
                <?php endforeach ?></tbody>
            </table></div>
            <div><button class="btn btn-primary"><?= icon('save', 18) ?>حفظ المدد</button></div>
            <span class="hint">لتوليد إشعارات الإنذار يومياً جدوِل الأمر: <code dir="ltr">php spark alerts:check</code> في برنامج جدولة المهام.</span>
        </form>
    <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?php if ($tab === 'users'): foreach ($users as $u): $codes = array_filter(explode(',', (string) $u['role_codes'])); ?>
<dialog class="modal" id="user<?= $u['id'] ?>">
    <form method="post" action="<?= site_url('admin/users/' . $u['id']) ?>">
        <?= csrf_field() ?>
        <div><h2><?= esc($u['full_name'] ?? $u['username']) ?></h2><p class="num" dir="ltr" style="text-align: right;"><?= esc($u['username']) ?></p></div>
        <div class="field"><span class="lbl">الأدوار</span><div class="row"><?php foreach ($roles as $r): ?><label class="check"><input type="checkbox" name="roles[]" value="<?= $r['id'] ?>" <?= in_array($r['code'], $codes, true) ? 'checked' : '' ?>><span><?= esc($r['name_ar']) ?></span></label><?php endforeach ?></div></div>
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?>><span>الحساب نشط</span></label>
        <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?><label class="check"><input type="checkbox" name="unlock" value="1" checked><span>فك القفل المؤقت</span></label><?php endif ?>
        <label class="field"><span class="lbl">كلمة مرور جديدة</span><input class="input" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="اتركها فارغة للإبقاء على الحالية"></label>
        <div class="row"><button class="btn btn-primary"><?= icon('save', 18) ?>حفظ</button><button class="btn" data-close>إلغاء</button></div>
    </form>
</dialog>
<?php endforeach; endif ?>
<?= $this->endSection() ?>
