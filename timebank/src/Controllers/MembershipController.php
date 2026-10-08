<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Auth;
use TimeBank\Core\DB;
use TimeBank\Core\Mailer;
use TimeBank\Core\Response;
use TimeBank\Models\TenantModel;
use TimeBank\Models\Transaction;

/**
 * Joining a community. Replaces the original AuthController: signing in,
 * creating an account and resetting a password happen once, on the shared
 * tools account at /account/; a person then joins each community they want,
 * which creates their membership (profile, balance, role) there.
 *
 * The old /login, /register, /logout and /forgot-password URLs still answer
 * and hand off to the shared account pages.
 */
class MembershipController extends BaseController
{
    /** Community root: members go to their dashboard, everyone else to the join page. */
    public function home(): void
    {
        Response::redirect(Auth::user() ? '/dashboard' : '/join');
    }

    public function login(): void
    {
        if (Auth::account()) {
            Response::redirect(Auth::membership() ? '/dashboard' : '/join');
        }
        Response::redirect('/account/login.php?next=' . rawurlencode(url('/dashboard')));
    }

    public function register(): void
    {
        if (Auth::account()) {
            Response::redirect('/join');
        }
        Response::redirect('/account/register.php?next=' . rawurlencode(url('/join')));
    }

    public function logout(): void
    {
        Response::redirect('/account/logout.php');
    }

    public function forgotPassword(): void
    {
        Response::redirect('/account/forgot.php');
    }

    public function joinForm(): void
    {
        $member = Auth::membership();
        if ($member) {
            // Already joined: the dashboard (or the pending/inactive notice).
            Response::redirect('/dashboard');
        }

        $tenantModel = new TenantModel();
        $tenantId    = (int) $this->tenantId();
        $account     = Auth::account();

        [$first, $last] = $account ? $this->splitName((string) $account['name']) : ['', ''];

        $this->view('membership/join', [
            'pageTitle'        => 'Join',
            'account'          => $account,
            'registrationOpen' => $tenantModel->isRegistrationOpen($tenantId),
            'requiresApproval' => $tenantModel->requiresApproval($tenantId),
            'firstName'        => $first,
            'lastName'         => $last,
            'signInUrl'        => '/account/login.php?next=' . rawurlencode(url('/join')),
            'createAccountUrl' => '/account/register.php?next=' . rawurlencode(url('/join')),
        ]);
    }

    public function join(): void
    {
        $this->verifyCsrf();

        $account = Auth::account();
        if (!$account) {
            Response::redirect('/account/login.php?next=' . rawurlencode(url('/join')));
        }
        if (Auth::membership()) {
            Response::redirect('/dashboard');
        }

        $tenantId    = (int) $this->tenantId();
        $tenantModel = new TenantModel();

        if (!$tenantModel->isRegistrationOpen($tenantId)) {
            flash('error', 'This timebank is not taking new members right now. Please contact an administrator.');
            Response::redirect('/join');
        }

        $data = [
            'first_name' => trim((string) $this->request->post('first_name', '')),
            'last_name'  => trim((string) $this->request->post('last_name', '')),
            'terms'      => (string) $this->request->post('terms', ''),
        ];

        $this->validate($data, [
            'first_name' => 'required|min:2|max:75',
            'last_name'  => 'required|min:2|max:75',
            'terms'      => 'required',
        ], '/join');

        $tenant          = $tenantModel->find($tenantId);
        $requireApproval = $tenantModel->requiresApproval($tenantId);
        $welcomeCredits  = round((float) ($tenant['welcome_credits'] ?? 0), 2);

        $memberId = DB::transaction(function () use ($account, $tenantId, $data, $requireApproval, $welcomeCredits) {
            $memberId = (int) DB::insert('tm_members', [
                'tenant_id'    => $tenantId,
                'user_id'      => (int) $account['id'],
                'email'        => $account['email'],
                'role'         => 'member',
                'first_name'   => $data['first_name'],
                'last_name'    => $data['last_name'],
                'display_name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'is_active'    => 1,
                'is_approved'  => $requireApproval ? 0 : 1,
                'balance'      => '0.00',
                'terms_accepted_at' => date('Y-m-d H:i:s'),
            ]);

            if ($welcomeCredits > 0 && !$requireApproval) {
                (new Transaction($tenantId))->creditFromCommunityFund($memberId, $welcomeCredits, 'Welcome credits', $memberId);
            }

            return $memberId;
        });

        Auth::forget();

        Mailer::sendTemplate($tenantId, 'welcome', $account['email'], $data['first_name'] . ' ' . $data['last_name'], [
            'first_name'      => $data['first_name'],
            'community_name'  => $tenant['name'] ?? 'Our Timebank',
            'welcome_credits' => number_format($welcomeCredits, 2),
            'currency_name'   => $tenant['currency_name'] ?? 'hours',
            'login_url'       => url_abs('/dashboard'),
        ]);

        if ($requireApproval) {
            flash('success', 'Thanks for joining. An administrator will approve your membership shortly.');
        } else {
            flash('success', 'Welcome! You are now a member of ' . ($tenant['name'] ?? 'this timebank') . '.');
        }
        Response::redirect('/dashboard');
    }

    /** "Ann Alpha Beta" -> ["Ann", "Alpha Beta"] to prefill the join form. */
    private function splitName(string $name): array
    {
        $name = trim($name);
        $pos  = strpos($name, ' ');
        return $pos === false ? [$name, ''] : [substr($name, 0, $pos), trim(substr($name, $pos + 1))];
    }
}
