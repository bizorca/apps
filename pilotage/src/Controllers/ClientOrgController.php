<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Invitation;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Mailer;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Repositories\ClientContactRepository;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Services\Timeline;

/**
 * Client organizations and their contacts (M3).
 *
 * Every action authorizes through Policy before touching data. The repository
 * scope makes cross-tenant access impossible; Policy decides whether this
 * particular user may do this particular thing within their own tenant.
 */
final class ClientOrgController
{
    public function index(): string
    {
        [$tenant, $user] = $this->context();

        // The firm's book of clients is firm-side only. This was briefly a
        // Policy::can() call passing the requesting user's own id as
        // owner_user_id, which trivially satisfied the 'own' qualifier and let
        // every client-side user list every client in the firm. A permission
        // check whose context is derived from the requester is not a check.
        $this->requireFirmSide($user);

        $orgs = new ClientOrgRepository();
        $filter = (string) ($_GET['status'] ?? 'active');
        $search = trim((string) ($_GET['q'] ?? ''));

        if ($search !== '') {
            $rows = $orgs->search($search);
        } elseif (in_array($filter, ['prospect', 'active', 'archived'], true)) {
            $rows = $orgs->all(['status' => $filter], 'name asc');
        } else {
            $rows = $orgs->all([], 'name asc');
        }

        return View::render('orgs.index', [
            'title'  => 'Clients',
            'user'   => $user,
            'tenant' => $tenant,
            'orgs'   => $rows,
            'counts' => $orgs->countsByStatus(),
            'filter' => $filter,
            'search' => $search,
        ]);
    }

    public function create(): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);

        return View::render('orgs.form', [
            'title'  => 'Add a client',
            'user'   => $user,
            'tenant' => $tenant,
            'org'    => ['status' => 'prospect', 'owner_user_id' => $user['id']],
            'errors' => [],
            'action' => url('/clients/new'),
        ]);
    }

    public function store(): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);
        Csrf::check($_POST);

        $data = ClientOrgRepository::normalize($this->orgFields($_POST));
        $data['owner_user_id'] = $user['id'];

        $problems = ClientOrgRepository::problems($data);

        if ($problems !== []) {
            return View::render('orgs.form', [
                'title'  => 'Add a client',
                'user'   => $user,
                'tenant' => $tenant,
                'org'    => $data,
                'errors' => $problems,
                'action' => url('/clients/new'),
            ]);
        }

        $orgs = new ClientOrgRepository();
        $id = $orgs->createOrg($data);

        Timeline::record(
            (int) $tenant['id'],
            $id,
            Timeline::ORG_CREATED,
            $data['name'] . ' added as a ' . ($data['status'] ?? 'prospect'),
            $user
        );

        redirect(url('/clients/' . $id));
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->context();

        $orgs = new ClientOrgRepository();
        $org = $orgs->find((int) ($params['id'] ?? 0));

        if ($org === null) {
            throw new HttpException(404, 'No such client.');
        }

        $this->authorizeOrgRead($user, $org);

        $contacts = new ClientContactRepository();
        $clientSide = ($user['client_org_id'] ?? null) !== null;

        $engagements = new \Bizorca\Pilotage\Repositories\EngagementRepository();

        return View::render('orgs.show', [
            'engagements' => $engagements->forOrg((int) $org['id']),
            'cadences'    => \Bizorca\Pilotage\Repositories\EngagementRepository::CADENCES,
            'title'    => (string) $org['name'],
            'user'     => $user,
            'tenant'   => $tenant,
            'org'      => $org,
            'contacts' => $contacts->forOrg((int) $org['id']),
            'seats'    => $contacts->seatsUsed((int) $org['id']),
            'events'   => Timeline::forOrg((int) $tenant['id'], (int) $org['id'], $clientSide),
            'canEdit'  => Policy::can($user, Policy::UPDATE, 'engagement', $this->orgContext($org)),
        ]);
    }

    /** @param array<string,string> $params */
    public function edit(array $params): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);

        $orgs = new ClientOrgRepository();
        $org = $orgs->find((int) ($params['id'] ?? 0));

        if ($org === null) {
            throw new HttpException(404, 'No such client.');
        }

        Policy::authorize($user, Policy::UPDATE, 'engagement', $this->orgContext($org));

        return View::render('orgs.form', [
            'title'  => 'Edit ' . $org['name'],
            'user'   => $user,
            'tenant' => $tenant,
            'org'    => $org,
            'errors' => [],
            'action' => url('/clients/' . $org['id'] . '/edit'),
        ]);
    }

    /** @param array<string,string> $params */
    public function update(array $params): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);
        Csrf::check($_POST);

        $orgs = new ClientOrgRepository();
        $org = $orgs->find((int) ($params['id'] ?? 0));

        if ($org === null) {
            throw new HttpException(404, 'No such client.');
        }

        Policy::authorize($user, Policy::UPDATE, 'engagement', $this->orgContext($org));

        $data = ClientOrgRepository::normalize($this->orgFields($_POST));
        $problems = ClientOrgRepository::problems($data);

        if ($problems !== []) {
            return View::render('orgs.form', [
                'title'  => 'Edit ' . $org['name'],
                'user'   => $user,
                'tenant' => $tenant,
                'org'    => $data + $org,
                'errors' => $problems,
                'action' => url('/clients/' . $org['id'] . '/edit'),
            ]);
        }

        $orgs->update((int) $org['id'], $data);

        Timeline::record((int) $tenant['id'], (int) $org['id'], Timeline::ORG_UPDATED, 'Details updated', $user, null, null, false);

        redirect(url('/clients/' . $org['id']));
    }

    /** @param array<string,string> $params */
    public function convert(array $params): string
    {
        [$tenant, $user] = $this->context();
        $this->requireFirmSide($user);
        Csrf::check($_POST);

        $orgs = new ClientOrgRepository();
        $org = $orgs->find((int) ($params['id'] ?? 0));

        if ($org === null) {
            throw new HttpException(404, 'No such client.');
        }

        Policy::authorize($user, Policy::UPDATE, 'engagement', $this->orgContext($org));

        if ($orgs->convert((int) $org['id'])) {
            Timeline::record(
                (int) $tenant['id'],
                (int) $org['id'],
                Timeline::ORG_CONVERTED,
                $org['name'] . ' became an active client',
                $user
            );
        }

        redirect(url('/clients/' . $org['id']));
    }

    /** @param array<string,string> $params */
    public function addContact(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $orgs = new ClientOrgRepository();
        $org = $orgs->find((int) ($params['id'] ?? 0));

        if ($org === null) {
            throw new HttpException(404, 'No such client.');
        }

        $contacts = new ClientContactRepository();
        $access = (string) ($_POST['portal_access'] ?? 'none');

        // Granting a client contact portal access is its own permission,
        // deliberately distinct from managing the firm's own seats — see the
        // note on 'client_portal_access' in Policy. FR-3.3: a client owner may
        // do this for their own organization, up to the cap.
        if ($access !== 'none') {
            Policy::authorize($user, Policy::CREATE, 'client_portal_access', $this->orgContext($org));

            if ($contacts->seatsUsed((int) $org['id']) >= (int) $org['team_invite_cap']) {
                throw new HttpException(
                    403,
                    'This client has used all ' . (int) $org['team_invite_cap'] . ' of its portal seats.'
                );
            }

            // Before the contact row is written, so a throttled firm does not
            // end up with a contact it cannot invite and no explanation. The
            // binding check is in Invitation::issue().
            \Bizorca\Pilotage\Services\SendingTrust::assertMayInvite((int) $tenant['id']);
        } else {
            $this->requireFirmSide($user);
        }

        $data = ClientContactRepository::normalize([
            'client_org_id' => (int) $org['id'],
            'name'          => $_POST['name'] ?? '',
            'email'         => $_POST['email'] ?? '',
            'title'         => $_POST['job_title'] ?? '',
            'phone'         => $_POST['phone'] ?? '',
            'portal_access' => $access,
            'is_primary'    => $_POST['is_primary'] ?? 0,
        ]);

        $problems = ClientContactRepository::problems($data);

        if ($problems !== []) {
            throw new HttpException(422, implode(' ', $problems));
        }

        $contactId = $contacts->createContact($data);

        Timeline::record(
            (int) $tenant['id'],
            (int) $org['id'],
            Timeline::CONTACT_ADDED,
            $data['name'] . ($data['title'] ? ' (' . $data['title'] . ')' : '') . ' added',
            $user,
            'contact',
            $contactId
        );

        if ($access !== 'none') {
            $this->inviteContact($tenant, $org, $data, $user);
        }

        redirect(url('/clients/' . $org['id']));
    }

    // ------------------------------------------------------------- internals

    /** @param array<string,mixed> $contact */
    private function inviteContact(array $tenant, array $org, array $contact, array $user): void
    {
        $role = ClientContactRepository::ACCESS_TO_ROLE[$contact['portal_access']] ?? null;

        if ($role === null) {
            return;
        }

        try {
            $invite = Invitation::issue(
                (int) $tenant['id'],
                (string) $tenant['slug'],
                (string) $contact['email'],
                $role,
                (int) $org['id'],
                (string) $contact['name'],
                (int) $user['id']
            );
        } catch (\RuntimeException) {
            // Already has an account here — nothing to send.
            return;
        }

        $firm = (string) ($tenant['mail_from_name'] ?? '') ?: (string) $tenant['name'];

        $body = \Bizorca\Pilotage\Services\MailTemplate::action(
            'Hello ' . $contact['name'] . ',',
            [
                $user['name'] . ' at ' . $firm . ' has set up a shared workspace for your work together '
                    . 'with ' . $org['name'] . ', and has invited you to it.',
                'You will use it to see what has been agreed, what is coming up, and anything '
                    . 'either side owes the other. Setting up takes about a minute.',
            ],
            'Accept your invitation',
            $invite['url'],
            ['The link is good for seven days.'],
            $firm
        );

        Mailer::send(
            (string) $contact['email'],
            (string) $contact['name'],
            $user['name'] . ' invited you to the ' . $firm . ' workspace',
            $body['text'],
            $body['html'],
            $firm
        );

        Timeline::record(
            (int) $tenant['id'],
            (int) $org['id'],
            Timeline::INVITE_SENT,
            'Portal invitation sent to ' . $contact['name'],
            $user
        );
    }

    /** @return array<string,mixed> */
    private function orgContext(array $org): array
    {
        return [
            'client_org_id' => (int) $org['id'],
            'owner_user_id' => $org['owner_user_id'] === null ? null : (int) $org['owner_user_id'],
        ];
    }

    private function authorizeOrgRead(array $user, array $org): void
    {
        // A client-side user may only ever see their own organization.
        $userOrg = $user['client_org_id'] ?? null;

        if ($userOrg !== null && (int) $userOrg !== (int) $org['id']) {
            throw new HttpException(404, 'No such client.');
        }

        Policy::authorize($user, Policy::READ, 'engagement', $this->orgContext($org));
    }

    private function requireFirmSide(array $user): void
    {
        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }
    }

    /** @param array<string,mixed> $post @return array<string,mixed> */
    private function orgFields(array $post): array
    {
        $fields = [
            'name', 'legal_name', 'entity_type', 'industry_naics', 'employee_count',
            'revenue_band', 'fiscal_year_end', 'website', 'phone',
            'address_line1', 'address_line2', 'city', 'region', 'postal_code', 'country',
            'situation', 'status',
        ];

        $out = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $post)) {
                $out[$field] = $post[$field];
            }
        }

        return $out;
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>} */
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
