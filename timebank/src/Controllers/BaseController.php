<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Auth;
use TimeBank\Core\Flash;
use TimeBank\Core\Request;
use TimeBank\Core\Response;
use TimeBank\Core\Tenant;
use TimeBank\Core\Validator;
use TimeBank\Core\View;

abstract class BaseController
{
    protected Request $request;
    protected ?array  $tenant;
    protected bool    $authInitialized = false;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->tenant  = Tenant::get();
    }

    protected function view(string $template, array $data = []): void
    {
        View::render($template, $data);
    }

    protected function redirect(string $url, int $code = 302): never
    {
        Response::redirect($url, $code);
    }

    protected function requireAuth(): void
    {
        Auth::require();
    }

    protected function requireAdmin(): void
    {
        Auth::requireAdmin();
    }

    /**
     * Validate incoming data against a set of rules.
     *
     * On failure, stores errors and old input in the session and redirects back
     * to the referring page (or to $redirectTo if provided).
     *
     * On success, returns the Validator instance (passes() === true).
     */
    protected function validate(array $data, array $rules, ?string $redirectTo = null): Validator
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            tl_session(true);   // before writing: session_start() would replace $_SESSION
            $_SESSION['tm_errors'] = $validator->errors();
            $_SESSION['tm_old']    = $data;

            Flash::set('error', 'Please correct the errors below.');
            if ($redirectTo !== null) {
                Response::redirect($redirectTo);
            }
            Response::back('/dashboard');
        }

        return $validator;
    }

    protected function json(mixed $data, int $code = 200): never
    {
        Response::json($data, $code);
    }

    /**
     * Verify the CSRF token from a POST request.
     * Aborts with 419 if invalid.
     */
    protected function verifyCsrf(): void
    {
        $token = $this->request->post('_csrf_token');

        if (!\TimeBank\Core\CSRF::verify($token)) {
            Response::abort(419, 'Page Expired — invalid or missing CSRF token.');
        }
    }

    /**
     * An active, approved member of THIS community, or null.
     *
     * The original took member ids from forms (transaction parties, message
     * recipients, endorsement targets) without checking which community they
     * belonged to, so rows could point across communities.
     */
    protected function communityMember(int $memberId): ?array
    {
        $row = \TimeBank\Core\DB::fetch(
            'SELECT * FROM `tm_members` WHERE id = ? AND tenant_id = ? AND is_active = 1 AND is_approved = 1 LIMIT 1',
            [$memberId, (int) $this->tenantId()]
        );
        return $row ?: null;
    }

    /**
     * Return the current tenant's ID, or null on the main domain.
     */
    protected function tenantId(): ?int
    {
        return $this->tenant['id'] ?? null;
    }

    /**
     * Convenience: currently authenticated member array.
     */
    protected function currentUser(): ?array
    {
        return Auth::user();
    }

    /**
     * Convenience: currently authenticated member ID.
     */
    protected function currentUserId(): ?int
    {
        return Auth::id();
    }
}
