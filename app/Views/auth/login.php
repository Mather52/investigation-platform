<?php helper('ui'); ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول — منصة التحقيق الإداري</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="login">
    <section class="login-hero">
        <div class="brand" style="border: none; padding: 0;"><div class="brand-logo" style="width: 56px; height: 56px;"><?= icon('shield', 30) ?></div><div><div class="brand-name">منصة التحقيق</div><div class="brand-sub" style="font-size: 13px;">المدينة الطبية · إدارة الشؤون القانونية والالتزام</div></div></div>
        <div>
            <h1>منصة أتمتة إجراءات التحقيق الإداري</h1>
            <p>إجراءات التحقيق في إطار آلي تحكمه الأنظمة واللوائح، من استلام المعاملة حتى صدور القرار.</p>
            <ul class="login-points">
                <li><span class="pt"><?= icon('folder', 18) ?></span>ملف واحد لكل معاملة بكل مستنداتها وإجراءاتها</li>
                <li><span class="pt"><?= icon('clock', 18) ?></span>متابعة المدد النظامية وتنبيهات قبل التأخر</li>
                <li><span class="pt"><?= icon('lock', 18) ?></span>سرية المعلومات وصلاحيات حسب الدور</li>
            </ul>
        </div>
        <div class="login-foot"><?= icon('lock', 16) ?><span>اتصال آمن · قسم التحقيق</span></div>
    </section>

    <section class="login-panel">
        <form class="login-card" method="post" action="<?= site_url('login') ?>" novalidate>
            <?= csrf_field() ?>
            <div><h2>تسجيل الدخول</h2><p class="muted">متاح لكافة موظفي المدينة الطبية</p></div>

            <?php if ($e = session()->getFlashdata('error')): ?><div class="alert alert-error"><?= icon('alert', 18) ?><span><?= esc($e) ?></span></div><?php endif ?>
            <?php if ($errs = session()->getFlashdata('errors')): ?><div class="alert alert-error"><ul class="flash-list"><?php foreach ($errs as $er): ?><li><?= esc($er) ?></li><?php endforeach ?></ul></div><?php endif ?>
            <?php if ($m = session()->getFlashdata('message')): ?><div class="alert alert-ok"><?= esc($m) ?></div><?php endif ?>

            <button type="button" class="btn btn-nafath" disabled title="يُفعّل بعد اعتماد الربط مع النفاذ الوطني الموحد">
                <span class="nafath-mark"><?= icon('shield', 18) ?>نفاذ</span>
                <span class="nafath-label">الدخول عبر النفاذ الوطني الموحد</span>
                <span class="nafath-soon">قريباً</span>
            </button>
            <div class="or">أو بحساب المدينة الطبية</div>

            <label class="field"><span class="lbl">اسم المستخدم</span><input class="input" type="text" name="username" value="<?= esc(old('username')) ?>" placeholder="الرقم الوظيفي أو اسم المستخدم" autocomplete="username" required></label>
            <div class="field"><label class="lbl" for="password">كلمة المرور</label>
                <div class="pw-wrap"><input class="input" id="password" type="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="إظهار كلمة المرور"><?= icon('eye', 18) ?></button></div></div>

            <div class="acks">
                <label class="check"><input type="checkbox" name="ack_confidentiality" value="1" <?= old('ack_confidentiality') ? 'checked' : '' ?>><span>أقر بالمحافظة على سرية المعلومات والبيانات المطلع عليها</span></label>
                <label class="check"><input type="checkbox" name="ack_accuracy" value="1" <?= old('ack_accuracy') ? 'checked' : '' ?>><span>أقر بصحة المعلومات التي أدخلها في المنصة</span></label>
            </div>

            <button type="submit" class="btn btn-primary">تسجيل الدخول</button>
        </form>
    </section>
</div>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
