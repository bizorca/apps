<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Invitation;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\ClientIp;
use Bizorca\Pilotage\Services\Billing;
use Bizorca\Pilotage\Services\Entitlements;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Mailer;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\StarterPlaybooks;
use Bizorca\Pilotage\Services\StarterSessionTemplates;

/**
 * Firm settings: branding, seats, onboarding, and the data export (M1).
 *
 * Every action here is firm-owner only, which is the one place in the product
 * where "is the owner" genuinely is the whole check — seats are billing, and
 * branding is the firm's identity.
 */
final class FirmController
{
    public function settings(): string
    {
        [$tenant, $user] = $this->owner();

        $users = new UserRepository((int) $tenant['id']);

        return View::render('firm.settings', [
            'title'   => 'Firm settings',
            'user'    => $user,
            'tenant'  => $tenant,
            'staff'   => $users->firmSide(),
            'pending' => Invitation::pending((int) $tenant['id']),
            // Shown before somebody hits the limit, not only after. A ceiling
            // you discover by bouncing off it reads as a bug.
            'sendingNotice' => \Bizorca\Pilotage\Services\SendingTrust::notice((int) $tenant['id']),
        ]);
    }

    public function updateBranding(): string
    {
        [$tenant, $user] = $this->owner();
        Csrf::check($_POST);

        $problems = [];
        $primary = trim((string) ($_POST['primary_color'] ?? ''));
        $accent  = trim((string) ($_POST['accent_color'] ?? ''));
        $logo    = trim((string) ($_POST['logo_url'] ?? ''));

        foreach (['primary' => $primary, 'accent' => $accent] as $label => $hex) {
            if ($hex !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                $problems[] = ucfirst($label) . ' colour should look like #1a2b3c.';
            }
        }

        // A logo URL is rendered into an <img src>. Only https, so a client is
        // never asked to load a firm's branding over plaintext, and never a
        // javascript: or data: URL.
        if ($logo !== '' && !preg_match('#^https://#i', $logo)) {
            $problems[] = 'The logo URL must start with https://.';
        }

        if ($problems !== []) {
            throw new HttpException(422, implode(' ', $problems));
        }

        Database::conn()->prepare(
            'UPDATE pl_tenants
             SET name = :name, logo_url = :logo, primary_color = :primary, accent_color = :accent,
                 mail_from_name = :from, support_email = :support
             WHERE id = :id'
        )->execute([
            'name'    => trim((string) ($_POST['name'] ?? $tenant['name'])) ?: $tenant['name'],
            'logo'    => $logo ?: null,
            'primary' => $primary ?: null,
            'accent'  => $accent ?: null,
            'from'    => trim((string) ($_POST['mail_from_name'] ?? '')) ?: null,
            'support' => trim((string) ($_POST['support_email'] ?? '')) ?: null,
            'id'      => (int) $tenant['id'],
        ]);

        Audit::record('tenant.branding_updated', (int) $tenant['id'], $user, 'tenant', (int) $tenant['id'],
            null, ClientIp::resolve($_SERVER));

        redirect(url('/firm'));
    }

    /** Invite a colleague. A seat is a billing decision, hence owner-only. */
    public function inviteStaff(): string
    {
        [$tenant, $user] = $this->owner();
        Csrf::check($_POST);
        Policy::authorize($user, Policy::CREATE, 'user');

        $role = (string) ($_POST['role'] ?? 'coach');

        if (!in_array($role, UserRepository::FIRM_SIDE_ROLES, true)) {
            throw new HttpException(422, 'That is not a firm-side role.');
        }

        // Through Entitlements::canAddSeat rather than comparing the numbers
        // here. This used to do its own arithmetic, which meant beta lifted the
        // limit everywhere except the one screen that enforces it — a firm
        // being told the product is free and unlimited, and then refused a
        // second seat. Anything that gates on a plan asks Entitlements.
        $limit = Entitlements::seatLimit($tenant);

        if (!Entitlements::canAddSeat($tenant)) {
            throw new HttpException(403,
                'The ' . Entitlements::plan($tenant)['name'] . ' plan includes ' . $limit
                . ' advisor seat' . ($limit === 1 ? '' : 's') . ', and they are all in use. '
                . 'Moving up a plan adds more; your clients are never counted as seats.');
        }

        // Checked here too, so a refusal arrives before any work is done. The
        // check that actually guarantees it lives in Invitation::issue().
        \Bizorca\Pilotage\Services\SendingTrust::assertMayInvite((int) $tenant['id']);

        try {
            $invite = Invitation::issue(
                (int) $tenant['id'], (string) $tenant['slug'],
                (string) ($_POST['email'] ?? ''), $role, null,
                trim((string) ($_POST['name'] ?? '')) ?: null, (int) $user['id']
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        // A colleague joining the firm is firm-side, so this comes from us.
        $body = \Bizorca\Pilotage\Services\MailTemplate::action(
            'Hello' . (trim((string) ($_POST['name'] ?? '')) !== '' ? ' ' . trim((string) $_POST['name']) : '') . ',',
            [
                $user['name'] . ' has invited you to join ' . $tenant['name'] . ' on Pilotage, '
                    . 'the workspace they use to run client engagements.',
                'Setting up takes a minute. You will be asked to choose a password.',
            ],
            'Accept your invitation',
            $invite['url'],
            ['The link is good for seven days.'],
            Mailer::PLATFORM_NAME
        );

        Mailer::send(
            (string) $_POST['email'],
            trim((string) ($_POST['name'] ?? '')) ?: (string) $_POST['email'],
            $user['name'] . ' invited you to ' . $tenant['name'] . ' on Pilotage',
            $body['text'],
            $body['html'],
            Mailer::PLATFORM_NAME
        );

        Audit::record(Audit::INVITATION_SENT, (int) $tenant['id'], $user, null, null,
            ['email' => $_POST['email'], 'role' => $role], ClientIp::resolve($_SERVER));

        redirect(url('/firm'));
    }

    public function disableStaff(): string
    {
        [$tenant, $user] = $this->owner();
        Csrf::check($_POST);

        $targetId = (int) ($_POST['user_id'] ?? 0);

        if ($targetId === (int) $user['id']) {
            throw new HttpException(422, 'You cannot disable your own account.');
        }

        $users = new UserRepository((int) $tenant['id']);
        $target = $users->find($targetId);

        if ($target === null || $target['client_org_id'] !== null) {
            throw new HttpException(404, 'No such colleague.');
        }

        $before = Billing::seatsInUse((int) $tenant['id']);

        $users->update($targetId, ['status' => 'disabled']);
        Session::revokeAllForUser((int) $tenant['id'], $targetId);

        // A seat freed is a billable quantity changed. Recorded even when
        // Stripe is not configured, so "why did my bill change" is answerable
        // from our own data rather than by reading invoices line by line.
        Billing::recordSeatChange(
            (int) $tenant['id'], $before, Billing::seatsInUse((int) $tenant['id']),
            'Disabled ' . (string) $target['name'], (int) $user['id']
        );

        Audit::record(Audit::USER_DISABLED, (int) $tenant['id'], $user, 'user', $targetId,
            null, ClientIp::resolve($_SERVER));

        redirect(url('/firm'));
    }

    /** Turn the public enquiry form on or off, and set its copy. */
    public function updateIntake(): string
    {
        [$tenant, $user] = $this->owner();
        Csrf::check($_POST);

        Database::conn()->prepare(
            'UPDATE pl_tenants SET intake_enabled = :on, intake_headline = :head, intake_blurb = :blurb
             WHERE id = :id'
        )->execute([
            'on'    => empty($_POST['intake_enabled']) ? 0 : 1,
            'head'  => mb_substr(trim((string) ($_POST['intake_headline'] ?? '')), 0, 255) ?: null,
            'blurb' => trim((string) ($_POST['intake_blurb'] ?? '')) ?: null,
            'id'    => (int) $tenant['id'],
        ]);

        Audit::record('tenant.intake_updated', (int) $tenant['id'], $user, 'tenant', (int) $tenant['id'],
            ['enabled' => !empty($_POST['intake_enabled'])], ClientIp::resolve($_SERVER));

        redirect(url('/firm'));
    }

    /**
     * When the digests go out (FR-11.3).
     *
     * Stored as a UTC hour, because everything in this system is pinned to UTC
     * and a local-time column here would reintroduce the two-clock problem
     * documented in CLAUDE.md. The screen does the conversion; the database
     * never sees a local time.
     */
    public function updateDigest(): string
    {
        [$tenant, $user] = $this->owner();
        Csrf::check($_POST);

        Database::conn()->prepare(
            'UPDATE pl_tenants
             SET digest_hour = :hour, client_digest_day = :day, client_digest_enabled = :on
             WHERE id = :id'
        )->execute([
            'hour' => max(0, min(23, (int) ($_POST['digest_hour'] ?? 13))),
            'day'  => max(0, min(6, (int) ($_POST['client_digest_day'] ?? 1))),
            'on'   => empty($_POST['client_digest_enabled']) ? 0 : 1,
            'id'   => (int) $tenant['id'],
        ]);

        Audit::record('tenant.digest_updated', (int) $tenant['id'], $user, 'tenant', (int) $tenant['id'],
            ['hour' => (int) ($_POST['digest_hour'] ?? 13)], ClientIp::resolve($_SERVER));

        redirect(url('/firm'));
    }

    /**
     * Full tenant export (FR-1.5).
     *
     * A trust requirement, not a feature: a firm whose entire client
     * relationship lives here should be able to take it out without asking.
     */
    public function export(): string
    {
        [$tenant, $user] = $this->owner();

        $tenantId = (int) $tenant['id'];
        $db = Database::conn();

        // Every tenant-scoped table, exported wholesale.
        $tables = [
            'pl_client_orgs', 'pl_client_contacts', 'pl_users', 'pl_engagements',
            'pl_engagement_members', 'pl_scope_items', 'pl_change_requests',
            'pl_playbooks', 'pl_playbook_versions', 'pl_playbook_phases', 'pl_playbook_steps',
            'pl_engagement_playbooks', 'pl_engagement_phases', 'pl_engagement_steps',
            'pl_sessions', 'pl_session_agenda_items', 'pl_session_notes_shared',
            'pl_session_recaps', 'pl_session_attendees',
            'pl_tasks', 'pl_comments', 'pl_issues', 'pl_issue_resolutions',
            'pl_goals', 'pl_goal_milestones', 'pl_metrics', 'pl_metric_values',
            'pl_documents', 'pl_document_versions', 'pl_document_deliveries',
            'pl_document_requests', 'pl_document_request_items',
            'pl_threads', 'pl_messages', 'pl_org_events', 'pl_audit_log',
        ];

        $export = [
            'exported_at' => date('c'),
            'tenant'      => ['id' => $tenantId, 'slug' => $tenant['slug'], 'name' => $tenant['name']],
            'note'        => 'Uploaded files are not included; they are available individually from the app.',
            'tables'      => [],
        ];

        foreach ($tables as $table) {
            // Table names are a hardcoded allowlist, never user input.
            $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE tenant_id = :tid');
            $stmt->execute(['tid' => $tenantId]);
            $rows = $stmt->fetchAll();

            // Never export credentials or token material, even to the owner.
            foreach ($rows as $i => $row) {
                unset($rows[$i]['password_hash'], $rows[$i]['verifier_hash'], $rows[$i]['secret'], $rows[$i]['code_hash']);
            }

            $export['tables'][$table] = $rows;
        }

        Audit::record('tenant.exported', $tenantId, $user, 'tenant', $tenantId,
            ['tables' => count($tables)], ClientIp::resolve($_SERVER));

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="pilotage-' . $tenant['slug'] . '-' . date('Y-m-d') . '.json"');
        header('Cache-Control: private, no-store');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Onboarding (FR-1.3). Target: first client invited within 15 minutes.
     * Seeds the starter playbook and session templates so a new firm has
     * something to run rather than a blank page.
     */
    public function onboard(): string
    {
        [$tenant, $user] = $this->owner();
        Csrf::check($_POST);

        if ($tenant['onboarded_at'] !== null) {
            redirect(url('/firm'));
        }

        $tenantId = (int) $tenant['id'];

        StarterPlaybooks::installQuarterlyRhythm($tenantId, (int) $user['id']);
        StarterSessionTemplates::installWorkingSession($tenantId);
        StarterSessionTemplates::installDiscovery($tenantId);

        Database::conn()->prepare('UPDATE pl_tenants SET onboarded_at = NOW() WHERE id = :id')
            ->execute(['id' => $tenantId]);

        Audit::record('tenant.onboarded', $tenantId, $user, 'tenant', $tenantId,
            null, ClientIp::resolve($_SERVER));

        redirect(url('/clients/new'));
    }

    /** @return array{0:array,1:array} */
    private function owner(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'No tenant in scope.');
        }

        $user = Session::user();

        if ($user === null) {
            redirect(url('/login?redirect=' . rawurlencode(pl_request_path())));
        }

        // Firm settings are genuinely owner-only — seats are billing, branding
        // is the firm's identity, and the export is everything.
        Policy::authorize($user, Policy::UPDATE, 'tenant_settings');

        return [$tenant, $user];
    }
}
