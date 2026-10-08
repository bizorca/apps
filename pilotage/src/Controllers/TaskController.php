<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Services\Comments;
use Bizorca\Pilotage\Services\Notifications;

/**
 * Tasks: my list, an engagement's list, and the detail view with comments.
 */
final class TaskController
{
    /** Everything owed by me, plus (for a coach) everything overdue across the book. */
    public function mine(): string
    {
        [$tenant, $user] = $this->context();

        $tasks = new TaskRepository();
        $firmSide = $this->isFirmSide($user);

        return View::render('tasks.mine', [
            'title'   => 'My tasks',
            'user'    => $user,
            'tenant'  => $tenant,
            'mine'    => $tasks->forOwner((int) $user['id']),
            'overdue' => $firmSide ? $tasks->overdue((int) $user['id']) : [],
            'firmSide' => $firmSide,
        ]);
    }

    /** @param array<string,string> $params */
    public function forEngagement(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);

        $tasks = new TaskRepository();
        $clientSide = !$this->isFirmSide($user);

        $rows = $tasks->forEngagement((int) $engagement['id'], $clientSide);

        // Group subtasks under their parents for display.
        $tree = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $tree[(int) $row['id']] = $row + ['children' => []];
            }
        }
        foreach ($rows as $row) {
            $parent = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            if ($parent !== null && isset($tree[$parent])) {
                $tree[$parent]['children'][] = $row;
            }
        }

        // FR-6.8: list, kanban and calendar over the same data. The view is a
        // presentation choice, so it lives in the query string rather than in
        // three near-identical controller actions.
        $view = (string) ($_GET['view'] ?? 'list');

        if (!in_array($view, ['list', 'board', 'calendar'], true)) {
            $view = 'list';
        }

        $month = (string) ($_GET['month'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        return View::render('tasks.index', [
            'title'      => 'Tasks',
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'tree'       => array_values($tree),
            'rows'       => $rows,
            'view'       => $view,
            'month'      => $month,
            'board'      => $this->board($rows),
            'calendar'   => $this->calendar($rows, $month),
            'people'     => $this->people((int) $tenant['id'], $engagement),
            'rate'       => $tasks->completionRate((int) $engagement['id']),
            'canAssign'  => Policy::can($user, Policy::CREATE, 'task', ['client_org_id' => (int) $engagement['client_org_id']]),
        ]);
    }

    /** @param array<string,string> $params */
    public function store(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        Csrf::check($_POST);
        Policy::authorize($user, Policy::CREATE, 'task', ['client_org_id' => (int) $engagement['client_org_id']]);

        $tasks = new TaskRepository();

        $owner = (int) ($_POST['owner_user_id'] ?? 0);

        try {
            $taskId = $tasks->createTask([
                'engagement_id'      => (int) $engagement['id'],
                'parent_id'          => ((int) ($_POST['parent_id'] ?? 0)) ?: null,
                'title'              => (string) ($_POST['title'] ?? ''),
                'definition_of_done' => trim((string) ($_POST['definition_of_done'] ?? '')) ?: null,
                'owner_user_id'      => $owner ?: null,
                'assigned_by'        => (int) $user['id'],
                'due_on'             => trim((string) ($_POST['due_on'] ?? '')) ?: null,
                'priority'           => (string) ($_POST['priority'] ?? 'normal'),
                'evidence_required'  => (string) ($_POST['evidence_required'] ?? 'none'),
                'source'             => 'ad_hoc',
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        // An owner should be told they have been given something to do. An
        // unowned task is a note to the firm, and nobody is notified about it.
        if ($owner > 0 && $owner !== (int) $user['id']) {
            Notifications::queue(
                (int) $tenant['id'], $owner, 'task.assigned',
                $user['name'] . ' assigned you "' . (string) ($_POST['title'] ?? '') . '"',
                trim((string) ($_POST['due_on'] ?? '')) !== ''
                    ? 'Due ' . date('j F', strtotime((string) $_POST['due_on'])) . '.'
                    : null,
                '/tasks/' . $taskId,
                [
                    'client_org_id' => (int) $engagement['client_org_id'],
                    'object_type'   => 'task',
                    'object_id'     => $taskId,
                ]
            );
        }

        redirect(url('/engagements/' . $engagement['id'] . '/tasks'));
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->context();

        $tasks = new TaskRepository();
        $task = $tasks->find((int) ($params['id'] ?? 0));

        if ($task === null) {
            throw new HttpException(404, 'No such task.');
        }

        $engagements = new EngagementRepository();
        $engagement = $engagements->find((int) $task['engagement_id']);

        if ($engagement === null) {
            throw new HttpException(404, 'No such task.');
        }

        $clientSide = !$this->isFirmSide($user);

        if ($clientSide) {
            if ((int) $user['client_org_id'] !== (int) $engagement['client_org_id'] || (int) $task['client_visible'] !== 1) {
                throw new HttpException(404, 'No such task.');
            }
        }

        Policy::authorize($user, Policy::READ, 'task', [
            'client_org_id' => (int) $engagement['client_org_id'],
            'user_id'       => $task['owner_user_id'] === null ? null : (int) $task['owner_user_id'],
        ]);

        return View::render('tasks.show', [
            'title'      => (string) $task['title'],
            'user'       => $user,
            'tenant'     => $tenant,
            'task'       => $task,
            'engagement' => $engagement,
            'subtasks'   => $tasks->subtasks((int) $task['id']),
            'comments'   => Comments::forObject((int) $tenant['id'], 'task', (int) $task['id'], $clientSide),
            'clientSide' => $clientSide,
            // Tags are a firm-side organising tool. A client seeing "renewal
            // risk" on their own task would be told something not meant for them.
            'tags'       => $clientSide ? [] : \Bizorca\Pilotage\Services\Tags::forObject((int) $tenant['id'], 'task', (int) $task['id']),
            'tagString'  => $clientSide ? '' : \Bizorca\Pilotage\Services\Tags::stringFor((int) $tenant['id'], 'task', (int) $task['id']),
        ]);
    }

    /** @param array<string,string> $params */
    public function act(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tasks = new TaskRepository();
        $task = $tasks->find((int) ($params['id'] ?? 0));

        if ($task === null) {
            throw new HttpException(404, 'No such task.');
        }

        $engagements = new EngagementRepository();
        $engagement = $engagements->find((int) $task['engagement_id']);

        if ($engagement === null) {
            throw new HttpException(404, 'No such task.');
        }

        $clientSide = !$this->isFirmSide($user);

        if ($clientSide && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'No such task.');
        }

        $context = [
            'client_org_id' => (int) $engagement['client_org_id'],
            'user_id'       => $task['owner_user_id'] === null ? null : (int) $task['owner_user_id'],
        ];

        $action = (string) ($_POST['action'] ?? '');

        try {
            switch ($action) {
                case 'complete':
                    Policy::authorize($user, Policy::UPDATE, 'task', $context);
                    $tasks->complete((int) $task['id'], (int) $user['id'], trim((string) ($_POST['evidence'] ?? '')) ?: null);

                    // The coach hears about it in tomorrow's digest. This is
                    // the single highest-volume event in the product, which is
                    // exactly why it is not an email each time.
                    if ($clientSide) {
                        Notifications::queueMany(
                            (int) $tenant['id'],
                            Notifications::firmRecipients((int) $tenant['id'], (int) $engagement['id']),
                            'task.completed',
                            $user['name'] . ' finished "' . (string) $task['title'] . '"',
                            null,
                            '/tasks/' . (int) $task['id'],
                            [
                                'client_org_id' => (int) $engagement['client_org_id'],
                                'object_type'   => 'task',
                                'object_id'     => (int) $task['id'],
                            ],
                            (int) $user['id']
                        );
                    }
                    break;

                case 'reopen':
                    Policy::authorize($user, Policy::UPDATE, 'task', $context);
                    $tasks->reopen((int) $task['id']);
                    break;

                case 'tags':
                    // Firm-side only, deliberately: see the note on the read path.
                    if ($clientSide) {
                        throw new HttpException(404, 'Not found.');
                    }
                    Policy::authorize($user, Policy::UPDATE, 'task', $context);
                    \Bizorca\Pilotage\Services\Tags::sync(
                        (int) $tenant['id'], 'task', (int) $task['id'],
                        (string) ($_POST['tags'] ?? ''), (int) $user['id']
                    );
                    break;

                case 'comment':
                    Policy::authorize($user, Policy::READ, 'task', $context);
                    Comments::add(
                        (int) $tenant['id'],
                        'task',
                        (int) $task['id'],
                        (string) ($_POST['body'] ?? ''),
                        $user,
                        // Only firm-side users may leave an internal note.
                        $clientSide ? true : empty($_POST['internal'])
                    );
                    break;

                default:
                    throw new HttpException(422, 'Unknown action.');
            }
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/tasks/' . $task['id']));
    }

    // ------------------------------------------------------------- internals

    /**
     * Group tasks into board columns.
     *
     * The columns are the task lifecycle, not user-defined lists. Letting a
     * coach invent columns is the first step toward a worse Asana, which
     * SPEC.md §11 rules out; the value here is seeing WHERE work is stuck, and
     * a fixed spine shows that more honestly than a bespoke one.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array{label:string, tasks:array<int,array<string,mixed>>}>
     */
    private function board(array $rows): array
    {
        $columns = [
            'overdue'     => ['label' => 'Overdue',     'tasks' => []],
            'open'        => ['label' => 'Not started', 'tasks' => []],
            'in_progress' => ['label' => 'In progress', 'tasks' => []],
            'done'        => ['label' => 'Done',        'tasks' => []],
        ];

        $today = date('Y-m-d');

        foreach ($rows as $row) {
            // Subtasks ride with their parent rather than cluttering the board.
            if ($row['parent_id'] !== null) {
                continue;
            }

            $status = (string) $row['status'];

            if ($status === 'cancelled') {
                continue;
            }

            $key = match (true) {
                $status === 'done' => 'done',
                $row['due_on'] !== null && (string) $row['due_on'] < $today => 'overdue',
                $status === 'in_progress' => 'in_progress',
                default => 'open',
            };

            $columns[$key]['tasks'][] = $row;
        }

        return $columns;
    }

    /**
     * A month grid of tasks by due date.
     *
     * Undated tasks are returned separately rather than dropped. A commitment
     * with no date is the single most likely one to be forgotten, so hiding it
     * from the calendar would hide exactly what needs seeing.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{weeks:array<int,array<int,array<string,mixed>>>, undated:array<int,array<string,mixed>>, month:string}
     */
    private function calendar(array $rows, string $month): array
    {
        $byDate = [];
        $undated = [];

        foreach ($rows as $row) {
            if ((string) $row['status'] === 'cancelled') {
                continue;
            }

            if ($row['due_on'] === null) {
                if ($row['parent_id'] === null) {
                    $undated[] = $row;
                }
                continue;
            }

            $byDate[(string) $row['due_on']][] = $row;
        }

        $first = $month . '-01';
        $daysInMonth = (int) date('t', strtotime($first));

        // Monday-anchored weeks, matching the scorecard's period maths.
        $leading = (int) date('N', strtotime($first)) - 1;

        $cells = [];

        for ($i = 0; $i < $leading; $i++) {
            $cells[] = ['date' => null, 'tasks' => []];
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%s-%02d', $month, $d);
            $cells[] = [
                'date'  => $date,
                'day'   => $d,
                'today' => $date === date('Y-m-d'),
                'tasks' => $byDate[$date] ?? [],
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = ['date' => null, 'tasks' => []];
        }

        return [
            'weeks'   => array_chunk($cells, 7),
            'undated' => $undated,
            'month'   => $month,
        ];
    }

    /** @return array<int,array<string,mixed>> Everyone who could own a task here. */
    private function people(int $tenantId, array $engagement): array
    {
        $stmt = \Bizorca\Pilotage\Core\Database::conn()->prepare(
            "SELECT id, name, role, client_org_id FROM pl_users
             WHERE tenant_id = :tid AND status = 'active'
               AND (client_org_id IS NULL OR client_org_id = :org)
             ORDER BY client_org_id IS NULL DESC, name ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'org' => (int) $engagement['client_org_id']]);

        return $stmt->fetchAll();
    }

    private function isFirmSide(array $user): bool
    {
        return ($user['client_org_id'] ?? null) === null;
    }

    /** @param array<string,string> $params @return array{0:array,1:array,2:array} */
    private function engagementContext(array $params): array
    {
        [$tenant, $user] = $this->context();

        $engagements = new EngagementRepository();
        $engagement = $engagements->find((int) ($params['id'] ?? 0));

        if ($engagement === null) {
            throw new HttpException(404, 'No such engagement.');
        }

        if (!$this->isFirmSide($user) && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'No such engagement.');
        }

        Policy::authorize($user, Policy::READ, 'engagement', $engagements->policyContext($engagement));

        return [$tenant, $user, $engagement];
    }

    /** @return array{0:array,1:array} */
    private function context(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        return [$tenant, $user];
    }
}
