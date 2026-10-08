<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\NotificationTemplates;
use Bizorca\Pilotage\Services\Notifications;
use Bizorca\Pilotage\Services\Unsubscribe;

/**
 * The inbox, the preferences screen, the template editor, and unsubscribe (M11).
 */
final class NotificationController
{
    // ----------------------------------------------------------------- inbox

    public function index(): string
    {
        [$tenant, $user] = $this->context();

        return View::render('notifications.index', [
            'title'         => 'Notifications',
            'user'          => $user,
            'tenant'        => $tenant,
            'notifications' => Notifications::inbox((int) $tenant['id'], (int) $user['id']),
            'unread'        => Notifications::unreadCount((int) $tenant['id'], (int) $user['id']),
        ]);
    }

    /**
     * Click-through: mark it read, then go where it points.
     *
     * A POST, not a GET, because it changes state. The read/unread count is
     * small stakes, but this codebase has already been bitten once by a GET
     * that quietly created a playbook draft, and the fix is cheaper than the
     * rule being applied inconsistently.
     *
     * @param array<string,string> $params
     */
    public function open(array $params): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $notification = Notifications::find($tenantId, (int) $user['id'], (int) ($params['id'] ?? 0));

        if ($notification === null) {
            throw new HttpException(404, 'Not found.');
        }

        Notifications::markRead($tenantId, (int) $user['id'], (int) $notification['id']);

        // The stored link is a path and was validated as one at queue time.
        // Re-checking here means a row edited directly in the database still
        // cannot turn a notification into an open redirect.
        $link = (string) ($notification['link'] ?? '/');
        $safe = $link !== '' && $link[0] === '/' && !str_starts_with($link, '//') ? $link : '/';

        redirect(url($safe));
    }

    public function markAllRead(): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        Notifications::markRead((int) $tenant['id'], (int) $user['id']);

        redirect(url('/notifications'));
    }

    // ----------------------------------------------------------- preferences

    public function preferences(): string
    {
        [$tenant, $user] = $this->context();

        $firmSide = ($user['client_org_id'] ?? null) === null;

        return View::render('notifications.preferences', [
            'title'       => 'Notifications',
            'user'        => $user,
            'tenant'      => $tenant,
            'preferences' => Notifications::preferencesFor((int) $tenant['id'], (int) $user['id'], $firmSide),
            'channels'    => Notifications::CHANNELS,
            'saved'       => isset($_GET['saved']),
        ]);
    }

    public function savePreferences(): string
    {
        [$tenant, $user] = $this->context();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $submitted = $_POST['channel'] ?? [];

        if (!is_array($submitted)) {
            throw new HttpException(422, 'Malformed preferences.');
        }

        foreach ($submitted as $eventType => $channel) {
            $eventType = (string) $eventType;

            if (!Notifications::isKnown($eventType)) {
                continue;
            }

            // Transactional rows are rendered as fixed text with no control, so
            // one arriving here is a hand-crafted POST. Skipping rather than
            // erroring keeps the screen usable while still ignoring it.
            if (Notifications::CATALOGUE[$eventType]['transactional']) {
                continue;
            }

            try {
                Notifications::setPreference($tenantId, (int) $user['id'], $eventType, (string) $channel);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        redirect(url('/settings/notifications?saved=1'));
    }

    // ------------------------------------------------------- template editor

    public function templates(): string
    {
        [$tenant, $user] = $this->firmContext();
        Policy::authorize($user, Policy::UPDATE, 'tenant_settings');

        return View::render('notifications.templates', [
            'title'     => 'Email wording',
            'user'      => $user,
            'tenant'    => $tenant,
            'templates' => NotificationTemplates::all((int) $tenant['id']),
            'saved'     => isset($_GET['saved']),
            'error'     => null,
        ]);
    }

    public function saveTemplate(): string
    {
        [$tenant, $user] = $this->firmContext();
        Policy::authorize($user, Policy::UPDATE, 'tenant_settings');
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $eventType = (string) ($_POST['event_type'] ?? '');

        try {
            if (($_POST['action'] ?? '') === 'reset') {
                NotificationTemplates::reset($tenantId, $eventType);
            } else {
                NotificationTemplates::save(
                    $tenantId,
                    $eventType,
                    $_POST['subject'] ?? null,
                    $_POST['body'] ?? null,
                    (int) $user['id']
                );
            }
        } catch (\InvalidArgumentException $e) {
            // Re-render rather than redirect: the coach's draft is in $_POST
            // and a PRG here would throw away what they typed along with the
            // explanation of what was wrong with it.
            return View::render('notifications.templates', [
                'title'     => 'Email wording',
                'user'      => $user,
                'tenant'    => $tenant,
                'templates' => NotificationTemplates::all($tenantId),
                'saved'     => false,
                'error'     => ['event_type' => $eventType, 'message' => $e->getMessage()],
            ]);
        }

        redirect(url('/firm/email-wording?saved=1'));
    }

    // ----------------------------------------------------------- unsubscribe

    /**
     * The confirmation page. GET, and it changes nothing.
     *
     * One-click unsubscribe is tempting and wrong here: mail clients and
     * security scanners prefetch links, and a GET that unsubscribes would
     * silently switch people off who never touched it. A single button on a
     * page costs one click and is the difference between a preference and an
     * accident.
     *
     * @param array<string,string> $params
     */
    public function unsubscribeConfirm(array $params): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'Not found.');
        }

        $token = (string) ($params['token'] ?? '');
        $who = Unsubscribe::resolve($token);

        // A bad token is a plain page, not an error. Someone holding a stale
        // link should be told what to do, not shown a 404 that reads as a
        // broken product.
        return View::render('notifications.unsubscribe', [
            'title'    => 'Email preferences',
            'tenant'   => $tenant,
            'token'    => $token,
            'valid'    => $who !== null && (int) $who['tenant_id'] === (int) $tenant['id'],
            'done'     => null,
            'bare'     => true,
        ]);
    }

    /** @param array<string,string> $params */
    public function unsubscribeApply(array $params): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new HttpException(404, 'Not found.');
        }

        $token = (string) ($params['token'] ?? '');
        $who = Unsubscribe::resolve($token);

        if ($who === null || (int) $who['tenant_id'] !== (int) $tenant['id']) {
            return View::render('notifications.unsubscribe', [
                'title'  => 'Email preferences',
                'tenant' => $tenant,
                'token'  => $token,
                'valid'  => false,
                'done'   => null,
                'bare'   => true,
            ]);
        }

        return View::render('notifications.unsubscribe', [
            'title'  => 'Email preferences',
            'tenant' => $tenant,
            'token'  => $token,
            'valid'  => true,
            'done'   => Unsubscribe::apply($token),
            'bare'   => true,
        ]);
    }

    // ------------------------------------------------------------- internals

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

    /** @return array{0:array,1:array} */
    private function firmContext(): array
    {
        [$tenant, $user] = $this->context();

        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }

        return [$tenant, $user];
    }
}
