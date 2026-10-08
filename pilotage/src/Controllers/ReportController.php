<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Services\Health;
use Bizorca\Pilotage\Services\Reports;

/**
 * Reporting screens (M12): the firm roll-up, engagement health, and the period
 * report a coach takes into a renewal conversation.
 */
final class ReportController
{
    /** The firm dashboard (FR-12.3). */
    public function firm(): string
    {
        [$tenant, $user] = $this->firmContext();

        // Utilization and playbook performance across the whole book is
        // owner-level information. A coach reads their own engagements on the
        // coach dashboard; how they compare to their colleagues is not theirs
        // to see.
        Policy::authorize($user, Policy::READ, 'tenant_settings');

        return View::render('reports.firm', [
            'title'  => 'Firm',
            'user'   => $user,
            'tenant' => $tenant,
            // Not 'data': View::capture() extracts with EXTR_SKIP, so a key
            // named after its own $data parameter never reaches the template,
            // and the whole firm dashboard rendered from nulls.
            'report' => Reports::firmDashboard((int) $tenant['id']),
        ]);
    }

    /**
     * Health for one engagement, with its factors (FR-12.2).
     *
     * @param array<string,string> $params
     */
    public function health(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);

        return View::render('reports.health', [
            'title'      => 'Health',
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'health'     => Health::forEngagement((int) $tenant['id'], (int) $engagement['id']),
        ]);
    }

    /**
     * The period report (FR-12.5).
     *
     * @param array<string,string> $params
     */
    public function engagement(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        [$from, $to] = $this->period();

        return View::render('reports.engagement', [
            'title'      => 'Report',
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'report'     => Reports::engagementReport((int) $tenant['id'], (int) $engagement['id'], $from, $to),
            'print'      => isset($_GET['print']),
        ], isset($_GET['print']) ? null : 'layout');
    }

    /**
     * The same report as CSV (FR-12.6).
     *
     * @param array<string,string> $params
     */
    public function engagementCsv(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        [$from, $to] = $this->period();

        $report = Reports::engagementReport((int) $tenant['id'], (int) $engagement['id'], $from, $to);

        $name = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $engagement['org_name']) ?? 'report';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . trim($name, '-') . '-' . $from . '-to-' . $to . '.csv"');
        header('Cache-Control: private, no-store');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo Reports::toCsv($report);
        exit;
    }

    // ------------------------------------------------------------- internals

    /**
     * The requested period, defaulting to the current quarter to date.
     *
     * Both ends are validated as dates and swapped if reversed, rather than
     * being passed through to SQL as whatever arrived in the query string.
     *
     * @return array{0:string, 1:string}
     */
    private function period(): array
    {
        [$defaultFrom, $defaultTo] = Reports::defaultPeriod();

        $from = $this->date($_GET['from'] ?? null) ?? $defaultFrom;
        $to = $this->date($_GET['to'] ?? null) ?? $defaultTo;

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $parsed = date_create_immutable($value);

        return $parsed === false ? null : $parsed->format('Y-m-d');
    }

    /**
     * Reports are firm-side. A client sees their own progress on their
     * dashboard; a report about them, with health scoring and utilization
     * context, is the coach's working document and not a client-facing one.
     *
     * @param array<string,string> $params
     * @return array{0:array,1:array,2:array}
     */
    private function engagementContext(array $params): array
    {
        [$tenant, $user] = $this->firmContext();

        $engagements = new EngagementRepository((int) $tenant['id']);
        $engagement = $engagements->find((int) ($params['id'] ?? 0));

        if ($engagement === null) {
            throw new HttpException(404, 'No such engagement.');
        }

        Policy::authorize($user, Policy::READ, 'engagement', [
            'client_org_id' => (int) $engagement['client_org_id'],
        ]);

        $org = (new \Bizorca\Pilotage\Repositories\ClientOrgRepository((int) $tenant['id']))
            ->find((int) $engagement['client_org_id']);

        $engagement['org_name'] = $org['name'] ?? '';

        return [$tenant, $user, $engagement];
    }

    /** @return array{0:array,1:array} */
    private function firmContext(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }

        return [$tenant, $user];
    }
}
