<?php

use App\Filters\AuthFilter;
use App\Filters\RoleFilter;
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// الدخول والخروج
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->get('logout', 'Auth::logout');

$routes->group('', ['filter' => AuthFilter::class], static function (RouteCollection $routes): void {
    $routes->get('/', 'Dashboard::index');
    $routes->get('dashboard', 'Dashboard::index');

    // المعاملات
    $routes->get('cases', 'Cases::index');
    $routes->get('cases/new', 'Cases::create');
    $routes->post('cases', 'Cases::store');
    $routes->get('cases/(:num)', 'Cases::show/$1');
    $routes->post('cases/(:num)/attachments', 'Cases::upload/$1');
    $routes->get('attachments/(:num)', 'Cases::download/$1');
    $routes->post('cases/(:num)/start', 'Cases::start/$1');
    $routes->post('cases/(:num)/submit', 'Cases::submitDraft/$1');
    $routes->get('cases/(:num)/log', 'Cases::log/$1');

    // الإحالة
    $routes->get('cases/(:num)/referral', 'Referrals::show/$1');
    $routes->post('cases/(:num)/referral', 'Referrals::act/$1');

    // الدعوات والحضور
    $routes->get('cases/(:num)/invitations/new', 'Invitations::create/$1');
    $routes->post('cases/(:num)/invitations', 'Invitations::store/$1');
    $routes->get('cases/(:num)/attendance', 'Invitations::attendance/$1');
    $routes->post('invitations/(:num)/attendance', 'Invitations::setAttendance/$1');
    $routes->post('invitations/(:num)/absence', 'Invitations::applyAbsence/$1');
    $routes->get('invitations/(:num)', 'Invitations::view/$1');

    // الجلسات
    $routes->post('invitations/(:num)/session', 'Sessions::startFromInvitation/$1');
    $routes->get('sessions/(:num)', 'Sessions::show/$1');
    $routes->post('sessions/(:num)/status', 'Sessions::status/$1');
    $routes->post('sessions/(:num)/minutes', 'Sessions::minutes/$1');
    $routes->post('sessions/(:num)/messages', 'Sessions::message/$1');

    // محاور التحقيق
    $routes->get('cases/(:num)/topics', 'Topics::index/$1');
    $routes->post('cases/(:num)/topics', 'Topics::store/$1');
    $routes->post('topics/(:num)', 'Topics::save/$1');

    // الشهود والمختصون
    $routes->get('cases/(:num)/statements', 'Statements::index/$1');
    $routes->post('cases/(:num)/statements', 'Statements::store/$1');
    $routes->post('statements/(:num)/respond', 'Statements::respond/$1');

    // المراسلات
    $routes->get('cases/(:num)/letters', 'Letters::index/$1');
    $routes->post('cases/(:num)/letters', 'Letters::store/$1');
    $routes->post('letters/(:num)/reply', 'Letters::reply/$1');

    // المذكرة والاعتماد
    $routes->get('cases/(:num)/memo', 'Memos::edit/$1');
    $routes->post('cases/(:num)/memo', 'Memos::save/$1');
    $routes->get('cases/(:num)/memo/preview', 'Memos::preview/$1');
    $routes->get('cases/(:num)/review', 'Approvals::review/$1');
    $routes->post('cases/(:num)/review', 'Approvals::decide/$1');

    // التنفيذ والأرشفة
    $routes->get('cases/(:num)/execution', 'Execution::index/$1');
    $routes->post('cases/(:num)/recommendations', 'Execution::storeRecommendation/$1');
    $routes->post('recommendations/(:num)', 'Execution::updateRecommendation/$1');
    $routes->post('cases/(:num)/archive', 'Execution::archive/$1');

    // الاستشارات القانونية (الصلاحيات تُفحص داخل الكنترولر)
    $routes->get('consultations', 'Consultations::index');
    $routes->get('consultations/new', 'Consultations::create');
    $routes->get('consultations/similar', 'Consultations::similar');
    $routes->post('consultations', 'Consultations::store');
    $routes->get('consultations/(:num)', 'Consultations::show/$1');
    $routes->post('consultations/(:num)/assign', 'Consultations::assign/$1');
    $routes->post('consultations/(:num)/answer', 'Consultations::answer/$1');
    $routes->post('consultations/(:num)/close', 'Consultations::close/$1');

    // الإشعارات والتقارير
    $routes->get('notifications', 'Notifications::index');
    $routes->get('notifications/(:num)', 'Notifications::open/$1');
    $routes->post('notifications/read-all', 'Notifications::readAll');
    $routes->get('reports', 'Reports::index', ['filter' => RoleFilter::class . ':investigator,head,legal,gm']);

    // الإدارة (مدير النظام فقط)
    $routes->group('admin', ['filter' => RoleFilter::class . ':admin'], static function (RouteCollection $routes): void {
        $routes->get('users', 'Admin\Users::index');
        $routes->post('users', 'Admin\Users::store');
        $routes->post('users/(:num)', 'Admin\Users::update/$1');
        $routes->post('employees', 'Admin\Users::storeEmployee');
        $routes->post('departments', 'Admin\Users::storeDepartment');
        $routes->post('settings', 'Admin\Users::settings');
    });
});
