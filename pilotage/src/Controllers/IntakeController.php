<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\ClientIp;
use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Mailer;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Intake;
use Bizorca\Pilotage\Services\MailTemplate;

/**
 * The public enquiry form, and the firm-side queue behind it.
 *
 * Two entry points resolve to a tenant differently:
 *   firmslug.pilotagehq.com/apply -> that firm
 *   pilotagehq.com/apply          -> the house firm from config
 *
 * The public actions take no session and never leak whether a firm exists:
 * a closed or unknown form is a plain 404, same as an unknown tenant.
 */
final class IntakeController
{
    public function show(): string
    {
        $tenant = $this->intakeTenant();

        return View::render('intake.form', [
            'title'    => $tenant['intake_headline'] ?: ('Work with ' . $tenant['name']),
            'tenant'   => $tenant,
            'input'    => [],
            'errors'   => [],
            'renderedAt' => time(),
        ], 'layout_public');
    }

    public function submit(): string
    {
        $tenant = $this->intakeTenant();
        $tenantId = (int) $tenant['id'];

        // Deliberately NO CSRF check. This form is reachable without a session,
        // so there is no token to bind to — a CSRF token issued to an anonymous
        // visitor protects nothing. The honeypot, the fill-time floor, the rate
        // limit and the origin check do the work instead.
        Csrf::checkOrigin($_SERVER, preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');

        $result = Intake::submit($tenantId, $_POST, $_SERVER);

        if ($result['problems'] !== []) {
            return View::render('intake.form', [
                'title'      => $tenant['intake_headline'] ?: ('Work with ' . $tenant['name']),
                'tenant'     => $tenant,
                'input'      => $_POST,
                'errors'     => $result['problems'],
                'renderedAt' => time(),
            ], 'layout_public');
        }

        // Spam and success land on the same page. Telling a bot it was caught
        // only helps it try again differently.
        if ($result['id'] !== null) {
            $this->notifyFirm($tenant, $result['id']);
            $this->acknowledge($tenant, $_POST);
        }

        return View::render('intake.thanks', [
            'title'  => 'Thank you',
            'tenant' => $tenant,
        ], 'layout_public');
    }

    // ------------------------------------------------------------ firm side

    public function queue(): string
    {
        [$tenant, $user] = $this->firmContext();

        return View::render('intake.queue', [
            'title'       => 'Enquiries',
            'user'        => $user,
            'tenant'      => $tenant,
            'submissions' => Intake::pending((int) $tenant['id'], !empty($_GET['spam'])),
            'showingSpam' => !empty($_GET['spam']),
        ]);
    }

    /** @param array<string,string> $params */
    public function act(array $params): string
    {
        [$tenant, $user] = $this->firmContext();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'accept') {
                Policy::authorize($user, Policy::CREATE, 'engagement', ['owner_user_id' => (int) $user['id']]);

                $orgId = Intake::accept($tenantId, $id, (int) $user['id'],
                    ((int) ($_POST['assign_to'] ?? 0)) ?: null);

                Audit::record('intake.accepted', $tenantId, $user, 'client_org', $orgId,
                    ['submission' => $id], ClientIp::resolve($_SERVER));

                redirect(url('/clients/' . $orgId));
            }

            if ($action === 'decline') {
                Intake::decline($tenantId, $id, (int) $user['id'], trim((string) ($_POST['note'] ?? '')) ?: null);
                redirect(url('/enquiries'));
            }
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        throw new HttpException(422, 'Unknown action.');
    }

    // ------------------------------------------------------------- internals

    /**
     * Resolve which firm a public visitor is applying to.
     *
     * @return array<string,mixed>
     */
    private function intakeTenant(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            // The apex. Hand strangers to the house firm.
            $slug = trim((string) Config::get('intake.house_tenant', ''));

            if ($slug === '') {
                throw new HttpException(404, 'Not found.');
            }

            $stmt = Database::conn()->prepare(
                "SELECT * FROM pl_tenants WHERE slug = :s AND status IN ('trial','active') LIMIT 1"
            );
            $stmt->execute(['s' => $slug]);
            $tenant = $stmt->fetch();

            if ($tenant === false) {
                throw new HttpException(404, 'Not found.');
            }
        }

        if (!Intake::isOpen($tenant)) {
            // A closed form is indistinguishable from no form at all.
            throw new HttpException(404, 'Not found.');
        }

        return $tenant;
    }

    /** @param array<string,mixed> $tenant */
    private function notifyFirm(array $tenant, int $submissionId): void
    {
        $submission = Intake::find((int) $tenant['id'], $submissionId);

        if ($submission === null) {
            return;
        }

        // Whoever is set to receive enquiries, else the firm owner.
        $stmt = Database::conn()->prepare(
            "SELECT email, name FROM pl_users
             WHERE tenant_id = :tid AND status = 'active' AND client_org_id IS NULL
               AND (id = :assignee OR role = 'firm_owner')
             ORDER BY (id = :assignee2) DESC LIMIT 1"
        );
        $stmt->execute([
            'tid'       => (int) $tenant['id'],
            'assignee'  => $tenant['intake_assign_to'],
            'assignee2' => $tenant['intake_assign_to'],
        ]);
        $recipient = $stmt->fetch();

        if ($recipient === false) {
            return;
        }

        $body = MailTemplate::action(
            'Hello ' . $recipient['name'] . ',',
            [
                $submission['name'] . ' at ' . $submission['company_name'] . ' has enquired through your form.',
                'What they said is going on: ' . mb_strimwidth((string) $submission['situation'], 0, 400, '…'),
                'Nothing has been created yet — read it and decide whether to take it on.',
            ],
            'Read the enquiry',
            tenant_url('/enquiries', (string) $tenant['slug']),
            [$submission['email'] . ($submission['phone'] ? ' · ' . $submission['phone'] : '')],
            Mailer::PLATFORM_NAME
        );

        Mailer::send(
            (string) $recipient['email'],
            (string) $recipient['name'],
            'Enquiry from ' . $submission['company_name'],
            $body['text'],
            $body['html'],
            Mailer::PLATFORM_NAME
        );
    }

    /** @param array<string,mixed> $tenant @param array<string,mixed> $input */
    private function acknowledge(array $tenant, array $input): void
    {
        $email = trim((string) ($input['email'] ?? ''));
        $name  = trim((string) ($input['name'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $firm = (string) ($tenant['mail_from_name'] ?? '') ?: (string) $tenant['name'];

        // The enquirer is a prospective CLIENT, so this comes from the firm.
        $body = MailTemplate::action(
            'Hello ' . ($name !== '' ? $name : 'there') . ',',
            [
                'Thank you for getting in touch with ' . $firm . '. Your enquiry has arrived and a real person will read it.',
                'We will come back to you shortly. If anything changes in the meantime, just reply to this message.',
            ],
            'Visit ' . $firm,
            tenant_url('/', (string) $tenant['slug']),
            [],
            $firm
        );

        Mailer::send($email, $name !== '' ? $name : $email,
            'We have your enquiry — ' . $firm, $body['text'], $body['html'], $firm);
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
