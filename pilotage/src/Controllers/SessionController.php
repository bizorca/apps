<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Mailer;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Services\Ics;
use Bizorca\Pilotage\Services\Notifications;
use Bizorca\Pilotage\Services\SessionService;
use Bizorca\Pilotage\Services\Timeline;

/**
 * Sessions, including the runner — the screen a coach drives during a live
 * meeting. If that screen is good, the product is good (SPEC.md §9).
 */
final class SessionController
{
    /** @param array<string,string> $params */
    public function index(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);

        return View::render('sessions.index', [
            'title'      => 'Sessions',
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'sessions'   => SessionService::forEngagement((int) $tenant['id'], (int) $engagement['id']),
            'templates'  => $this->templates((int) $tenant['id']),
            'canRun'     => $this->isFirmSide($user),
        ]);
    }

    /** @param array<string,string> $params */
    public function schedule(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        $this->requireFirmSide($user);
        Csrf::check($_POST);
        Policy::authorize($user, Policy::CREATE, 'session');

        $templateId = (int) ($_POST['template_id'] ?? 0);
        $when = trim((string) ($_POST['scheduled_at'] ?? ''));

        try {
            $id = SessionService::schedule(
                (int) $tenant['id'],
                (int) $engagement['id'],
                (string) ($_POST['title'] ?? ''),
                $when === '' ? null : date('Y-m-d H:i:s', strtotime($when)),
                $templateId > 0 ? $templateId : null,
                (int) $user['id'],
                trim((string) ($_POST['location'] ?? '')) ?: null
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/sessions/' . $id));
    }

    /**
     * The runner. Coach-side gets the timed agenda, both note panes, and the
     * controls. Client-side gets shared notes and a sent recap, nothing else.
     *
     * @param array<string,string> $params
     */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->context();
        $tenantId = (int) $tenant['id'];
        $sessionId = (int) ($params['id'] ?? 0);

        $bare = SessionService::find($tenantId, $sessionId);

        if ($bare === null) {
            throw new HttpException(404, 'No such session.');
        }

        $clientSide = !$this->isFirmSide($user);

        if ($clientSide && (int) $user['client_org_id'] !== (int) $bare['client_org_id']) {
            throw new HttpException(404, 'No such session.');
        }

        // A client team member only reads sessions they attended (FR-5.4, §8).
        $attendeeIds = [];
        foreach (SessionService::attendees($tenantId, $sessionId) as $a) {
            if ($a['user_id'] !== null) {
                $attendeeIds[] = (int) $a['user_id'];
            }
        }
        Policy::authorize($user, Policy::READ, 'session', ['attendee_user_ids' => $attendeeIds]);

        $session = $clientSide
            ? SessionService::forClient($tenantId, $sessionId)
            : SessionService::forCoach($tenantId, $sessionId, (int) $user['id']);

        return View::render('sessions.runner', [
            'title'      => (string) $session['title'],
            'user'       => $user,
            'tenant'     => $tenant,
            'session'    => $session,
            'clientSide' => $clientSide,
            'canRun'     => !$clientSide && Policy::can($user, Policy::UPDATE, 'session'),
        ]);
    }

    /** @param array<string,string> $params */
    public function act(array $params): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $sessionId = (int) ($params['id'] ?? 0);
        $session = SessionService::find($tenantId, $sessionId);

        if ($session === null) {
            throw new HttpException(404, 'No such session.');
        }

        Policy::authorize($user, Policy::UPDATE, 'session');

        $action = (string) ($_POST['action'] ?? '');

        switch ($action) {
            case 'start':
                SessionService::start($tenantId, $sessionId);
                break;

            case 'save_notes':
                SessionService::saveSharedNotes($tenantId, $sessionId, (string) ($_POST['shared'] ?? ''), (int) $user['id']);
                SessionService::savePrivateNotes($tenantId, $sessionId, (string) ($_POST['private'] ?? ''), (int) $user['id']);
                break;

            case 'cover':
                $itemId = (int) ($_POST['item_id'] ?? 0);
                Database::conn()->prepare(
                    'UPDATE pl_session_agenda_items
                     SET covered_at = IF(covered_at IS NULL, NOW(), NULL)
                     WHERE tenant_id = :tid AND id = :id AND session_id = :sid'
                )->execute(['tid' => $tenantId, 'id' => $itemId, 'sid' => $sessionId]);
                break;

            case 'close':
                SessionService::close($tenantId, $sessionId);
                Timeline::record(
                    $tenantId,
                    (int) $session['client_org_id'],
                    'session.held',
                    $session['title'] . ' held',
                    $user
                );
                break;

            case 'save_recap':
                SessionService::saveRecap($tenantId, $sessionId, (string) ($_POST['recap'] ?? ''));
                break;

            case 'send_recap':
                $this->sendRecap($tenantId, $session, $user);
                break;

            default:
                throw new HttpException(422, 'Unknown action.');
        }

        redirect(url('/sessions/' . $sessionId));
    }

    /** @param array<string,string> $params */
    public function ics(array $params): string
    {
        [$tenant, $user] = $this->context();
        $tenantId = (int) $tenant['id'];

        $session = SessionService::find($tenantId, (int) ($params['id'] ?? 0));

        if ($session === null) {
            throw new HttpException(404, 'No such session.');
        }

        if (!$this->isFirmSide($user) && (int) $user['client_org_id'] !== (int) $session['client_org_id']) {
            throw new HttpException(404, 'No such session.');
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="session-' . (int) $session['id'] . '.ics"');

        return Ics::forSession(
            $session,
            (string) \Bizorca\Pilotage\Core\Config::get('mail.from_addr'),
            (string) $tenant['name']
        );
    }

    // ------------------------------------------------------------- internals

    /** @param array<string,mixed> $session */
    private function sendRecap(int $tenantId, array $session, array $user): void
    {
        $recap = SessionService::recap($tenantId, (int) $session['id']);

        if ($recap === null || $recap['status'] !== 'draft') {
            return;
        }

        if (!SessionService::markRecapSent($tenantId, (int) $session['id'], (int) $user['id'])) {
            return;
        }

        // Queued rather than sent inline (M11). The recap is transactional, so
        // it reaches every client-side attendee regardless of preference — but
        // it goes through the same pipe as everything else, which is what makes
        // it appear in the reader's inbox as well as their mail.
        $recipients = [];

        foreach (SessionService::attendees($tenantId, (int) $session['id']) as $attendee) {
            if ($attendee['user_id'] === null) {
                continue;
            }

            $stmt = Database::conn()->prepare(
                'SELECT id FROM pl_users
                 WHERE tenant_id = :tid AND id = :id AND client_org_id IS NOT NULL AND status = \'active\' LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'id' => (int) $attendee['user_id']]);
            $row = $stmt->fetch();

            if ($row !== false) {
                $recipients[] = (int) $row['id'];   // firm-side attendees get no client recap
            }
        }

        Notifications::queueMany(
            $tenantId,
            $recipients,
            'session.recap',
            'Recap from ' . (string) $session['title'],
            (string) $recap['body'],
            '/sessions/' . (int) $session['id'],
            [
                'client_org_id' => (int) $session['client_org_id'],
                'object_type'   => 'session',
                'object_id'     => (int) $session['id'],
            ],
            (int) $user['id']
        );

        Timeline::record(
            $tenantId,
            (int) $session['client_org_id'],
            'session.recap_sent',
            'Recap sent for ' . $session['title'],
            $user
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function templates(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_session_templates WHERE tenant_id = :tid ORDER BY name ASC'
        );
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
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
