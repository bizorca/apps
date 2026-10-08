<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Order matters: config, then tenant resolution, then session guard, then
 * routing. Nothing that touches tenant data may run before the scope exists.
 */

require __DIR__ . '/_bootstrap.php';

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Controllers\AuthController;
use Bizorca\Pilotage\Controllers\ClientOrgController;
use Bizorca\Pilotage\Controllers\DashboardController;
use Bizorca\Pilotage\Controllers\EngagementController;
use Bizorca\Pilotage\Controllers\PlaybookController;
use Bizorca\Pilotage\Controllers\SessionController;
use Bizorca\Pilotage\Controllers\DocumentController;
use Bizorca\Pilotage\Controllers\FileController;
use Bizorca\Pilotage\Controllers\FirmController;
use Bizorca\Pilotage\Controllers\IntakeController;
use Bizorca\Pilotage\Controllers\MarketingController;
use Bizorca\Pilotage\Controllers\MessageController;
use Bizorca\Pilotage\Controllers\ScorecardController;
use Bizorca\Pilotage\Controllers\TagController;
use Bizorca\Pilotage\Controllers\TaskController;
use Bizorca\Pilotage\Controllers\NotificationController;
use Bizorca\Pilotage\Controllers\AdminController;
use Bizorca\Pilotage\Controllers\CalendarController;
use Bizorca\Pilotage\Controllers\CohortController;
use Bizorca\Pilotage\Controllers\BillingController;
use Bizorca\Pilotage\Controllers\ReportController;
use Bizorca\Pilotage\Controllers\WorksheetController;
use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Csp;
use Bizorca\Pilotage\Core\Heartbeat;
use Bizorca\Pilotage\Core\Router;
use Bizorca\Pilotage\Services\Entitlements;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\TenantNotFoundException;
use Bizorca\Pilotage\Core\TenantScopeException;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;

Config::load(require PL_ROOT . '/config/app.php');

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

/**
 * On tools.bizorca.com there is one session for every tool (cookie
 * `tools_session`, path /, configured by the shared core) and it is LAZY:
 * resumed when the browser already has one, never minted for an anonymous
 * page view. A form that needs a CSRF token creates it (Csrf::token()), and
 * signing in creates it (the shared /account pages). Pilotage's own session
 * state lives under pl_-prefixed keys inside it, per firm — see Auth\Session.
 */
tl_session();

$host   = (string) ($_SERVER['HTTP_HOST'] ?? '');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

/**
 * Routing on this server is by query string: nginx sends PHP straight to
 * PHP-FPM and cannot fall back to index.php for /pilotage/x/y. The route is
 * ?r=/path, and a firm is the route prefix /f/<slug>. Everything below the
 * prefix is the original route table unchanged. pl_route() builds these URLs;
 * PL_CLEAN_URLS switches every link to /pilotage/f/<slug>/path in one place
 * once Cloudways adds a try_files fallback.
 */
[$tenantSlug, $path] = pl_parse_route(pl_current_route());

// Permission qualifiers must be registered before any Policy check runs.
// Anything unregistered denies, so forgetting this locks the app down rather
// than opening it up — but it locks down features that should work.
Qualifiers::register();

$auth = new AuthController();
$clients = new ClientOrgController();
$engagementsCtl = new EngagementController();
$dashboard = new DashboardController();
$firm = new FirmController();
$intake = new IntakeController();
$marketing = new MarketingController();
$playbooks = new PlaybookController();
$sessions = new SessionController();
$tasks = new TaskController();
$tags = new TagController();
$worksheets = new WorksheetController();
$notifications = new NotificationController();
$reports = new ReportController();
$billing = new BillingController();
$admin = new AdminController();
$calendar = new CalendarController();
$cohorts = new CohortController();
$documents = new DocumentController();
$files = new FileController();
$messages = new MessageController();
$scoreboard = new ScorecardController();

// M4 can answer the 'steps' agenda block; later modules register their own.
\Bizorca\Pilotage\Services\AgendaBuilder::registerPlaybookProvider();
\Bizorca\Pilotage\Services\AccountabilityLoop::registerAgendaProvider();
\Bizorca\Pilotage\Services\Scorecard::registerAgendaProviders();

$router = new Router();

// --- authentication -------------------------------------------------------
/**
 * Who you are is the shared Bizorca Tools account (/account/login.php etc.).
 * GET /login is the firm's own step after that: membership check, then the
 * TOTP challenge or enrolment, then a Pilotage session. See AuthController.
 *
 * The per-firm password form, the emailed sign-in link and per-firm password
 * reset are gone with the port; their old addresses forward to the shared
 * account pages so a bookmarked or emailed link still lands somewhere useful.
 */
$router->get('/login',            static fn (): string => $auth->showLogin());
$router->get('/login/2fa',        static fn (): string => $auth->showTwoFactorChallenge());
$router->post('/login/2fa',       static fn (): string => $auth->verifyTwoFactor());
$router->post('/logout',          static fn (): string => $auth->logout());

$router->get('/auth/link/{token}',      static fn (): string => redirect(url('/login')));
$router->get('/password/forgot',        static fn (): string => redirect('/account/forgot.php'));
$router->get('/password/reset/{token}', static fn (): string => redirect('/account/forgot.php'));

// --- invitations ----------------------------------------------------------
$router->get('/invite/{token}',  static fn (array $p): string => $auth->showInvitation($p));
$router->post('/invite/{token}', static fn (array $p): string => $auth->acceptInvitation($p));

// --- two-factor enrolment -------------------------------------------------
$router->get('/2fa/setup',  static fn (): string => $auth->showTwoFactorSetup());
$router->post('/2fa/setup', static fn (): string => $auth->confirmTwoFactorSetup());

// --- client organizations (M3) -------------------------------------------
$router->get('/clients',                   static fn (): string => $clients->index());
$router->get('/clients/new',               static fn (): string => $clients->create());
$router->post('/clients/new',              static fn (): string => $clients->store());
$router->get('/clients/{id}',              static fn (array $p): string => $clients->show($p));
$router->get('/clients/{id}/edit',         static fn (array $p): string => $clients->edit($p));
$router->post('/clients/{id}/edit',        static fn (array $p): string => $clients->update($p));
$router->post('/clients/{id}/convert',     static fn (array $p): string => $clients->convert($p));
$router->post('/clients/{id}/contacts',    static fn (array $p): string => $clients->addContact($p));

// --- playbooks (M4) -------------------------------------------------------
$router->get('/playbooks',                 static fn (): string => $playbooks->index());
$router->post('/playbooks',                static fn (): string => $playbooks->store());
$router->post('/playbooks/starter',        static fn (): string => $playbooks->installStarter());
$router->get('/playbooks/{id}',            static fn (array $p): string => $playbooks->show($p));
$router->post('/playbooks/{id}/phases',    static fn (array $p): string => $playbooks->addPhase($p));
$router->post('/playbooks/{id}/steps',     static fn (array $p): string => $playbooks->addStep($p));
$router->post('/playbooks/{id}/import',    static fn (array $p): string => $playbooks->import($p));
$router->post('/playbooks/{id}/revise',    static fn (array $p): string => $playbooks->revise($p));
$router->post('/playbooks/{id}/publish',   static fn (array $p): string => $playbooks->publish($p));

// --- engagements running a playbook ---------------------------------------
$router->get('/engagements/{id}/journey',      static fn (array $p): string => $playbooks->journey($p));
$router->post('/engagements/{id}/apply',       static fn (array $p): string => $playbooks->apply($p));
$router->post('/engagements/{id}/steps/{step}', static fn (array $p): string => $playbooks->stepAction($p));

// --- sessions (M5) --------------------------------------------------------
$router->get('/engagements/{id}/sessions',  static fn (array $p): string => $sessions->index($p));
$router->post('/engagements/{id}/sessions', static fn (array $p): string => $sessions->schedule($p));
// The .ics route MUST precede the bare one: {id} matches [^/]+, so
// /sessions/12.ics would otherwise match {id}="12.ics", and (int) casting
// silently turns that into 12 — serving the runner instead of a calendar file.
$router->get('/sessions/{id}.ics',          static fn (array $p): string => $sessions->ics($p));
$router->get('/sessions/{id}',              static fn (array $p): string => $sessions->show($p));
$router->post('/sessions/{id}',             static fn (array $p): string => $sessions->act($p));

// --- the heartbeat --------------------------------------------------------
/**
 * Background work runs here rather than under cron. A page load that finds work
 * due fires a detached request at this endpoint and hangs up; this request does
 * the work with its own execution budget.
 *
 * No session and no CSRF: the caller is our own server, or an uptime monitor
 * pinging it to keep a quiet site alive. The token is the authentication, and
 * it authorises running the tick and nothing else.
 */
$router->get('/_tick/{token}/{mode}', static function (array $p): string {
    // FIRST, before anything slow. The trigger hangs up after 250ms and this
    // request must not die with it.
    ignore_user_abort(true);

    if (!Heartbeat::verifyToken((string) ($p['token'] ?? ''))) {
        http_response_code(404);
        return '';
    }

    $mode = (string) ($p['mode'] ?? '');

    if (!in_array($mode, \Bizorca\Pilotage\Services\Tick::MODES, true)) {
        http_response_code(404);
        return '';
    }

    // An external pinger arrives without having claimed anything, so claim
    // here too. Our own trigger already holds it and will not double-claim,
    // because the claim window has not elapsed.
    if (!Heartbeat::claimedRecently($mode)) {
        if (!Heartbeat::claim($mode)) {
            header('Content-Type: text/plain');
            return "not due\n";
        }
    }

    @set_time_limit(300);

    $lines = [];

    \Bizorca\Pilotage\Services\Tick::run($mode, static function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    header('Content-Type: text/plain');
    header('Cache-Control: no-store');

    return implode("\n", $lines) . "\n";
});

// --- cohorts (Phase 3) ----------------------------------------------------
// show() is reachable from both sides; Cohorts::visibleTo decides, and a
// non-member gets a 404 rather than a 403 — whether a given cohort exists is
// itself something a client should not be able to probe.
$router->get('/cohorts',            static fn (): string => $cohorts->index());
$router->post('/cohorts',           static fn (): string => $cohorts->store());
$router->get('/cohorts/{id}',       static fn (array $p): string => $cohorts->show($p));
$router->post('/cohorts/{id}',      static fn (array $p): string => $cohorts->act($p));

// --- calendar sync (Phase 3) ----------------------------------------------
// The callback is a GET because that is how OAuth redirects work; it is
// protected by the single-use state nonce rather than by CSRF, since the
// browser arrives from the provider with no token of ours.
$router->get('/calendar',                      static fn (): string => $calendar->index());
$router->post('/calendar/connect/{provider}',  static fn (array $p): string => $calendar->connect($p));
// ONE registered redirect URI for every firm, at the apex. Providers match it
// character for character and every firm has its own subdomain, so per-tenant
// URIs would mean registering one per customer. The `state` nonce carries the
// tenant, so this route needs no tenant of its own — which is also why state is
// a database row and not a session value: a cookie scoped to a subdomain is not
// readable here.
$router->get('/calendar/callback/{provider}',  static fn (array $p): string => $calendar->callback($p));
$router->post('/calendar/disconnect',          static fn (): string => $calendar->disconnect());
$router->post('/calendar/sync',                static fn (): string => $calendar->syncNow());

// --- administration and compliance (M14) ----------------------------------
$router->get('/firm/audit',                 static fn (): string => $admin->audit());
$router->get('/firm/retention',             static fn (): string => $admin->retention());
$router->post('/firm/retention',            static fn (): string => $admin->saveRetention());
$router->post('/firm/retention/keep',       static fn (): string => $admin->keepEngagement());
$router->get('/firm/erasure',               static fn (): string => $admin->erasure());
$router->post('/firm/erasure',              static fn (): string => $admin->erasureAct());
$router->post('/engagements/{id}/archive',  static fn (array $p): string => $admin->archive($p));
$router->post('/engagements/{id}/agreement',static fn (array $p): string => $admin->agreement($p));

// --- billing (M13) --------------------------------------------------------
// The webhook takes no session and no CSRF: it is Stripe calling, not a
// browser. Its signature is its authentication.
$router->post('/stripe/webhook',            static fn (): string => $billing->webhook());
$router->get('/billing',                    static fn (): string => $billing->index());
$router->post('/billing/subscribe',         static fn (): string => $billing->subscribe());
$router->post('/billing/portal',            static fn (): string => $billing->portal());

// --- reporting (M12) ------------------------------------------------------
// .csv before {id}/report so the extension is not swallowed by the path.
$router->get('/firm/dashboard',                     static fn (): string => $reports->firm());
$router->get('/engagements/{id}/report.csv',        static fn (array $p): string => $reports->engagementCsv($p));
$router->get('/engagements/{id}/report',            static fn (array $p): string => $reports->engagement($p));
$router->get('/engagements/{id}/health',            static fn (array $p): string => $reports->health($p));

// --- notifications (M11) --------------------------------------------------
$router->get('/notifications',              static fn (): string => $notifications->index());
$router->post('/notifications/read',        static fn (): string => $notifications->markAllRead());
$router->post('/notifications/{id}',        static fn (array $p): string => $notifications->open($p));
$router->get('/settings/notifications',     static fn (): string => $notifications->preferences());
$router->post('/settings/notifications',    static fn (): string => $notifications->savePreferences());
$router->post('/firm/digest',               static fn (): string => $firm->updateDigest());
$router->get('/firm/email-wording',         static fn (): string => $notifications->templates());
$router->post('/firm/email-wording',        static fn (): string => $notifications->saveTemplate());

// Unsubscribe is reachable without a session — that is the point of it. GET
// only ever shows a confirmation; the POST is what changes anything, because
// mail clients and scanners prefetch links and a prefetched GET would silence
// people who never clicked.
$router->get('/unsubscribe/{token}',        static fn (array $p): string => $notifications->unsubscribeConfirm($p));
$router->post('/unsubscribe/{token}',       static fn (array $p): string => $notifications->unsubscribeApply($p));

// --- worksheets (M10) -----------------------------------------------------
// Specific paths precede /worksheets/{id}: {id} matches [^/]+, so
// /worksheets/fill/3 would otherwise bind {id}="fill".
$router->get('/worksheets',                     static fn (): string => $worksheets->index());
$router->post('/worksheets',                    static fn (): string => $worksheets->store());
$router->get('/worksheets/fill/{id}',           static fn (array $p): string => $worksheets->fill($p));
$router->post('/worksheets/fill/{id}',          static fn (array $p): string => $worksheets->save($p));
$router->get('/worksheets/done/{id}',           static fn (array $p): string => $worksheets->done($p));
$router->get('/worksheets/results/{id}',        static fn (array $p): string => $worksheets->results($p));
$router->get('/worksheets/export/{id}',         static fn (array $p): string => $worksheets->export($p));
$router->get('/worksheets/{id}',                static fn (array $p): string => $worksheets->show($p));
$router->post('/worksheets/{id}',               static fn (array $p): string => $worksheets->build($p));
$router->get('/engagements/{id}/worksheets',    static fn (array $p): string => $worksheets->forEngagement($p));
$router->post('/engagements/{id}/worksheets',   static fn (array $p): string => $worksheets->assign($p));

// --- tags -----------------------------------------------------------------
$router->get('/tags',           static fn (): string => $tags->index());
$router->get('/tags/{slug}',    static fn (array $p): string => $tags->show($p));
$router->post('/tags/{slug}',   static fn (array $p): string => $tags->act($p));

// --- tasks (M6) -----------------------------------------------------------
$router->get('/tasks',                    static fn (): string => $tasks->mine());
$router->get('/engagements/{id}/tasks',   static fn (array $p): string => $tasks->forEngagement($p));
$router->post('/engagements/{id}/tasks',  static fn (array $p): string => $tasks->store($p));
$router->get('/tasks/{id}',               static fn (array $p): string => $tasks->show($p));
$router->post('/tasks/{id}',              static fn (array $p): string => $tasks->act($p));

// --- documents (M7) -------------------------------------------------------
// Specific routes precede {id} ones: {id} matches [^/]+, and (int) casting
// would silently swallow "12/download" style mismatches.
$router->get('/library',                            static fn (): string => $documents->library());
$router->get('/engagements/{id}/documents',         static fn (array $p): string => $documents->forEngagement($p));
$router->post('/engagements/{id}/documents',        static fn (array $p): string => $documents->upload($p));
$router->post('/engagements/{id}/document-requests', static fn (array $p): string => $documents->createRequest($p));
$router->get('/documents/{id}/download/{version}',  static fn (array $p): string => $files->download($p));
$router->get('/documents/{id}/download',            static fn (array $p): string => $files->download($p));
$router->get('/documents/{id}',                     static fn (array $p): string => $documents->show($p));
$router->post('/documents/{id}',                    static fn (array $p): string => $documents->act($p));

// Share links carry their own authorisation and take no session.
$router->get('/shared/{token}',                     static fn (array $p): string => $files->shared($p));

// --- public intake (FR-3.6) -----------------------------------------------
// Unauthenticated. Works on a tenant subdomain and on the bare apex, where it
// routes to the house firm from config.
$router->get('/apply',            static fn (): string => $intake->show());
$router->post('/apply',           static fn (): string => $intake->submit());
$router->get('/enquiries',        static fn (): string => $intake->queue());
$router->post('/enquiries/{id}',  static fn (array $p): string => $intake->act($p));

// --- firm settings (M1) ---------------------------------------------------
$router->get('/firm',                  static fn (): string => $firm->settings());
$router->post('/firm/branding',        static fn (): string => $firm->updateBranding());
$router->post('/firm/intake',          static fn (): string => $firm->updateIntake());
$router->post('/firm/staff',           static fn (): string => $firm->inviteStaff());
$router->post('/firm/staff/disable',   static fn (): string => $firm->disableStaff());
$router->get('/firm/export',           static fn (): string => $firm->export());
$router->post('/firm/onboard',         static fn (): string => $firm->onboard());

// --- engagements + scope (M4B) --------------------------------------------
$router->post('/clients/{id}/engagements',   static fn (array $p): string => $engagementsCtl->store($p));
$router->get('/engagements/{id}/scope',      static fn (array $p): string => $engagementsCtl->scope($p));
$router->post('/engagements/{id}/scope',     static fn (array $p): string => $engagementsCtl->scopeAct($p));

// --- messaging (M8) -------------------------------------------------------
$router->get('/mentions',                       static fn (): string => $messages->mentions());
$router->get('/engagements/{id}/messages',      static fn (array $p): string => $messages->index($p));
$router->post('/engagements/{id}/threads',      static fn (array $p): string => $messages->store($p));
$router->get('/threads/{id}',                   static fn (array $p): string => $messages->show($p));
$router->post('/threads/{id}',                  static fn (array $p): string => $messages->post($p));

// --- scoreboard (M9) ------------------------------------------------------
$router->get('/engagements/{id}/scoreboard',    static fn (array $p): string => $scoreboard->show($p));
$router->post('/engagements/{id}/scoreboard',   static fn (array $p): string => $scoreboard->act($p));
$router->post('/engagements/{id}/rollover',     static fn (array $p): string => $scoreboard->rollover($p));

// --- the public marketing site (apex only) --------------------------------
/**
 * Every one of these 404s on a tenant subdomain — the controller enforces it,
 * not the router. A client of a firm must never see Pilotage's own pricing
 * page bleeding through onto their advisor's address.
 *
 * /signin is a signpost, not a login. Sessions are scoped to the exact tenant
 * host, so authenticating at the apex would produce a session that is invisible
 * where the firm actually lives.
 */
$router->get('/about',            static fn (): string => $marketing->about());
$router->get('/pricing',          static fn (): string => $marketing->pricing());
$router->get('/faq',              static fn (): string => $marketing->faq());
$router->get('/signin',           static fn (): string => $marketing->signin());
$router->post('/signin',          static fn (): string => $marketing->signinRedirect());
$router->get('/signup',           static fn (): string => $marketing->signup());
$router->post('/signup',          static fn (): string => $marketing->signupSubmit());
$router->get('/signup/available', static fn (): string => $marketing->slugAvailable());

/**
 * Host-aware, and it has to be: robots.txt is per-origin, and every tenant
 * subdomain is its own origin full of somebody's private client work. A single
 * permissive file served on all of them would invite the whole product to be
 * crawled. Apex allows the marketing pages and nothing else.
 */
$router->get('/robots.txt', static function (): string {
    header('Content-Type: text/plain; charset=utf-8');

    if (Tenant::current() !== null) {
        return "User-agent: *\nDisallow: /\n";
    }

    return "User-agent: *\n"
         . "Allow: /$\n"
         . "Allow: /about\n"
         . "Allow: /pricing\n"
         . "Allow: /faq\n"
         . "Allow: /signup\n"
         . "Disallow: /signin\n"
         . "Disallow: /signup/available\n"
         . "Disallow: /apply\n"
         . "Disallow: /_tick/\n"
         . "Disallow: /_health\n"
         . "\nSitemap: " . app_url('/sitemap.xml') . "\n";
});

$router->get('/sitemap.xml', static function (): string {
    if (Tenant::current() !== null) {
        throw new HttpException(404, 'Not found.');
    }

    $paths = ['/', '/pricing', '/about', '/faq', '/signup'];

    header('Content-Type: application/xml; charset=utf-8');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($paths as $p) {
        $xml .= '  <url><loc>' . h(app_url($p)) . '</loc></url>' . "\n";
    }

    return $xml . '</urlset>' . "\n";
});

// --- home -----------------------------------------------------------------
// Apex is the marketing site; a tenant host is somebody's dashboard.
$router->get('/', static fn (): string => Tenant::current() === null
    ? $marketing->home()
    : $dashboard->home());

$router->get('/_health', static function (): string {
    $tenant = Tenant::current();

    $checks = [
        'base_domain' => base_domain(),
        'env'         => (string) Config::get('app.env'),
        'tenant'      => $tenant === null ? '(apex)' : (string) $tenant['slug'],
    ];

    try {
        Database::conn()->query('SELECT 1');
        $checks['database'] = 'ok';
    } catch (Throwable) {
        $checks['database'] = 'FAILED';
    }

    /**
     * Background work, stated plainly.
     *
     * This is here because of a real incident: nothing scheduled ran in
     * production for a day and the only symptom was emails that never arrived.
     * A health page that says the database is fine while the entire background
     * layer is dormant is a health page that lies by omission.
     */
    try {
        foreach (Heartbeat::status() as $tick) {
            $checks['tick:' . $tick['mode']] =
                ($tick['healthy'] ? 'ok — ' : 'STALE — ') . $tick['human'];
        }
    } catch (Throwable) {
        $checks['tick'] = 'unknown';
    }

    $rows = '';
    foreach ($checks as $k => $v) {
        $rows .= '<tr><td class="pr-6 py-1 text-slate-500">' . h($k) . '</td><td class="py-1 font-mono">' . h($v) . '</td></tr>';
    }

    return View::render('app.plain', [
        'title' => 'Health',
        'body'  => '<table class="text-sm">' . $rows . '</table>',
    ]);
});

/**
 * The policy goes out before anything is dispatched, so it covers error pages
 * too — a 500 that renders a stack trace is exactly when you want script
 * execution constrained.
 */
Csp::send();

try {
    Tenant::resolveSlug($tenantSlug);

    // Cross-origin POSTs are refused outright, alongside SameSite and CSRF.
    if ($method === 'POST') {
        Csrf::checkOrigin($_SERVER, preg_replace('/:\d+$/', '', $host) ?? $host);
    }

    /**
     * The read-only gate (FR-13.3).
     *
     * A firm whose trial has run out or whose subscription has lapsed can still
     * read everything, still export everything, and still sign in. What it
     * cannot do is write. Nothing is deleted and nothing is hidden — see the
     * long note in Entitlements.
     *
     * Enforced HERE rather than in each controller, because a gate with
     * thirty-odd call sites is a gate with a hole in it. One place, checked on
     * every mutating request, and the short allowlist lives beside the rule.
     */
    $scopedTenant = Tenant::current();

    if ($scopedTenant !== null
        && Entitlements::enforced()
        && !Entitlements::allowsWrite($scopedTenant, $method, $path)) {
        throw new HttpException(
            402,
            'This workspace is read-only until billing is sorted out. Nothing has been deleted — '
            . 'everything is still here, still readable, and the export still works.'
        );
    }

    $body = $router->dispatch($method, $path);

    echo $body;

    /**
     * The heartbeat, last thing.
     *
     * After the response is produced, so nothing here can delay or break a
     * page. It costs one indexed UPDATE on most requests, and roughly 250ms on
     * the one request per interval that finds work due — see Core\Heartbeat for
     * why it is a detached self-request rather than fastcgi_finish_request
     * (this host is apache2handler, so there is no such function).
     *
     * Skipped for the tick endpoint itself, which would otherwise ask itself to
     * run again on the way out.
     */
    if (!str_starts_with($path, '/_tick/')) {
        Heartbeat::afterResponse();
    }
} catch (TenantNotFoundException $e) {
    // An unknown tenant and a suspended one look identical from outside.
    http_response_code(404);
    echo View::render('app.plain', ['title' => 'Not found', 'body' => '<p class="text-sm text-slate-600">No such site.</p>']);
    if ($debug) {
        error_log('Tenant resolution: ' . $e->getMessage());
    }
} catch (HttpException $e) {
    http_response_code($e->statusCode());
    echo View::render('app.plain', [
        'title' => (string) $e->statusCode(),
        'body'  => '<p class="text-sm text-slate-600">' . h($e->getMessage()) . '</p>',
    ]);
} catch (TenantScopeException $e) {
    // A programming error: something tried to run unscoped.
    error_log('Tenant scope violation: ' . $e->getMessage());
    http_response_code(500);
    echo View::render('app.plain', [
        'title' => 'Error',
        'body'  => $debug ? '<pre class="text-xs">' . h($e->getMessage()) . '</pre>' : '<p class="text-sm">Something went wrong.</p>',
    ]);
} catch (Throwable $e) {
    error_log('Unhandled: ' . $e->getMessage());
    http_response_code(500);
    echo View::render('app.plain', [
        'title' => 'Error',
        'body'  => $debug ? '<pre class="text-xs overflow-x-auto">' . h((string) $e) . '</pre>' : '<p class="text-sm">Something went wrong.</p>',
    ]);
}
