<?php

declare(strict_types=1);

/**
 * M6: tasks and the accountability loop.
 *
 * The group that matters is "The loop escalates". Everything else is a task
 * list; that one is the product's actual argument — a commitment missed twice
 * stops being a reminder and becomes a problem to solve.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\AccountabilityLoop;
use Bizorca\Pilotage\Services\AgendaBuilder;
use Bizorca\Pilotage\Services\Comments;
use Bizorca\Pilotage\Services\SessionService;
use Bizorca\Pilotage\Services\StarterSessionTemplates;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_comments', 'pl_tasks', 'pl_task_series', 'pl_issues',
          'pl_session_recaps', 'pl_session_notes_private', 'pl_session_notes_shared',
          'pl_session_agenda_items', 'pl_session_attendees', 'pl_sessions',
          'pl_session_template_items', 'pl_session_templates',
          'pl_engagement_step_artifacts', 'pl_engagement_steps', 'pl_engagement_phases',
          'pl_engagement_playbooks', 'pl_engagement_members', 'pl_engagements',
          'pl_org_events', 'pl_client_contacts', 'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);

$orgs = new ClientOrgRepository(1);
$orgId = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active', 'owner_user_id' => $coachId]);

$ownerId = $users->create(['email' => 'owner@alpha.test', 'name' => 'An Owner', 'role' => 'client_owner', 'client_org_id' => $orgId, 'status' => 'active']);

$engagements = new EngagementRepository(1);
$engId = $engagements->createEngagement([
    'client_org_id' => $orgId, 'title' => 'Q3 rhythm', 'status' => 'active', 'coach_user_id' => $coachId,
]);

$tasks = new TaskRepository(1);


T::group('Tasks go both ways');

$clientTask = $tasks->createTask([
    'engagement_id'      => $engId,
    'title'              => 'Send the trailing 12 P&L',
    'owner_user_id'      => $ownerId,
    'assigned_by'        => $coachId,
    'due_on'             => date('Y-m-d', strtotime('+7 days')),
    'definition_of_done' => 'PDF or spreadsheet, uploaded here.',
]);

// FR-6.2: a coach owing the client a deliverable is first-class.
$coachTask = $tasks->createTask([
    'engagement_id' => $engId,
    'title'         => 'Draft the margin analysis',
    'owner_user_id' => $coachId,
    'assigned_by'   => $ownerId,
    'due_on'        => date('Y-m-d', strtotime('+3 days')),
]);

T::same($ownerId, (int) $tasks->find($clientTask)['owner_user_id'], 'a task can be owed by the client');
T::same($coachId, (int) $tasks->find($coachTask)['owner_user_id'], 'and a task can be owed by the coach');
T::same(1, count($tasks->forOwner($ownerId)), 'the client sees one thing owed');
T::same(1, count($tasks->forOwner($coachId)), 'the coach sees one too');

T::group('Validation');

T::throws(InvalidArgumentException::class, static fn () => $tasks->createTask(['engagement_id' => $engId, 'title' => '']), 'a task needs a title');
T::throws(InvalidArgumentException::class, static fn () => $tasks->createTask(['title' => 'Orphan']), 'a task needs an engagement');
T::throws(InvalidArgumentException::class, static fn () => $tasks->createTask(['engagement_id' => $engId, 'title' => 'X', 'priority' => 'urgent']), 'unknown priority refused');
T::throws(InvalidArgumentException::class, static fn () => $tasks->createTask(['engagement_id' => $engId, 'title' => 'X', 'due_on' => '07/07/2026']), 'malformed due date refused');
T::throws(InvalidArgumentException::class, static fn () => $tasks->createTask(['engagement_id' => $engId, 'title' => 'X', 'evidence_required' => 'blood']), 'unknown evidence type refused');


T::group('One level of nesting, and only one');

$sub = $tasks->createTask(['parent_id' => $clientTask, 'title' => 'Find last year\'s file']);
T::same($clientTask, (int) $tasks->find($sub)['parent_id'], 'a subtask attaches to its parent');
T::same($engId, (int) $tasks->find($sub)['engagement_id'], 'and inherits the engagement');

// A subtask of a subtask flattens to the same parent rather than erroring.
$subSub = $tasks->createTask(['parent_id' => $sub, 'title' => 'Ask the bookkeeper']);
T::same($clientTask, (int) $tasks->find($subSub)['parent_id'], 'nesting deeper flattens to one level');
T::same(2, count($tasks->subtasks($clientTask)), 'both land under the top-level task');

T::throws(InvalidArgumentException::class,
    static fn () => $tasks->createTask(['parent_id' => 999999, 'title' => 'Nowhere']),
    'an unknown parent is refused');


T::group('Evidence requirements');

$needsNote = $tasks->createTask([
    'engagement_id' => $engId, 'title' => 'Talk to the bank', 'owner_user_id' => $ownerId,
    'evidence_required' => 'note',
]);

T::throws(InvalidArgumentException::class,
    static fn () => $tasks->complete($needsNote, $ownerId),
    'a task needing a note cannot be closed without one');
T::throws(InvalidArgumentException::class,
    static fn () => $tasks->complete($needsNote, $ownerId, '   '),
    'whitespace is not evidence');

T::ok($tasks->complete($needsNote, $ownerId, 'Spoke to Marie on Tuesday; covenant waiver possible.'), 'with a note it closes');
$closed = $tasks->find($needsNote);
T::same('done', $closed['status'], 'status is done');
T::ok(str_contains((string) $closed['evidence_note'], 'Marie'), 'the evidence is kept');

T::ok($tasks->complete($coachTask, $coachId), 'a task needing nothing closes plainly');
T::ok($tasks->complete($coachTask, $coachId), 'completing twice is harmless');
T::ok($tasks->reopen($coachTask), 'and it can be reopened');
T::same('open', $tasks->find($coachTask)['status'], 'reopened is open');


T::group('The loop escalates');

$missable = $tasks->createTask([
    'engagement_id' => $engId,
    'title'         => 'Send the trailing 12 P&L',
    'owner_user_id' => $ownerId,
    'due_on'        => date('Y-m-d', strtotime('-3 days')),
]);

$result = AccountabilityLoop::sweep(1);
T::ok($result['missed'] >= 1, 'the sweep records a miss');
T::same(0, $result['escalated'], 'one miss does not escalate — once is life');
T::same(1, (int) $tasks->find($missable)['miss_count'], 'miss count is one');

// Running the tick again the same day must not double count.
$again = AccountabilityLoop::sweep(1);
T::same(0, $again['missed'], 'the sweep is idempotent within a due date');
T::same(1, (int) $tasks->find($missable)['miss_count'], 'miss count did not move');

// Reschedule. This is the pattern the loop exists to catch: pushing a date
// you already missed must NOT wipe the history.
$tasks->update($missable, ['due_on' => date('Y-m-d', strtotime('-1 day'))]);
T::same(1, (int) $tasks->find($missable)['miss_count'], 'rescheduling does not reset the miss count');

$second = AccountabilityLoop::sweep(1);
T::same(1, $second['missed'], 'the new due date can be missed too');
T::same(1, $second['escalated'], 'the second miss escalates');
T::same(2, (int) $tasks->find($missable)['miss_count'], 'miss count is two');

$issueId = $tasks->find($missable)['escalated_issue_id'];
T::ok($issueId !== null, 'the task is linked to an issue');

$issue = $db->query('SELECT * FROM pl_issues WHERE id = ' . (int) $issueId)->fetch();
T::same('missed_commitment', $issue['origin'], 'the issue knows where it came from');
T::same('open', $issue['status'], 'and it is open');
T::ok(str_contains((string) $issue['title'], 'Send the trailing 12'), 'it names the commitment');
T::ok(str_contains((string) $issue['detail'], 'in the way'), 'it asks what is in the way rather than blaming anyone');

// Escalating twice for the same task would be nagging by another name.
$tasks->update($missable, ['due_on' => date('Y-m-d', strtotime('-1 day')), 'last_missed_on' => null]);
$db->exec('UPDATE pl_tasks SET last_missed_on = NULL WHERE id = ' . $missable);
$third = AccountabilityLoop::sweep(1);
T::same(0, $third['escalated'], 'an already-escalated task does not escalate again');
T::same(1, (int) $db->query('SELECT COUNT(*) c FROM pl_issues')->fetch()['c'], 'still exactly one issue');

// A completed task is never swept.
$db->exec('UPDATE pl_tasks SET last_missed_on = NULL WHERE id = ' . $clientTask);
$tasks->update($clientTask, ['due_on' => date('Y-m-d', strtotime('-5 days'))]);
$tasks->complete($clientTask, $ownerId);
$after = AccountabilityLoop::sweep(1);
T::same(0, $after['missed'], 'a completed task is never counted as missed');


T::group('Nudges');

$nudges = AccountabilityLoop::dueForNudge(1);
$titles = array_column($nudges, 'title');
T::ok(in_array('Send the trailing 12 P&L', $titles, true), 'overdue tasks are nudged');

$soon = $tasks->createTask([
    'engagement_id' => $engId, 'title' => 'Due in two days', 'owner_user_id' => $ownerId,
    'due_on' => date('Y-m-d', strtotime('+2 days')),
]);
$titles = array_column(AccountabilityLoop::dueForNudge(1), 'title');
T::ok(in_array('Due in two days', $titles, true), 'tasks due in two days are nudged');

$tasks->update($soon, ['due_on' => date('Y-m-d', strtotime('+9 days'))]);
$titles = array_column(AccountabilityLoop::dueForNudge(1), 'title');
T::ok(!in_array('Due in two days', $titles, true), 'tasks far out are not nudged');


T::group('Recurring series');

$db->exec("INSERT INTO pl_task_series (tenant_id, engagement_id, title, owner_user_id, frequency, next_due_on)
           VALUES (1, {$engId}, 'Enter the weekly numbers', {$ownerId}, 'weekly', '" . date('Y-m-d', strtotime('-1 day')) . "')");

$created = AccountabilityLoop::rollRecurring(1);
T::same(1, $created, 'a due series creates an occurrence');

$occurrences = $tasks->all(['source' => 'recurring']);
T::same(1, count($occurrences), 'the occurrence is a real task');
T::same('Enter the weekly numbers', $occurrences[0]['title'], 'with the series title');

$series = $db->query('SELECT next_due_on FROM pl_task_series LIMIT 1')->fetch();
T::same(date('Y-m-d', strtotime('+6 days')), $series['next_due_on'], 'the series rolls forward a week');

T::same(0, AccountabilityLoop::rollRecurring(1), 'and does not fire again until it is due');

// A missed occurrence stays missed rather than being replaced.
AccountabilityLoop::sweep(1);
$occurrence = $tasks->find((int) $occurrences[0]['id']);
T::same(1, (int) $occurrence['miss_count'], 'the missed occurrence keeps its miss');


T::group('Completion rate');

$rate = $tasks->completionRate($engId);
T::ok($rate['due'] > 0, 'some tasks have come due');
T::ok($rate['rate'] !== null, 'a rate is available');
T::ok($rate['rate'] >= 0 && $rate['rate'] <= 100, 'and it is a percentage');

$freshEng = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Brand new', 'status' => 'active']);
$tasks->createTask(['engagement_id' => $freshEng, 'title' => 'Due next month', 'due_on' => date('Y-m-d', strtotime('+30 days'))]);
T::same(null, $tasks->completionRate($freshEng)['rate'], 'a rate is null until something has actually come due');


T::group('Comments');

$c1 = Comments::add(1, 'task', $missable, 'Struggling to get this from the bookkeeper.', ['id' => $ownerId, 'name' => 'An Owner']);
$c2 = Comments::add(1, 'task', $missable, 'Internal: owner is avoiding this.', ['id' => $coachId, 'name' => 'A Coach'], false);

T::same(2, count(Comments::forObject(1, 'task', $missable, false)), 'the coach sees both comments');
T::same(1, count(Comments::forObject(1, 'task', $missable, true)), 'the client sees only the visible one');

$clientSees = Comments::forObject(1, 'task', $missable, true);
T::ok(!str_contains(json_encode($clientSees), 'avoiding'), 'no internal text reaches the client');
T::same('An Owner', $clientSees[0]['author_label'], 'the author label is preserved on the comment');

T::throws(InvalidArgumentException::class, static fn () => Comments::add(1, 'task', $missable, '   ', null), 'an empty comment is refused');
T::throws(InvalidArgumentException::class, static fn () => Comments::add(1, 'nonsense', 1, 'hi', null), 'an unknown object type is refused');

T::ok(Comments::remove(1, $c1, $ownerId), 'an author can remove their own comment');
T::ok(!Comments::remove(1, $c2, $ownerId), "but not someone else's");
T::same(1, count(Comments::forObject(1, 'task', $missable, false)), 'the removed comment is gone from the thread');


T::group('The commitments agenda block — M6 pays M5 back');

AgendaBuilder::resetProviders();
$tpl = StarterSessionTemplates::installWorkingSession(1);
$sessionId = SessionService::schedule(1, $engId, 'Fortnightly', date('Y-m-d H:i:s'), $tpl, $coachId);
$session = SessionService::find(1, $sessionId);

$before = AgendaBuilder::build('commitments', 1, $session);
T::same([], $before['items'], 'unregistered, the block is empty');

AccountabilityLoop::registerAgendaProvider();

$after = AgendaBuilder::build('commitments', 1, $session);
T::ok(count($after['items']) > 0, 'registered, it lists what was promised');

$joined = implode(' | ', $after['items']);
T::ok(str_contains($joined, 'An Owner'), 'each commitment names its owner');
T::ok(str_contains($joined, 'MISSED'), 'and misses are called out plainly');

T::ok(!in_array('commitments', AgendaBuilder::unresolvedBlocks(1), true), 'commitments is no longer an unresolved block');


T::group('Tenant isolation');

$tasksB = new TaskRepository(2);
T::same(null, $tasksB->find($missable), "tenant B cannot read tenant A's task");
T::same([], $tasksB->forEngagement($engId), 'nor its engagement tasks');
T::same([], $tasksB->forOwner($ownerId), 'nor its owner list');
T::same([], $tasksB->overdue(), 'nor its overdue list');
T::same([], Comments::forObject(2, 'task', $missable, false), "nor its comments");
T::ok(!Comments::remove(2, $c2, $coachId), 'nor remove them');
T::same(['missed' => 0, 'escalated' => 0], AccountabilityLoop::sweep(2), "tenant B's sweep touches nothing of A's");
T::same(0, AccountabilityLoop::rollRecurring(2), "nor rolls A's series");
T::same([], AccountabilityLoop::dueForNudge(2), "nor nudges A's tasks");
T::same(null, $tasksB->completionRate($engId)['rate'], "nor reads A's completion rate");

AgendaBuilder::resetProviders();
