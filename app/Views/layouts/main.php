<?php
use App\Libraries\Access;
use App\Libraries\ConsultationService;

$active  = $active ?? '';
$unread  = (int) db_connect()->table('notifications')->where('user_id', session('user_id'))->where('read_at', null)->countAllResults();
$consN   = ConsultationService::safeNavCount();
// [المفتاح، العنوان، الرابط، الأيقونة، الأدوار (فارغ = الكل)]
$nav = [
    ['dashboard',     'الرئيسية',               'dashboard',                 'home',    []],
    ['cases',         'المعاملات',              'cases',                     'folder',  []],
    ['new',           'إنشاء مخالفة / شكوى',    'cases/new',                 'plus',    []],
    ['referred',      'المعاملات المحالة',      'cases?view=referred',       'share',   ['legal', 'gm', 'head']],
    ['investigation', 'المعاملات قيد التحقيق',  'cases?view=investigation',  'search2', ['investigator', 'head', 'legal']],
    ['sessions',      'الجلسات',                'cases?view=sessions',       'video',   ['investigator', 'head', 'legal']],
    ['statements',    'الشهود والمختصون',       'cases?view=statements',     'users',   ['investigator', 'head', 'legal']],
    ['letters',       'المراسلات',              'cases?view=letters',        'mail',    ['investigator', 'head', 'legal']],
    ['memos',         'مذكرات التحقيق',         'cases?view=memos',          'file',    ['investigator', 'head', 'legal']],
    ['approvals',     'الاعتمادات',             'cases?view=approvals',      'checkc',  ['head', 'legal', 'gm']],
    ['recs',          'التوصيات',               'cases?view=execution',      'list',    ['legal', 'gm']],
    ['archive',       'الأرشيف',                'cases?view=archive',        'archive', ['head', 'legal', 'gm']],
    ['consultations', 'الاستشارات القانونية',   'consultations',             'msg',     []],
    ['reports',       'التقارير',               'reports',                   'chart',   ['investigator', 'head', 'legal', 'gm']],
    ['notifications', 'الإشعارات',              'notifications',             'bell',    []],
];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'منصة التحقيق') ?> — منصة التحقيق الإداري</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="القائمة الجانبية" inert>
        <div class="brand">
            <div class="brand-logo"><img src="<?= base_url('assets/img/emblem.png') ?>" alt="شعار مدينة الملك عبدالله الطبية"></div>
            <div><div class="brand-name">منصة التحقيق</div><div class="brand-sub">إدارة الشؤون القانونية والالتزام</div></div>
            <button type="button" class="sidebar-close" data-nav-close aria-label="إغلاق القائمة"><?= icon('x', 20) ?></button>
        </div>
        <nav class="nav" aria-label="القائمة الرئيسية">
            <?php
            // عنوان المجموعة يظهر قبل أول عنصر مرئي منها
            $groupOf = static fn (string $key) => match ($key) {
                'dashboard', 'cases', 'new' => 'عام',
                'consultations', 'reports', 'notifications' => 'الخدمات',
                default => 'مراحل المعاملة',
            };
            $group = null;
            foreach ($nav as [$key, $label, $route, $ic, $roles]): if (! Access::hasAny($roles)) continue;
                if ($groupOf($key) !== $group): $group = $groupOf($key); ?><div class="nav-group"><?= esc($group) ?></div><?php endif;
                $n = ['notifications' => $unread, 'consultations' => $consN][$key] ?? 0; ?>
                <a href="<?= site_url($route) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><span class="nav-ico"><?= icon($ic, 18) ?></span><span><?= esc($label) ?></span><?php if ($n > 0): ?><span class="count num"><?= $n ?></span><?php endif ?></a>
            <?php endforeach ?>
            <?php if (Access::isAdmin()): ?>
                <div class="nav-group">الإدارة</div>
                <a href="<?= site_url('admin/users') ?>" class="<?= $active === 'admin' ? 'is-active' : '' ?>"><span class="nav-ico"><?= icon('gear', 18) ?></span><span>المستخدمون والإعدادات</span></a>
            <?php endif ?>
        </nav>
        <div class="user-box">
            <div class="avatar"><?= esc(initial(session('full_name'))) ?></div>
            <div class="user-meta">
                <div class="user-name"><?= esc(session('full_name')) ?></div>
                <div class="user-role" title="<?= esc(implode('، ', (array) session('role_names'))) ?>"><?= esc(implode('، ', (array) session('role_names'))) ?></div>
            </div>
            <a class="logout" href="<?= site_url('logout') ?>" title="تسجيل الخروج" aria-label="تسجيل الخروج"><?= icon('logout', 20) ?></a>
        </div>
    </aside>

    <div class="nav-backdrop" data-nav-close></div>

    <div class="content">
        <header class="page-header">
            <div class="head-start">
                <button type="button" class="menu-btn no-print" data-nav-toggle aria-controls="sidebar" aria-expanded="false" aria-label="فتح القائمة"><?= icon('menu', 22) ?><?php if ($unread + $consN > 0): ?><span class="dot"></span><?php endif ?></button>
                <?php if (! empty($bare)): ?>
                <div class="top-brand"><span class="brand-logo sm"><img src="<?= base_url('assets/img/emblem.png') ?>" alt=""></span><div><strong>منصة التحقيق</strong><small>مدينة الملك عبدالله الطبية · إدارة الشؤون القانونية والالتزام</small></div></div>
                <?php else: ?>
                <div>
                <div class="crumbs">
                    <a href="<?= site_url('dashboard') ?>"><?= icon('home', 16) ?>الرئيسية</a>
                    <?php $cr = $crumbs ?? []; foreach ($cr as $i => $c): [$ct, $cu] = is_array($c) ? $c : [$c, null]; ?>
                        <span class="sep">‹</span>
                        <?php if ($cu && $i < count($cr) - 1): ?><a href="<?= site_url($cu) ?>"><?= esc($ct) ?></a><?php else: ?><span class="current"><?= esc($ct) ?></span><?php endif ?>
                    <?php endforeach ?>
                </div>
                <h1><?= esc($title ?? '') ?></h1>
                <?php if (! empty($subtitle)): ?><div class="subtitle"><?= esc($subtitle) ?></div><?php endif ?>
                </div>
                <?php endif ?>
            </div>
            <div class="header-tools no-print">
                <form class="search" method="get" action="<?= site_url('cases') ?>" role="search">
                    <?= icon('search2', 18) ?>
                    <input type="search" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="بحث برقم المعاملة أو الموضوع…" aria-label="بحث في المعاملات">
                </form>
                <a class="bell" href="<?= site_url('notifications') ?>" aria-label="الإشعارات"><?= icon('bell', 20) ?><?php if ($unread > 0): ?><span class="dot"></span><?php endif ?></a>
            </div>
        </header>

        <main class="page-body">
            <?php if ($m = session()->getFlashdata('message')): ?><div class="alert alert-ok"><?= icon('checkc', 18) ?><span><?= esc($m) ?></span></div><?php endif ?>
            <?php if ($e = session()->getFlashdata('error')): ?><div class="alert alert-error"><?= icon('alert', 18) ?><span><?= esc($e) ?></span></div><?php endif ?>
            <?php if ($errs = session()->getFlashdata('errors')): ?><div class="alert alert-error"><?= icon('alert', 18) ?><ul class="flash-list"><?php foreach ($errs as $er): ?><li><?= esc($er) ?></li><?php endforeach ?></ul></div><?php endif ?>
            <?= $this->renderSection('content') ?>
        </main>
        <?= $this->renderSection('actions') ?>
    </div>
</div>
<?= $this->renderSection('modals') ?>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
