<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Tags;

/**
 * The tag index, and everything carrying a given tag.
 *
 * Firm-side only. A tag spans every client in the book by design, so showing
 * one to a client-side user would show them the shape of engagements that are
 * none of their business — even without the contents.
 */
final class TagController
{
    public function index(): string
    {
        [$tenant, $user] = $this->firmContext();

        return View::render('tags.index', [
            'title'  => 'Tags',
            'user'   => $user,
            'tenant' => $tenant,
            'tags'   => Tags::all((int) $tenant['id']),
        ]);
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        [$tenant, $user] = $this->firmContext();

        $tag = Tags::findBySlug((int) $tenant['id'], (string) ($params['slug'] ?? ''));

        if ($tag === null) {
            throw new HttpException(404, 'No such tag.');
        }

        return View::render('tags.show', [
            'title'   => (string) $tag['name'],
            'user'    => $user,
            'tenant'  => $tenant,
            'tag'     => $tag,
            'objects' => Tags::objectsFor((int) $tenant['id'], (int) $tag['id']),
            'allTags' => Tags::all((int) $tenant['id']),
        ]);
    }

    /** @param array<string,string> $params */
    public function act(array $params): string
    {
        [$tenant, $user] = $this->firmContext();
        Csrf::check($_POST);

        $tenantId = (int) $tenant['id'];
        $tag = Tags::findBySlug($tenantId, (string) ($params['slug'] ?? ''));

        if ($tag === null) {
            throw new HttpException(404, 'No such tag.');
        }

        $action = (string) ($_POST['action'] ?? '');

        try {
            switch ($action) {
                case 'rename':
                    Tags::rename($tenantId, (int) $tag['id'], (string) ($_POST['name'] ?? ''));
                    $renamed = Tags::findBySlug($tenantId, (string) $_POST['name']);
                    redirect(url('/tags/' . ($renamed['slug'] ?? $tag['slug'])));
                    break;

                case 'merge':
                    $into = Tags::findBySlug($tenantId, (string) ($_POST['into'] ?? ''));

                    if ($into === null) {
                        throw new HttpException(422, 'No such tag to merge into.');
                    }

                    Tags::merge($tenantId, (int) $tag['id'], (int) $into['id']);
                    redirect(url('/tags/' . $into['slug']));
                    break;

                case 'delete':
                    Tags::delete($tenantId, (int) $tag['id']);
                    redirect(url('/tags'));
                    break;

                default:
                    throw new HttpException(422, 'Unknown action.');
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        redirect(url('/tags'));
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

        // A tag crosses every client in the book. Showing one to a client-side
        // user reveals the shape of engagements that are not theirs.
        if (($user['client_org_id'] ?? null) !== null) {
            throw new HttpException(404, 'Not found.');
        }

        return [$tenant, $user];
    }
}
