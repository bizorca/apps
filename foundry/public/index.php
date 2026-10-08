<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

// Security headers: the original had none in PHP and only Options -Indexes in
// .htaccess, which this server ignores. No CSP: the pages load Tailwind and
// Alpine from CDNs and use inline scripts.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

\Bizorca\Consulting\Auth\Session::start();

// Router
$router = new \Bizorca\Consulting\Core\Router();

// ── Public routes ──────────────────────────────────────────────────────────

$home = new \Bizorca\Consulting\Controllers\HomeController();
$router->get('/', fn() => $home->index());

// Application (public intake form)
$apply = new \Bizorca\Consulting\Controllers\ApplicationController();
$router->get('/apply',  fn() => $apply->show());
$router->post('/apply', fn() => $apply->submit());
$router->get('/apply/thank-you', fn() => $apply->thankYou());

// Sign-in is the shared tools account. The original's SSO endpoints, and the
// "Client Login" link on the home page (/login, which had no route at all),
// all land on the shared sign-in and come back to the client dashboard.
$toLogin = fn() => redirect('/account/login.php?next=' . rawurlencode(\Bizorca\Consulting\Core\Url::to('/dashboard')));
$router->get('/login',         $toLogin);
$router->get('/sso/redirect',  $toLogin);
$router->get('/sso/callback',  $toLogin);
// Sign-out: a GET (old links) gets the shared confirm page; the dashboards'
// POST form signs out of every tool, as their button always did here.
$router->get('/logout',  fn() => redirect('/account/logout.php'));
$router->post('/logout', function (): void {
    \Bizorca\Consulting\Auth\CSRF::verify();
    \Bizorca\Consulting\Auth\Session::logout();
    redirect('/');
});

// ── Authenticated client routes ────────────────────────────────────────────

$dashboard = new \Bizorca\Consulting\Controllers\DashboardController();
$router->get('/dashboard', fn() => $dashboard->index());

$engagement = new \Bizorca\Consulting\Controllers\EngagementController();
$router->get('/engagements/{id}',              fn($id) => $engagement->show($id));
$router->get('/engagements/{id}/scope',        fn($id) => $engagement->scope($id));
$router->post('/engagements/{id}/scope/accept',fn($id) => $engagement->acceptScope($id));

$changes = new \Bizorca\Consulting\Controllers\ChangeRequestController();
$router->get('/engagements/{id}/changes',      fn($id) => $changes->index($id));
$router->post('/engagements/{id}/changes',     fn($id) => $changes->submit($id));

$cards = new \Bizorca\Consulting\Controllers\CardController();
$router->post('/cards/{id}/comment',   fn($id) => $cards->comment($id));
$router->post('/cards/{id}/step/{sid}',fn($id, $sid) => $cards->toggleStep($id, $sid));
$router->post('/boards/{id}/reorder',  fn($id) => $cards->reorder($id));

// ── Admin routes ───────────────────────────────────────────────────────────

$admin = new \Bizorca\Consulting\Controllers\AdminController();
$router->get('/admin',                              fn() => $admin->dashboard());
$router->get('/admin/applications',                 fn() => $admin->applications());
$router->get('/admin/applications/{id}',            fn($id) => $admin->applicationDetail($id));
$router->post('/admin/applications/{id}/accept',    fn($id) => $admin->acceptApplication($id));
$router->post('/admin/applications/{id}/decline',   fn($id) => $admin->declineApplication($id));
$router->get('/admin/engagements',                  fn() => $admin->engagements());
$router->get('/admin/engagements/{id}',             fn($id) => $admin->engagementDetail($id));
$router->post('/admin/engagements/{id}/board',      fn($id) => $admin->createBoard($id));
$router->post('/admin/engagements/{id}/scope',      fn($id) => $admin->saveScope($id));
$router->post('/admin/engagements/{id}/lock-scope', fn($id) => $admin->lockScope($id));
$router->post('/admin/cards',                       fn() => $admin->createCard());
$router->post('/admin/cards/{id}',                  fn($id) => $admin->updateCard($id));
$router->post('/admin/cards/{id}/move',             fn($id) => $admin->moveCard($id));
$router->post('/admin/changes/{id}/approve',        fn($id) => $admin->approveChange($id));
$router->post('/admin/changes/{id}/decline',        fn($id) => $admin->declineChange($id));

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD']);
