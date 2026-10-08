<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Services\Scorecard;

/** The scoreboard: priorities, numbers, and the issues list (M9). */
final class ScorecardController
{
    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);

        $tenantId = (int) $tenant['id'];
        $engagementId = (int) $engagement['id'];
        $clientSide = !$this->isFirmSide($user);

        return View::render('scorecard.show', [
            'title'      => 'Scoreboard',
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'grid'       => Scorecard::grid($tenantId, $engagementId),
            'goals'      => Scorecard::goals($tenantId, $engagementId),
            'load'       => Scorecard::goalLoad($tenantId, $engagementId),
            'issues'     => Scorecard::issues($tenantId, $engagementId),
            'missing'    => Scorecard::missingThisPeriod($tenantId, $engagementId),
            'people'     => $this->people($tenantId, $engagement),
            'quarter'    => Scorecard::quarterLabel(),
            'clientSide' => $clientSide,
            'canDefine'  => Policy::can($user, Policy::CREATE, 'metric_definition'),
            'canSetGoals'=> Policy::can($user, Policy::CREATE, 'goal'),
        ]);
    }

    /** @param array<string,string> $params */
    public function act(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $engagementId = (int) $engagement['id'];
        $ctx = $this->ctx($engagement);
        $action = (string) ($_POST['action'] ?? '');

        try {
            switch ($action) {
                case 'add_goal':
                    Policy::authorize($user, Policy::CREATE, 'goal', $ctx);
                    Scorecard::createGoal($tenantId, $engagementId, [
                        'title'            => (string) ($_POST['title'] ?? ''),
                        'success_criteria' => trim((string) ($_POST['success_criteria'] ?? '')) ?: null,
                        'owner_user_id'    => ((int) ($_POST['owner_user_id'] ?? 0)) ?: null,
                    ]);
                    break;

                case 'goal_status':
                    Policy::authorize($user, Policy::UPDATE, 'goal', $ctx);
                    $this->assertBelongs($tenantId, 'pl_goals', (int) ($_POST['goal_id'] ?? 0), $engagementId);
                    Scorecard::setGoalStatus($tenantId, (int) $_POST['goal_id'], (string) ($_POST['status'] ?? ''));
                    break;

                case 'add_metric':
                    Policy::authorize($user, Policy::CREATE, 'metric_definition', $ctx);
                    Scorecard::createMetric($tenantId, $engagementId, [
                        'name'          => (string) ($_POST['name'] ?? ''),
                        'unit'          => trim((string) ($_POST['unit'] ?? '')) ?: null,
                        'direction'     => (string) ($_POST['direction'] ?? 'higher'),
                        'target_value'  => ($_POST['target_value'] ?? '') === '' ? null : (float) $_POST['target_value'],
                        'frequency'     => (string) ($_POST['frequency'] ?? 'weekly'),
                        'owner_user_id' => ((int) ($_POST['owner_user_id'] ?? 0)) ?: null,
                    ]);
                    break;

                case 'record':
                    $metricId = (int) ($_POST['metric_id'] ?? 0);
                    $this->assertBelongs($tenantId, 'pl_metrics', $metricId, $engagementId);

                    $metric = Scorecard::metric($tenantId, $metricId);
                    $onBehalf = $this->isFirmSide($user)
                        && $metric !== null
                        && $metric['owner_user_id'] !== null
                        && (int) $metric['owner_user_id'] !== (int) $user['id'];

                    Policy::authorize($user, Policy::UPDATE, 'metric_value', $ctx + [
                        'owner_user_id' => $metric['owner_user_id'] === null ? null : (int) $metric['owner_user_id'],
                    ]);

                    if (($_POST['value'] ?? '') === '') {
                        throw new HttpException(422, 'A number is required.');
                    }

                    Scorecard::record(
                        $tenantId, $metricId,
                        trim((string) ($_POST['period_start'] ?? '')) ?: date('Y-m-d'),
                        (float) $_POST['value'],
                        (int) $user['id'],
                        $onBehalf,
                        trim((string) ($_POST['note'] ?? '')) ?: null
                    );
                    break;

                case 'raise_issue':
                    Policy::authorize($user, Policy::CREATE, 'issue', $ctx);
                    Scorecard::raiseIssue(
                        $tenantId, $engagementId,
                        (string) ($_POST['title'] ?? ''),
                        trim((string) ($_POST['detail'] ?? '')) ?: null,
                        'ad_hoc',
                        (int) $user['id'],
                        in_array($_POST['priority'] ?? '', ['low', 'normal', 'high'], true) ? (string) $_POST['priority'] : 'normal'
                    );
                    break;

                case 'resolve_issue':
                    $this->requireFirmSide($user);
                    Policy::authorize($user, Policy::UPDATE, 'issue', $ctx);
                    $this->assertBelongs($tenantId, 'pl_issues', (int) ($_POST['issue_id'] ?? 0), $engagementId);
                    Scorecard::resolveIssue(
                        $tenantId, (int) $_POST['issue_id'],
                        (string) ($_POST['identified'] ?? ''),
                        (string) ($_POST['discussed'] ?? ''),
                        (string) ($_POST['decided'] ?? ''),
                        (int) $user['id']
                    );
                    break;

                default:
                    throw new HttpException(422, 'Unknown action.');
            }
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/engagements/' . $engagementId . '/scoreboard'));
    }

    /** @param array<string,string> $params */
    public function rollover(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        $this->requireFirmSide($user);
        Csrf::check($_POST);
        Policy::authorize($user, Policy::UPDATE, 'goal', $this->ctx($engagement));

        $decisions = [];

        foreach (($_POST['decision'] ?? []) as $goalId => $decision) {
            if (in_array($decision, ['done', 'carry', 'drop'], true)) {
                $decisions[(int) $goalId] = (string) $decision;
            }
        }

        Scorecard::rollQuarter((int) $tenant['id'], (int) $engagement['id'], $decisions);

        redirect(url('/engagements/' . $engagement['id'] . '/scoreboard'));
    }

    // ------------------------------------------------------------- internals

    /**
     * A goal/metric/issue id from a form must belong to THIS engagement.
     * Without this, a coach with one engagement could drive another's by
     * changing a hidden field.
     */
    private function assertBelongs(int $tenantId, string $table, int $id, int $engagementId): void
    {
        // Table names are hardcoded call-site constants, never user input.
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM ' . $table . ' WHERE tenant_id = :tid AND id = :id AND engagement_id = :eid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $id, 'eid' => $engagementId]);

        if ($stmt->fetch() === false) {
            throw new HttpException(404, 'Not found.');
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function people(int $tenantId, array $engagement): array
    {
        $stmt = Database::conn()->prepare(
            "SELECT id, name, client_org_id FROM pl_users
             WHERE tenant_id = :tid AND status = 'active'
               AND (client_org_id IS NULL OR client_org_id = :org)
             ORDER BY client_org_id IS NULL DESC, name ASC"
        );
        $stmt->execute(['tid' => $tenantId, 'org' => (int) $engagement['client_org_id']]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed> */
    private function ctx(array $engagement): array
    {
        return ['client_org_id' => (int) $engagement['client_org_id']];
    }

    private function isFirmSide(array $user): bool
    {
        return ($user['client_org_id'] ?? null) === null;
    }

    private function requireFirmSide(array $user): void
    {
        if (!$this->isFirmSide($user)) {
            throw new HttpException(404, 'Not found.');
        }
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
