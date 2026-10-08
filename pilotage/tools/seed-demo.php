<?php

declare(strict_types=1);

/**
 * Seed one realistic engagement, so there is something to look at.
 *
 *   php tools/seed-demo.php --tenant=bizorca
 *   php tools/seed-demo.php --tenant=bizorca --remove
 *
 * ---------------------------------------------------------------------------
 * THIS WRITES REAL ROWS, INCLUDING IN PRODUCTION. Three properties make that
 * safe enough to do deliberately:
 *
 *   1. Everything hangs off ONE client organization, named below. Removing that
 *      org cascades to the engagement, sessions, commitments, documents, goals,
 *      metrics, issues, threads and worksheet responses. `--remove` is
 *      therefore complete rather than best-effort, and it is the reason not to
 *      scatter demo rows across existing records.
 *   2. It refuses to run twice. A second run finds the org and stops, rather
 *      than quietly producing two of everything.
 *   3. NO MAIL LEAVES. Seeding drives the real services, which queue real
 *      notifications; those are marked in_app at the end so they populate the
 *      bell menu and are never emailed. Without that, the next five-minute tick
 *      would send a stack of "a session is booked" mail about a fictional
 *      client.
 *
 * It lives outside public/, so it is not reachable over HTTP.
 * ---------------------------------------------------------------------------
 *
 * The data is shaped to exercise the parts of the product that only look right
 * with history behind them: commitments in every state including one missed
 * twice and escalated, a metric with eight weeks of movement, an assessment run
 * twice so there is a delta, and a health score with enough behind it to
 * actually publish a band.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// The shared tools core: tl_db() and the shared `users` table the demo
// contacts sign in with. Beside this tool on the server, under private_html/
// in the repo.
foreach ([dirname(__DIR__) . '/../includes', dirname(__DIR__) . '/../private_html/includes'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require_once $dir . '/bootstrap.php';
        break;
    }
}

require dirname(__DIR__) . '/src/Core/autoload.php';

use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Compliance;
use Bizorca\Pilotage\Services\Messaging;
use Bizorca\Pilotage\Services\Notifications;
use Bizorca\Pilotage\Services\PlaybookInstantiator;
use Bizorca\Pilotage\Services\Scorecard;
use Bizorca\Pilotage\Services\SessionService;
use Bizorca\Pilotage\Services\Worksheets;

Config::load(require dirname(__DIR__) . '/config/app.php');

/** The one anchor. Everything else hangs off it, so removal is one delete. */
const DEMO_ORG = 'Harbour Line Joinery';

/** Client-side logins, so both sides of the wall can be walked. */
const DEMO_PASSWORD = 'HarbourLine2026!';

$options = getopt('', ['tenant:', 'remove', 'password:']);
$slug = (string) ($options['tenant'] ?? 'bizorca');
$password = (string) ($options['password'] ?? DEMO_PASSWORD);

$db = Database::conn();

$stmt = $db->prepare('SELECT * FROM pl_tenants WHERE slug = :slug');
$stmt->execute(['slug' => $slug]);
$tenant = $stmt->fetch();

if ($tenant === false) {
    fwrite(STDERR, "No firm with slug '{$slug}'.\n");
    exit(1);
}

$tenantId = (int) $tenant['id'];

$say = static function (string $line): void {
    echo $line . "\n";
};

// ---------------------------------------------------------------- removal

$find = $db->prepare('SELECT id FROM pl_client_orgs WHERE tenant_id = :tid AND name = :name');
$find->execute(['tid' => $tenantId, 'name' => DEMO_ORG]);
$existing = $find->fetch();

if (isset($options['remove'])) {
    if ($existing === false) {
        $say('Nothing to remove.');
        exit(0);
    }

    $orgId = (int) $existing['id'];

    // The users go explicitly: they belong to the org but the FK is SET NULL,
    // not CASCADE, precisely so a real firm cannot lose people by archiving a
    // client. Demo accounts are a different matter.
    // Their Bizorca Tools accounts go too, but only accounts this seeder
    // made (a reserved .example address) and only once nothing else uses them.
    $accounts = $db->prepare(
        "SELECT DISTINCT account_id FROM pl_users
         WHERE tenant_id = :tid AND client_org_id = :org AND account_id IS NOT NULL"
    );
    $accounts->execute(['tid' => $tenantId, 'org' => $orgId]);
    $accountIds = array_map('intval', $accounts->fetchAll(PDO::FETCH_COLUMN));

    $db->prepare('DELETE FROM pl_users WHERE tenant_id = :tid AND client_org_id = :org')
       ->execute(['tid' => $tenantId, 'org' => $orgId]);

    foreach ($accountIds as $accountId) {
        $db->prepare(
            "DELETE FROM users WHERE id = :id AND email LIKE '%@harbourline.example'
               AND NOT EXISTS (SELECT 1 FROM pl_users WHERE account_id = :id2)"
        )->execute(['id' => $accountId, 'id2' => $accountId]);
    }
    $db->prepare('DELETE FROM pl_client_orgs WHERE tenant_id = :tid AND id = :id')
       ->execute(['tid' => $tenantId, 'id' => $orgId]);

    $say('Removed ' . DEMO_ORG . ' and everything hanging off it.');
    exit(0);
}

if ($existing !== false) {
    $say(DEMO_ORG . ' is already here (org #' . (int) $existing['id'] . ').');
    $say('Run with --remove first if you want a clean one.');
    exit(0);
}

// ------------------------------------------------------------------ setup

$users = new UserRepository($tenantId);

$coach = null;

foreach ($users->firmSide() as $candidate) {
    if ((string) $candidate['status'] === 'active') {
        $coach = $candidate;
        break;
    }
}

if ($coach === null) {
    fwrite(STDERR, "That firm has no active staff to be the coach.\n");
    exit(1);
}

$coachId = (int) $coach['id'];
$say('Coach: ' . $coach['name'] . ' <' . $coach['email'] . '>');

$noteFrom = static fn (int $daysAgo): string => date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
$dateFrom = static fn (int $daysAgo): string => date('Y-m-d', strtotime("-{$daysAgo} days"));
$dateIn   = static fn (int $days): string => date('Y-m-d', strtotime("+{$days} days"));

// --------------------------------------------------------- the client org

$orgs = new ClientOrgRepository($tenantId);

$orgId = $orgs->createOrg([
    'name'           => DEMO_ORG,
    'legal_name'     => 'Harbour Line Joinery LLC',
    'entity_type'    => 'llc',
    'industry_naics' => '321918',
    'employee_count' => 14,
    'revenue_band'   => '1m_5m',
    'fiscal_year_end' => '12-31',
    'website'        => 'https://harbourline.example',
    'city'           => 'Port Townsend',
    'region'         => 'WA',
    'country'        => 'US',
    'situation'      => 'Custom millwork shop. Good work, full order book, and no idea which jobs '
                      . 'actually make money. Owner is the bottleneck on every quote.',
    'status'         => 'active',
    'owner_user_id'  => $coachId,
]);

$say('Client: ' . DEMO_ORG . ' (#' . $orgId . ')');

$people = [];

foreach ([
    ['maren@harbourline.example', 'Maren Sato',  'client_owner',  'Owner'],
    ['theo@harbourline.example',  'Theo Bright', 'client_member', 'Shop manager'],
    ['iris@harbourline.example',  'Iris Nadel',  'client_member', 'Bookkeeper'],
] as [$email, $name, $role, $title]) {
    $people[$name] = $users->create([
        'email'         => $email,
        'name'          => $name,
        'role'          => $role,
        'client_org_id' => $orgId,
        'status'        => 'active',
    ]);

    // Signing in is the shared Bizorca Tools account now, so each demo
    // contact gets one (reused if a previous run left it) and the membership
    // is bound to it through attachAccount(), the one write path for that.
    $find = $db->prepare('SELECT id FROM users WHERE email = :e');
    $find->execute(['e' => $email]);
    $accountId = (int) ($find->fetchColumn() ?: 0);
    if ($accountId === 0) {
        $db->prepare('INSERT INTO users (email, password_hash, name) VALUES (:e, :h, :n)')
           ->execute(['e' => $email, 'h' => password_hash($password, PASSWORD_DEFAULT), 'n' => $name]);
        $accountId = (int) $db->lastInsertId();
    }
    $users->attachAccount($people[$name], $accountId);

    $say('  contact: ' . $name . ' (' . $title . ') — ' . $email);
}

$maren = $people['Maren Sato'];
$theo  = $people['Theo Bright'];
$iris  = $people['Iris Nadel'];

// -------------------------------------------------------- the engagement

$engagements = new EngagementRepository($tenantId);

$engagementId = $engagements->createEngagement([
    'client_org_id' => $orgId,
    'title'         => 'Margin and capacity, Q3–Q4',
    'summary'       => 'Work out which jobs make money, then build a quoting process that does not '
                     . 'require Maren personally. Ninety days.',
    'status'        => 'active',
    'coach_user_id' => $coachId,
    'cadence'       => 'biweekly',
    'starts_on'     => $dateFrom(84),
]);

$engagements->addMember($engagementId, $coachId, 'lead');
$say('Engagement: #' . $engagementId . ' — Margin and capacity, Q3–Q4');

// The firm already ships a playbook; apply its published version if there is one.
$version = $db->prepare(
    "SELECT v.id FROM pl_playbook_versions v
     JOIN pl_playbooks p ON p.id = v.playbook_id AND p.tenant_id = v.tenant_id
     WHERE v.tenant_id = :tid AND v.state = 'published'
     ORDER BY v.id DESC LIMIT 1"
);
$version->execute(['tid' => $tenantId]);
$versionRow = $version->fetch();

if ($versionRow !== false) {
    PlaybookInstantiator::apply($tenantId, $engagementId, (int) $versionRow['id'], $coachId);
    $say('  playbook applied');
}

// ------------------------------------------------------------- sessions

$sessions = [];

foreach ([
    ['Kickoff — where the money actually goes', 84, 'complete'],
    ['Job costing: first read',                 70, 'complete'],
    ['Quoting without Maren',                   56, 'complete'],
    ['Mid-quarter review',                      42, 'complete'],
    ['Capacity and the second bench',           28, 'complete'],
    ['What we learned about pricing',           14, 'cancelled'],
    ['Q4 planning',                             -7, 'scheduled'],
] as [$title, $daysAgo, $status]) {
    $when = $daysAgo >= 0
        ? date('Y-m-d 17:00:00', strtotime("-{$daysAgo} days"))
        : date('Y-m-d 17:00:00', strtotime('+' . abs($daysAgo) . ' days'));

    $sessionId = SessionService::schedule($tenantId, $engagementId, $title, $when, null, $coachId, 'Video call');
    $sessions[] = $sessionId;

    if ($status === 'complete') {
        $db->prepare(
            "UPDATE pl_sessions SET status = 'complete', ended_at = :ended
             WHERE tenant_id = :tid AND id = :id"
        )->execute(['ended' => $when, 'tid' => $tenantId, 'id' => $sessionId]);

        foreach ([[$maren, 'Maren Sato'], [$theo, 'Theo Bright']] as [$attendeeId, $attendeeName]) {
            $rowId = SessionService::addAttendee($tenantId, $sessionId, $attendeeId, $attendeeName);
            SessionService::markAttendance($tenantId, $rowId, true);
        }
    }

    if ($status === 'cancelled') {
        $db->prepare("UPDATE pl_sessions SET status = 'cancelled' WHERE tenant_id = :tid AND id = :id")
           ->execute(['tid' => $tenantId, 'id' => $sessionId]);
    }
}

// Shared notes on the most recent held session, so the runner has something in it.
SessionService::saveSharedNotes(
    $tenantId,
    $sessions[4],
    "Second bench is the constraint, not demand.\n\n"
    . "Theo can quote standard casework from the rate card without Maren. Anything with a curve or "
    . "a finish spec still comes to her.\n\n"
    . "Agreed: track quoted-vs-actual hours on every job over \$5k for six weeks before changing "
    . "the rate card.",
    $coachId
);

$say('Sessions: ' . count($sessions) . ' (5 held, 1 cancelled, 1 upcoming)');

// ---------------------------------------------------------- commitments

$tasks = new TaskRepository($tenantId);

$commitments = [
    // [title, owner, due days ago (negative = future), status]
    ['Pull last 12 months of job costs into one sheet', $iris,  76, 'done'],
    ['List every job over $5k from Q2',                 $theo,  72, 'done'],
    ['Rate card: current pricing, written down',        $maren, 62, 'done'],
    ['Time-track three jobs end to end',                $theo,  48, 'done'],
    ['Draft quoting checklist for standard casework',   $theo,  34, 'done'],
    ['Reconcile shop supply spend to jobs',             $iris,  30, 'done'],
    ['Interview two candidates for the second bench',   $maren, 12, 'open'],
    ['Send the revised rate card to three customers',   $maren,  4, 'open'],
    ['Weekly: enter quoted vs actual hours',            $iris,  -2, 'open'],
    ['Book the equipment finance conversation',         $maren, -9, 'open'],
];

$stalled = null;

foreach ($commitments as [$title, $owner, $dueDaysAgo, $status]) {
    $due = $dueDaysAgo >= 0 ? $dateFrom($dueDaysAgo) : $dateIn(abs($dueDaysAgo));

    $taskId = $tasks->createTask([
        'engagement_id' => $engagementId,
        'title'         => $title,
        'owner_user_id' => $owner,
        'assigned_by'   => $coachId,
        'due_on'        => $due,
        'status'        => 'open',
        'source'        => 'session',
    ]);

    if ($status === 'done') {
        $tasks->complete($taskId, $owner);
    }

    if ($title === 'Interview two candidates for the second bench') {
        $stalled = $taskId;
    }
}

// One commitment that has been missed twice and escalated. This is the
// accountability loop's whole argument, and it only shows up with history.
if ($stalled !== null) {
    $db->prepare(
        'UPDATE pl_tasks SET miss_count = 2, last_missed_on = :on
         WHERE tenant_id = :tid AND id = :id'
    )->execute(['on' => $dateFrom(12), 'tid' => $tenantId, 'id' => $stalled]);

    $issueId = Scorecard::raiseIssue(
        $tenantId,
        $engagementId,
        'Stuck: hiring the second bench',
        "This has come and gone twice without moving.\n\n"
        . 'Worth asking what is actually in the way — Maren says she has no time to interview, '
        . 'which is the same constraint the hire is meant to relieve.',
        'missed_commitment',
        $coachId,
        'high'
    );

    $db->prepare('UPDATE pl_tasks SET escalated_issue_id = :iid WHERE tenant_id = :tid AND id = :id')
       ->execute(['iid' => $issueId, 'tid' => $tenantId, 'id' => $stalled]);
}

Scorecard::raiseIssue(
    $tenantId,
    $engagementId,
    'Quotes still bottleneck on one person',
    'Theo can quote standard casework now, but anything non-standard waits for Maren. '
    . 'Two jobs slipped a week in September for this reason.',
    'session',
    $coachId,
    'normal'
);

$say('Commitments: ' . count($commitments) . ' (6 done, 4 open, 1 escalated to an issue)');

// ---------------------------------------------------------------- goals

foreach ([
    ['Know the true margin on every job over $5k', 'Quoted vs actual hours captured on 100% of qualifying jobs for six consecutive weeks.'],
    ['Maren out of the standard quoting path',     'Theo issues standard casework quotes unaided for a month, with no reworks.'],
    ['Second bench hired and productive',          'A second bench joiner is billable on their own jobs by the end of Q4.'],
] as $i => [$title, $criteria]) {
    Scorecard::createGoal($tenantId, $engagementId, [
        'title'            => $title,
        'success_criteria' => $criteria,
        'owner_user_id'    => $i === 2 ? $maren : ($i === 0 ? $iris : $theo),
        'target_date'      => Scorecard::quarterEnd(),
    ]);
}

$say('Goals: 3 for ' . Scorecard::quarterLabel());

// -------------------------------------------------------------- metrics

$metrics = [
    ['Quoted vs actual hours, variance %', '%',   'lower',  10.0,  [34, 31, 29, 33, 24, 21, 18, 16]],
    ['Jobs quoted without Maren',          'jobs', 'higher',  8.0,  [0, 0, 1, 1, 3, 4, 5, 6]],
    ['Gross margin on completed jobs',     '%',   'higher', 38.0,  [26, 28, 27, 30, 31, 33, 34, 36]],
    ['Days from enquiry to quote',         'days', 'lower',   3.0,  [11, 10, 12, 9, 8, 7, 6, 5]],
];

foreach ($metrics as [$name, $unit, $direction, $target, $series]) {
    $metricId = Scorecard::createMetric($tenantId, $engagementId, [
        'name'          => $name,
        'unit'          => $unit,
        'direction'     => $direction,
        'target_value'  => $target,
        'frequency'     => 'weekly',
        'owner_user_id' => $iris,
    ]);

    // Backdate the definition as well as the values. Health measures entry
    // consistency against periods that have ELAPSED since a metric was defined,
    // so a metric created this morning is correctly reported as too new to be
    // behind on — which would leave that signal unmeasured in demo data.
    $db->prepare('UPDATE pl_metrics SET created_at = :on WHERE tenant_id = :tid AND id = :id')
       ->execute(['on' => $noteFrom(63), 'tid' => $tenantId, 'id' => $metricId]);

    // Eight weeks back to last week. Enough that the scorecard has a shape and
    // the period report has real movement to show.
    foreach ($series as $offset => $value) {
        $weeksAgo = count($series) - $offset;
        Scorecard::record(
            $tenantId,
            $metricId,
            Scorecard::normalizePeriod(date('Y-m-d', strtotime("-{$weeksAgo} weeks"))),
            (float) $value,
            $iris
        );
    }
}

$say('Metrics: ' . count($metrics) . ', eight weeks each');

// ------------------------------------------------------------ worksheets

$worksheetId = Worksheets::create($tenantId, [
    'title'       => 'Owner dependency check',
    'description' => 'Ten minutes. Answer for how things actually are, not how they should be.',
    'kind'        => 'assessment',
], $coachId);

$fields = [];

foreach ([
    ['How much could the shop run without you for two weeks?', 'Not at all', 'Entirely'],
    ['How confident are you in the numbers on a quote?',        'Guessing',   'Certain'],
    ['How predictable is next month\'s cash?',                  'No idea',    'To the dollar'],
    ['How often do you work on the business rather than in it?', 'Never',      'Most weeks'],
] as [$label, $low, $high]) {
    $fields[] = Worksheets::addField($tenantId, $worksheetId, [
        'label'           => $label,
        'field_type'      => 'scale',
        'required'        => 1,
        'scale_min'       => 1,
        'scale_max'       => 5,
        'scale_min_label' => $low,
        'scale_max_label' => $high,
        'weight'          => 1,
    ]);
}

Worksheets::addField($tenantId, $worksheetId, [
    'label'      => 'What would break first if you were away for a month?',
    'field_type' => 'long_text',
    'required'   => 0,
]);

foreach ([
    ['min_percent' => 0,  'max_percent' => 39,  'label' => 'The business is you',
     'interpretation' => 'Nothing runs without you. Start by writing down one thing you do from memory.'],
    ['min_percent' => 40, 'max_percent' => 69,  'label' => 'Leaning on you',
     'interpretation' => 'The shape of a business is here. The constraint is documentation, not capability.'],
    ['min_percent' => 70, 'max_percent' => 100, 'label' => 'It runs',
     'interpretation' => 'You own a business rather than a job. Protect that.'],
] as $band) {
    Worksheets::addBand($tenantId, $worksheetId, $band);
}

Worksheets::publish($tenantId, $worksheetId);

// Run it twice, so the over-time comparison has something to compare.
foreach ([['Baseline, week 1', 80, [1, 2, 2, 1]], ['Re-run, week 10', 7, [3, 4, 3, 3]]] as [$label, $daysAgo, $answers]) {
    $assignmentId = Worksheets::assign($tenantId, $worksheetId, $engagementId, $maren, $label, null, $coachId);

    $response = Worksheets::startOrResume($tenantId, $assignmentId, $maren, null);

    foreach ($fields as $i => $fieldId) {
        Worksheets::saveAnswer($tenantId, (int) $response['id'], $fieldId, (string) $answers[$i]);
    }

    Worksheets::submit($tenantId, (int) $response['id']);

    // Backdate so the trend reads as ten weeks of progress rather than two
    // things that happened this afternoon.
    $db->prepare(
        'UPDATE pl_worksheet_assignments SET created_at = :on WHERE tenant_id = :tid AND id = :id'
    )->execute(['on' => $noteFrom($daysAgo), 'tid' => $tenantId, 'id' => $assignmentId]);
    $db->prepare(
        'UPDATE pl_worksheet_responses SET submitted_at = :on WHERE tenant_id = :tid AND assignment_id = :id'
    )->execute(['on' => $noteFrom($daysAgo), 'tid' => $tenantId, 'id' => $assignmentId]);
}

$say('Worksheet: "Owner dependency check", run twice (delta to show)');

// -------------------------------------------------------------- messages

$coachRow = $users->find($coachId);
$marenRow = $users->find($maren);

$threadId = Messaging::createThread(
    $tenantId,
    $engagementId,
    'Rate card — the awkward number',
    "Maren, I ran your Q2 jobs against the current rate card. Six of the eleven lost money once "
    . "you count rework.\n\nI do not think this is a pricing problem yet. It looks like a "
    . "quoting-accuracy problem. Worth twenty minutes before Thursday?",
    $coachRow,
    true
);

Messaging::post(
    $tenantId,
    $threadId,
    "That matches what Iris found. I have been quoting curved work off feel for years.\n\n"
    . "Thursday works. Can we look at the Whitfield job specifically? That is the one that hurt.",
    $marenRow,
    true
);

Messaging::post(
    $tenantId,
    $threadId,
    "Yes — bring the actual hours if Theo has them. @Theo Bright, can you pull those?",
    $coachRow,
    true
);

// An internal note. Client-side users never see this, which is worth seeing.
Messaging::createThread(
    $tenantId,
    $engagementId,
    'Internal: renewal thinking',
    "Engagement ends in six weeks. The hiring goal will not be met on this timeline — worth "
    . "naming that in the Q4 planning session rather than letting it arrive as a surprise.",
    $coachRow,
    false
);

$say('Messages: 2 threads (one internal)');

// -------------------------------------------------------- the agreement

Compliance::waiveAgreement($tenantId, $engagementId, 'Signed on paper at the kickoff, filed in Dropbox.');

// --------------------------------------------- notifications: no mail out

/**
 * Seeding drove the real services, so the queue is now full of perfectly valid
 * notifications about a fictional joinery. Marking them in_app means they show
 * up in the bell menu — which is worth seeing — and are never emailed.
 *
 * Leaving them pending would have the next five-minute tick send a stack of
 * "a session is booked" mail about a client that does not exist.
 */
$marked = $db->prepare(
    "UPDATE pl_notifications SET delivery = 'in_app'
     WHERE tenant_id = :tid AND delivery = 'pending'"
);
$marked->execute(['tid' => $tenantId]);

$say('Notifications: ' . $marked->rowCount() . ' queued rows marked in-app');

/**
 * And silence the demo contacts for good.
 *
 * Marking the queue in_app is not enough on its own, and the gap cost real
 * reputation before it was noticed: the client weekly digest is assembled from
 * LIVE open commitments, not from the queue, so it went out on the next tick to
 * three addresses at `harbourline.example` — a reserved TLD that does not
 * resolve and hard-bounces. Hard bounces damage a young sending domain, which
 * is exactly what the deliverability work was protecting.
 *
 * So every optional event for these accounts, digests included, is switched
 * off. Transactional events cannot be switched off by design, but those only
 * fire when someone acts, and nobody acts as a fictional joiner.
 */
$silenced = 0;

foreach ($people as $name => $personId) {
    foreach (Notifications::CATALOGUE as $eventType => $meta) {
        if ($meta['transactional']) {
            continue;
        }

        Notifications::setPreference($tenantId, $personId, $eventType, Notifications::OFF);
        $silenced++;
    }
}

$say('Silenced: ' . $silenced . ' preferences off across the demo contacts; no mail will be sent');

$say('');
$say('Done. Sign in (Bizorca Tools account) as the client side to see the other half of the wall:');
$say('  maren@harbourline.example  (owner)');
$say('  theo@harbourline.example   (shop manager)');
$say('  iris@harbourline.example   (bookkeeper)');
$say('  password: ' . $password);
$say('');
$say('Remove it all with:  php tools/seed-demo.php --tenant=' . $slug . ' --remove');
