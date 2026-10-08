<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Services\Cohorts;
use Bizorca\Pilotage\Services\Messaging;
use Bizorca\Pilotage\Services\PlaybookRunner;
use Bizorca\Pilotage\Services\Scorecard;
use Bizorca\Pilotage\Services\SessionService;

/**
 * The two front doors (FR-12.1, FR-12.4).
 *
 * The coach dashboard answers "what needs me today" — sessions, overdue
 * commitments, clients whose rhythm has slipped. The client dashboard answers
 * "what do I owe and where are we", on one screen with no navigation.
 */
final class DashboardController
{
    public function home(): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            // The apex is the marketing site, and the router sends it to
            // MarketingController before it gets here. Reaching this branch
            // means something bypassed that, and rendering a dashboard shell
            // with no tenant is not a recovery worth attempting.
            throw new HttpException(404, 'Not found.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login'));
        }

        return ($user['client_org_id'] ?? null) === null
            ? $this->coach($tenant, $user)
            : $this->client($tenant, $user);
    }

    /** @param array<string,mixed> $tenant @param array<string,mixed> $user */
    private function coach(array $tenant, array $user): string
    {
        $tenantId = (int) $tenant['id'];
        $tasks = new TaskRepository($tenantId);
        $db = Database::conn();

        $today = $db->prepare(
            "SELECT s.*, e.title AS engagement_title, o.name AS org_name
             FROM pl_sessions s
             JOIN pl_engagements e ON e.id = s.engagement_id
             JOIN pl_client_orgs o ON o.id = e.client_org_id
             WHERE s.tenant_id = :tid AND s.status IN ('scheduled','in_progress')
               AND s.scheduled_at IS NOT NULL AND s.scheduled_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
             ORDER BY s.scheduled_at ASC LIMIT 10"
        );
        $today->execute(['tid' => $tenantId]);

        $active = $db->prepare(
            "SELECT e.*, o.name AS org_name FROM pl_engagements e
             JOIN pl_client_orgs o ON o.id = e.client_org_id
             WHERE e.tenant_id = :tid AND e.status = 'active'
             ORDER BY o.name ASC"
        );
        $active->execute(['tid' => $tenantId]);
        $engagements = $active->fetchAll();

        // A light health read per engagement: kept rate, and where the process is.
        foreach ($engagements as $i => $eng) {
            $engagements[$i]['completion'] = $tasks->completionRate((int) $eng['id']);

            $repo = new EngagementRepository($tenantId);
            $instance = $repo->playbookInstance((int) $eng['id']);
            $engagements[$i]['progress'] = $instance === null
                ? null
                : PlaybookRunner::progress($tenantId, (int) $instance['id']);
        }

        return View::render('dashboard.coach', [
            'needsOnboarding' => $tenant['onboarded_at'] === null && ($user['role'] ?? '') === 'firm_owner',
            'title'       => 'Dashboard',
            'user'        => $user,
            'tenant'      => $tenant,
            'sessions'    => $today->fetchAll(),
            'overdue'     => $tasks->overdue(),
            'mine'        => $tasks->forOwner((int) $user['id']),
            'slipped'     => SessionService::cadenceSlipped($tenantId),
            'engagements' => $engagements,
            'unread'      => Messaging::unreadCount($tenantId, (int) $user['id'], false),
            'mentions'    => count(Messaging::unreadMentions($tenantId, (int) $user['id'])),
        ]);
    }

    /** @param array<string,mixed> $tenant @param array<string,mixed> $user */
    private function client(array $tenant, array $user): string
    {
        $tenantId = (int) $tenant['id'];
        $orgId = (int) $user['client_org_id'];

        $db = Database::conn();

        $stmt = $db->prepare(
            "SELECT * FROM pl_engagements
             WHERE tenant_id = :tid AND client_org_id = :org AND status = 'active'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'org' => $orgId]);
        $engagement = $stmt->fetch();

        if ($engagement === false) {
            return View::render('dashboard.client', [
                'title' => 'Welcome', 'user' => $user, 'tenant' => $tenant,
                'engagement' => null, 'mine' => [], 'progress' => null,
                'next' => null, 'goals' => [], 'missing' => [], 'cohorts' => [],
            ]);
        }

        $engagementId = (int) $engagement['id'];
        $repo = new EngagementRepository($tenantId);
        $instance = $repo->playbookInstance($engagementId);

        $next = $db->prepare(
            "SELECT * FROM pl_sessions
             WHERE tenant_id = :tid AND engagement_id = :eid AND status = 'scheduled'
               AND scheduled_at >= NOW()
             ORDER BY scheduled_at ASC LIMIT 1"
        );
        $next->execute(['tid' => $tenantId, 'eid' => $engagementId]);

        $tasks = new TaskRepository($tenantId);

        // Only metrics this user actually owns — a nag for someone else's
        // number is noise.
        $missing = array_values(array_filter(
            Scorecard::missingThisPeriod($tenantId, $engagementId),
            static fn (array $m): bool => (int) ($m['owner_user_id'] ?? 0) === (int) $user['id']
        ));

        return View::render('dashboard.client', [
            'title'      => (string) $engagement['title'],
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'mine'       => $tasks->forOwner((int) $user['id']),
            'progress'   => $instance === null ? null : PlaybookRunner::progress($tenantId, (int) $instance['id']),
            'next'       => $next->fetch() ?: null,
            'goals'      => Scorecard::goals($tenantId, $engagementId),
            'missing'    => $missing,
            // Any group this client is part of. Names and the shared surface
            // only — Cohorts decides what a member may see of its peers.
            'cohorts'    => Cohorts::forEngagement($tenantId, $engagementId),
        ]);
    }
}
