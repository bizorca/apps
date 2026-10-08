<?php

namespace App\Core;

/**
 * Burn Rate's application object. Identity comes from the shared tools account
 * (tl_user()); Burn Rate's own per-person data (membership tier, rounds, admin
 * flag) lives in br_owners keyed on the shared user id, so OwnerID == users.id.
 */
class App
{
    public Database $db;
    public Session $session;
    public Router $router;
    public View $viewEngine;
    public array $config;

    private ?object $ownerRow = null;
    private bool $ownerLoaded = false;

    public function __construct()
    {
        $this->config = require BR_ROOT . '/includes/app.php';
        $this->db = Database::getInstance();
        $this->session = new Session();
        $this->router = new Router();
        $this->viewEngine = new View(__DIR__ . '/../Views');

        // Share common data with all views. The CSRF token lives in the
        // session, so an anonymous visitor (marketing pages, which have no
        // forms) gets an empty one rather than a cookie.
        $this->viewEngine->share('app', $this);
        $this->viewEngine->share('csrf_token', $this->isLoggedIn() ? $this->session->csrfToken() : '');
        $this->viewEngine->share('flash', [
            'success' => $this->session->getFlash('success'),
            'error' => $this->session->getFlash('error'),
            'info' => $this->session->getFlash('info'),
        ]);
    }

    public function view(string $template, array $data = []): string
    {
        return $this->viewEngine->render($template, $data);
    }

    public function run(): void
    {
        $this->router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', Url::path(), $this);
    }

    public function isLoggedIn(): bool
    {
        return tl_logged_in();
    }

    public function ownerId(): ?int
    {
        $u = tl_user();
        return $u ? (int) $u['id'] : null;
    }

    public function playerId(): ?int
    {
        $id = $this->session->get('player_id');
        return $id ? (int) $id : null;
    }

    /** The signed-in person's br_owners row (created on first use), or null. */
    public function owner(): ?object
    {
        if (!$this->ownerLoaded) {
            $this->ownerLoaded = true;
            $id = $this->ownerId();
            $this->ownerRow = $id ? (new \App\Models\Owner($this->db))->ensure($id) : null;
        }
        return $this->ownerRow;
    }

    /** Membership tier, read fresh each request (the original cached it in the session at login). */
    public function memberLevel(): int
    {
        return (int) ($this->owner()->MemberLevel ?? 0);
    }

    /**
     * Burn Rate admin: a site admin, or a br_owners.IsAdmin flag. Deliberately
     * NOT the membership tier: in the original, admin meant MemberLevel >=
     * 65536, and finishing rounds raises MemberLevel, so a player who completed
     * five games became an admin.
     */
    public function isAdmin(): bool
    {
        $u = tl_user();
        if (!$u) {
            return false;
        }
        return (bool) $u['is_admin'] || (bool) ($this->owner()->IsAdmin ?? false);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) return $default;
            $value = $value[$k];
        }
        return $value;
    }
}
