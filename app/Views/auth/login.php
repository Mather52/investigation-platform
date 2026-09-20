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
        <div class="brand"><div class="brand-logo" style="width: 56px; height: 56px;"><?= icon('shield', 30) ?></div><div class="brand-sub" style="font-size: 15px;">المدينة الطبية</div></div>
        <div>
            <h1>منصة أتمتة إجراءات التحقيق الإداري</h1>
            <p>إجراءات التحقيق في إطار آلي تحكمه الأنظمة واللوائح، من استلام المعاملة حتى صدور القرار.</p>
        </div>
        <div class="row brand-sub" style="font-size: 13px;"><?= icon('lock', 16) ?><span>اتصال آمن · إدارة الشؤون القانونية والالتزام · قسم التحقيق</span></div>
    </section>

    <section class="login-panel">
        <form class="login-card" method="post" action="<?= site_url('login') ?>" novalidate>
            <?= csrf_field() ?>
            <div><h2>تسجيل الدخول</h2><p class="muted">متاح لكافة موظفي المدينة الطبية</p></div>

            <?php if ($e = session()->getFlashdata('error')): ?><div class="alert alert-error"><?= esc($e) ?></div><?php endif ?>
            <?php if ($errs = session()->getFlashdata('errors')): ?><div class="alert alert-error"><ul class="flash-list"><?php foreach ($errs as $er): ?><li><?= esc($er) ?></li><?php endforeach ?></ul></div><?php endif ?>
            <?php if ($m = session()->getFlashdata('message')): ?><div class="alert alert-ok"><?= esc($m) ?></div><?php endif ?>

            <button type="button" class="btn btn-primary" disabled title="يُفعّل بعد اعتماد الربط مع النفاذ الوطني"><?= icon('shield', 20) ?>الدخول عبر النفاذ الوطني الموحد (قريباً)</button>
            <div class="or">أو بحساب المدينة الطبية</div>

            <label class="field"><span class="lbl">اسم المستخدم</span><input class="input" type="text" name="username" value="<?= esc(old('username')) ?>" placeholder="الرقم الوظيفي أو اسم المستخدم" autocomplete="username" required></label>
            <label class="field"><span class="lbl">كلمة المرور</span><input class="input" type="password" name="password" autocomplete="current-password" required></label>

            <div class="acks">
                <label class="check"><input type="checkbox" name="ack_confidentiality" value="1" <?= old('ack_confidentiality') ? 'checked' : '' ?>><span>أقر بالمحافظة على سرية المعلومات والبيانات المطلع عليها</span></label>
                <label class="check"><input type="checkbox" name="ack_accuracy" value="1" <?= old('ack_accuracy') ? 'checked' : '' ?>><span>أقر بصحة المعلومات التي أدخلها في المنصة</span></label>
            </div>

            <button type="submit" class="btn">دخول</button>
        </form>
    </section>
</div>
</body>
</html>
