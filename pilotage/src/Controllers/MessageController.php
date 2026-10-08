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
use Bizorca\Pilotage\Services\Messaging;

/** Threads on an engagement, and the mentions inbox (M8). */
final class MessageController
{
    /** @param array<string,string> $params */
    public function index(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        $clientSide = !$this->isFirmSide($user);

        return View::render('messages.index', [
            'title'      => 'Messages',
            'user'       => $user,
            'tenant'     => $tenant,
            'engagement' => $engagement,
            'threads'    => Messaging::threads((int) $tenant['id'], (int) $engagement['id'], $clientSide),
            'clientSide' => $clientSide,
        ]);
    }

    /** @param array<string,string> $params */
    public function store(array $params): string
    {
        [$tenant, $user, $engagement] = $this->engagementContext($params);
        Csrf::check($_POST);
        Policy::authorize($user, Policy::CREATE, 'message_thread', $this->ctx($engagement));

        $clientSide = !$this->isFirmSide($user);

        try {
            $id = Messaging::createThread(
                (int) $tenant['id'],
                (int) $engagement['id'],
                (string) ($_POST['subject'] ?? ''),
                (string) ($_POST['body'] ?? ''),
                $user,
                // Only firm-side users can start an internal thread.
                $clientSide ? true : empty($_POST['internal'])
            );
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/threads/' . $id));
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->context();

        $tenantId = (int) $tenant['id'];
        $thread = Messaging::thread($tenantId, (int) ($params['id'] ?? 0));

        if ($thread === null) {
            throw new HttpException(404, 'No such thread.');
        }

        $engagements = new EngagementRepository($tenantId);
        $engagement = $engagements->find((int) $thread['engagement_id']);

        if ($engagement === null) {
            throw new HttpException(404, 'No such thread.');
        }

        $clientSide = !$this->isFirmSide($user);

        if ($clientSide && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'No such thread.');
        }

        Policy::authorize($user, Policy::READ, 'message_thread', $engagements->policyContext($engagement));

        $messages = Messaging::messages($tenantId, (int) $thread['id'], $clientSide);

        // A thread with nothing visible to this client does not exist for them.
        if ($clientSide && $messages === []) {
            throw new HttpException(404, 'No such thread.');
        }

        Messaging::markRead($tenantId, (int) $thread['id'], (int) $user['id']);

        return View::render('messages.thread', [
            'title'      => (string) $thread['subject'],
            'user'       => $user,
            'tenant'     => $tenant,
            'thread'     => $thread,
            'engagement' => $engagement,
            'messages'   => $messages,
            'clientSide' => $clientSide,
        ]);
    }

    /** @param array<string,string> $params */
    public function post(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $thread = Messaging::thread($tenantId, (int) ($params['id'] ?? 0));

        if ($thread === null) {
            throw new HttpException(404, 'No such thread.');
        }

        $engagements = new EngagementRepository($tenantId);
        $engagement = $engagements->find((int) $thread['engagement_id']);

        if ($engagement === null) {
            throw new HttpException(404, 'No such thread.');
        }

        $clientSide = !$this->isFirmSide($user);

        if ($clientSide && (int) $user['client_org_id'] !== (int) $engagement['client_org_id']) {
            throw new HttpException(404, 'No such thread.');
        }

        Policy::authorize($user, Policy::CREATE, 'message_thread', $engagements->policyContext($engagement));

        try {
            Messaging::post(
                $tenantId,
                (int) $thread['id'],
                (string) ($_POST['body'] ?? ''),
                $user,
                $clientSide ? true : empty($_POST['internal'])
            );
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/threads/' . $thread['id']));
    }

    public function mentions(): string
    {
        [$tenant, $user] = $this->context();

        $tenantId = (int) $tenant['id'];
        $mentions = Messaging::unreadMentions($tenantId, (int) $user['id']);
        Messaging::markMentionsRead($tenantId, (int) $user['id']);

        return View::render('messages.mentions', [
            'title'    => 'Mentions',
            'user'     => $user,
            'tenant'   => $tenant,
            'mentions' => $mentions,
        ]);
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
