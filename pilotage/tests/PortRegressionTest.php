<?php

declare(strict_types=1);

/**
 * Bugs found in the original while porting Pilotage to tools.bizorca.com,
 * each pinned so it stays fixed. Every one was confirmed against the original
 * code before it was changed.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Messaging;

$db = Database::conn();

T::group('Every link a notification carries is a route that exists');

/**
 * The original built /engagements/{id}/messages/{thread} for a new-message
 * notification. No route matches that, so the email button and the in-app
 * notification both opened a 404. The thread page is /threads/{id}.
 */
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_notifications', 'pl_messages', 'pl_thread_participants', 'pl_threads', 'pl_engagements',
          'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id, slug, name, status) VALUES (1,'acme','Acme Advisory','active')");
$db->exec("INSERT INTO pl_client_orgs (id, tenant_id, name, status) VALUES (1,1,'Alpha','active')");
Tenant::setCurrent(['id' => 1, 'slug' => 'acme', 'name' => 'Acme Advisory']);
$users = new UserRepository(1);
$coachId = $users->create(['email' => 'c@acme.test', 'name' => 'Coach', 'role' => 'coach', 'status' => 'active']);
$ownerId = $users->create(['email' => 'o@alpha.test', 'name' => 'Owner', 'role' => 'client_owner', 'client_org_id' => 1, 'status' => 'active']);
$db->exec("INSERT INTO pl_engagements (id, tenant_id, client_org_id, coach_user_id, title, status) VALUES (1,1,1,{$coachId},'Plan','active')");
$coach = $users->find($coachId);
$owner = $users->find($ownerId);

$threadId = Messaging::createThread(1, 1, 'Hello', 'First message', $coach);
Messaging::post(1, $threadId, 'A reply', $owner);

$links = $db->query("SELECT DISTINCT link FROM pl_notifications WHERE tenant_id = 1 AND link IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
T::ok($links !== [], 'posting in a thread queued a notification with a link');

$routes = [];
preg_match_all("/\\\$router->(?:get|post)\\('([^']+)'/", (string) file_get_contents(dirname(__DIR__) . '/public/index.php'), $m);
foreach ($m[1] as $pattern) {
    $routes[] = '#^' . preg_replace('/\\\\\{[a-z_]+\\\\\}/i', '[^/]+', preg_quote($pattern, '#')) . '$#';
}
foreach ($links as $link) {
    $path = strtok((string) $link, '?#');
    $matched = false;
    foreach ($routes as $rx) {
        if (preg_match($rx, (string) $path) === 1) {
            $matched = true;
            break;
        }
    }
    T::ok($matched, 'the notification link ' . $link . ' is a real route');
}
T::same('/threads/' . $threadId, strtok((string) $links[0], '?#'), 'and it is the thread page');

Tenant::reset();

T::group('The firm dashboard receives its data');

/**
 * View::capture() extracts template variables with EXTR_SKIP, so a key named
 * after one of its own locals ($template, $data, $path) silently never reaches
 * the template. ReportController passed the firm report as 'data', and the
 * whole of /firm/dashboard rendered from nulls in the original. Assert the
 * rule across every controller, not just the one that broke.
 */
$offenders = [];
foreach (glob(dirname(__DIR__) . '/src/{Controllers,Services}/*.php', GLOB_BRACE) ?: [] as $file) {
    if (preg_match_all("/^\\s*'(template|data|path)'\\s*=>/m", (string) file_get_contents($file), $hits)) {
        $offenders[] = basename($file) . ': ' . implode(', ', array_unique($hits[1]));
    }
}
T::same([], $offenders, 'no view is handed a key that View::capture() would swallow');
T::ok(str_contains((string) file_get_contents(dirname(__DIR__) . '/src/Views/reports/firm.php'), "\$report['counts']"),
    'the firm dashboard reads the report it is actually given');
