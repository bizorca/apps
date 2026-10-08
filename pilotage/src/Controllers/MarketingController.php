<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Controllers;

use Bizorca\Pilotage\Auth\Audit;
use Bizorca\Pilotage\Auth\Csrf;
use Bizorca\Pilotage\Auth\RateLimiter;
use Bizorca\Pilotage\Auth\Session;
use Bizorca\Pilotage\Core\ClientIp;
use Bizorca\Pilotage\Core\Config;
use Bizorca\Pilotage\Core\HttpException;
use Bizorca\Pilotage\Core\Tenant;
use Bizorca\Pilotage\Core\View;
use Bizorca\Pilotage\Services\Billing;
use Bizorca\Pilotage\Services\Entitlements;
use Bizorca\Pilotage\Services\Provisioning;
use Bizorca\Pilotage\Repositories\UserRepository;

/**
 * The public marketing site, and self-serve firm creation.
 *
 * ---------------------------------------------------------------------------
 * APEX ONLY. Every action here calls apexOnly() first.
 *
 * These pages must not render on a tenant subdomain. A client of Harbourline
 * Advisory who lands on `harbourline.pilotagehq.com/pricing` should see a 404,
 * not our pricing — the whole promise of subdomain tenancy is that a client
 * sees their advisor's brand and nothing of ours. A marketing page bleeding
 * through onto a firm's own address quietly undoes that.
 *
 * The check is per-action rather than in the router because the router has no
 * concept of host scope, and a route table that silently means different things
 * on different hosts is worse than six explicit calls.
 * ---------------------------------------------------------------------------
 */
final class MarketingController
{
    public function home(): string
    {
        $this->apexOnly();

        return $this->page('marketing.home', [
            'title'       => 'A workspace for business coaches and advisors',
            'description' => 'Encode your methodology once, run every client engagement on it, and give '
                           . 'each client business a portal under your own branding. Playbooks, sessions, '
                           . 'two-way accountability, documents and scorecards in one place.',
            'active'      => 'product',
            'intakeOpen'  => trim((string) Config::get('intake.house_tenant', '')) !== '',
        ]);
    }

    public function about(): string
    {
        $this->apexOnly();

        return $this->page('marketing.about', [
            'title'       => 'About',
            'description' => 'Why Pilotage exists, what it is built on, and the things it deliberately '
                           . 'refuses to build.',
            'active'      => 'about',
        ]);
    }

    public function pricing(): string
    {
        $this->apexOnly();

        return $this->page('marketing.pricing', [
            'title'       => 'Pricing',
            'description' => 'Priced per advisor seat. Client-side contacts are always free, in any '
                           . 'number. A lapsed subscription makes a workspace read-only — it never '
                           . 'deletes anything.',
            'active'      => 'pricing',
            'interval'    => ($_GET['billing'] ?? '') === 'year' ? 'year' : 'month',
            'trialDays'   => Billing::TRIAL_DAYS,
        ]);
    }

    public function faq(): string
    {
        $this->apexOnly();

        return $this->page('marketing.faq', [
            'title'       => 'Frequently asked questions',
            'description' => 'Tenancy, data ownership, confidentiality, methodology support, billing '
                           . 'and what happens when you stop paying.',
            'active'      => 'faq',
            'groups'      => $this->faqGroups(),
        ]);
    }

    // ------------------------------------------------------------- sign in

    /**
     * "Your workspaces": the firms the signed-in tools account belongs to.
     * Signed out, it points at the shared sign-in. See the view for why this
     * replaced the old type-your-firm's-address signpost.
     */
    public function signin(): string
    {
        $this->apexOnly();

        $account = tl_user();

        return $this->page('marketing.signin', [
            'title'       => 'Your workspaces',
            'description' => 'Sign in once with your Bizorca Tools account and pick a workspace.',
            'account'     => $account,
            'workspaces'  => $account === null ? [] : Provisioning::memberships((int) $account['id']),
        ]);
    }

    /** Kept so an old bookmarked "find my firm" form post still lands somewhere sensible. */
    public function signinRedirect(): string
    {
        $this->apexOnly();
        Csrf::check($_POST);

        $slug = Provisioning::normaliseSlug((string) ($_POST['slug'] ?? ''));

        if ($slug === '' || !Tenant::isValidSlug($slug)) {
            redirect(app_url('/signin'));
        }

        redirect(tenant_url('/login', $slug));
    }

    // -------------------------------------------------------------- signup

    public function signup(): string
    {
        $this->apexOnly();

        if (tl_user() === null) {
            return $this->page('marketing.account_needed', [
                'title' => 'Create your workspace',
                'next'  => pl_route('/signup' . (isset($_GET['plan']) ? '?plan=' . rawurlencode((string) $_GET['plan']) : '')),
            ]);
        }

        $plan = (string) ($_GET['plan'] ?? '');

        return $this->signupForm(
            ['plan' => in_array($plan, Billing::PURCHASABLE, true) ? $plan : ''],
            []
        );
    }

    public function signupSubmit(): string
    {
        $this->apexOnly();
        Csrf::check($_POST);

        // The owner is the signed-in tools account. A post without one (an
        // expired session mid-form) goes back round the sign-in.
        $account = tl_user();
        if ($account === null) {
            redirect(app_url('/signup'));
        }

        $ip = ClientIp::resolve($_SERVER);

        /**
         * Spam checks run before validation and before any write.
         *
         * A caught bot gets the ordinary form back with no error, exactly as if
         * it had mistyped something. Telling it which check fired only helps it
         * try again differently.
         */
        if (Provisioning::rejectReason($_POST, $ip) !== null) {
            return $this->signupForm($_POST, []);
        }

        $problems = Provisioning::problems($_POST);

        if ($problems !== []) {
            // A failed attempt counts against the limit; a successful one does
            // not clear it, because the abuse worth stopping here is volume of
            // *successes* — someone farming subdomains — not guessing.
            if ($ip !== null) {
                RateLimiter::record(Provisioning::ACTION, $ip, $ip, false);
            }

            return $this->signupForm($_POST, $problems);
        }

        try {
            $firm = Provisioning::create($_POST, $account, $ip);
        } catch (\PDOException $e) {
            // Almost certainly the UNIQUE index on slug: someone took the
            // address between the availability check and the insert. Anything
            // else here is a real fault and should not be swallowed.
            if (!str_contains($e->getMessage(), 'uq_slug') && ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }

            return $this->signupForm($_POST, ['slug' => 'That address was taken a moment ago. Try another.']);
        }

        if ($ip !== null) {
            RateLimiter::record(Provisioning::ACTION, $ip, $ip, false);
        }

        Audit::record(
            'tenant.created',
            $firm['tenant_id'],
            null,
            'tenant',
            $firm['tenant_id'],
            ['slug' => $firm['slug'], 'via' => 'self_serve'],
            $ip
        );

        // Straight into the workspace. Same host, same signed-in account, so the
        // Pilotage session starts here (the subdomain days needed a one-time
        // handoff link for this).
        $owner = (new UserRepository((int) $firm['tenant_id']))->find((int) $firm['user_id']);
        Session::start($owner, (int) $firm['tenant_id'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
        Audit::record(Audit::LOGIN_SUCCESS, (int) $firm['tenant_id'], $owner, null, null, ['mfa' => 'enrolment_required', 'via' => 'signup'], $ip);

        redirect($firm['entry_url']);
    }

    /**
     * Address availability, for the signup form.
     *
     * Public and unauthenticated, which makes it a subdomain oracle — but only
     * for addresses nobody has taken yet, which is precisely the information
     * the form has to give to be usable. It reveals nothing about a firm that
     * exists beyond the fact that the name is spoken for, which any visit to
     * the subdomain would establish anyway.
     */
    public function slugAvailable(): string
    {
        $this->apexOnly();

        $raw = (string) ($_GET['slug'] ?? '');
        $slug = Provisioning::normaliseSlug($raw);

        $reason = null;

        if ($slug === '' || !Tenant::isValidSlug($slug)) {
            $available = false;
            $reason = 'Use 2 to 63 letters, numbers and hyphens.';
        } elseif (in_array($slug, Tenant::RESERVED_SLUGS, true)) {
            $available = false;
            $reason = 'That address is reserved.';
        } else {
            $available = Provisioning::slugAvailable($slug);
            $reason = $available ? null : 'That address is already taken.';
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        return (string) json_encode([
            'slug'      => $slug,
            'available' => $available,
            'reason'    => $reason,
            'domain'    => base_domain() . PL_BASE,
        ], JSON_UNESCAPED_SLASHES);
    }

    // ------------------------------------------------------------ internals

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $errors
     */
    private function signupForm(array $input, array $errors): string
    {
        $planKey = (string) ($input['plan'] ?? '');
        $plan = Billing::plan($planKey);

        return $this->page('marketing.signup', [
            'title'       => 'Create your workspace',
            'description' => 'Pick your address and start running engagements. '
                           . (Entitlements::inBeta() ? 'Free during beta, no card.' : 'No card to start.'),
            'errors'      => $errors,
            'input'       => $input,
            'planName'    => $plan === null || !in_array($planKey, Billing::PURCHASABLE, true)
                                ? null
                                : (string) $plan['name'],
            'trialDays'   => Billing::TRIAL_DAYS,
            'renderedAt'  => time(),
            'account'     => tl_user(),
        ]);
    }

    /**
     * Render through the marketing layout, with the beta flag every page needs.
     *
     * `beta` is read from Entitlements rather than from config directly, so the
     * marketing site cannot end up promising "free during beta" while the app
     * tells the same firm its trial has expired.
     *
     * @param array<string,mixed> $data
     */
    private function page(string $template, array $data): string
    {
        return View::render($template, $data + ['beta' => Entitlements::inBeta()], 'layout_marketing');
    }

    /**
     * These pages exist on the apex and nowhere else.
     *
     * @throws HttpException
     */
    private function apexOnly(): void
    {
        if (Tenant::current() !== null) {
            throw new HttpException(404, 'Not found.');
        }
    }

    /**
     * The FAQ content.
     *
     * Answers are trusted HTML written here, not user input — the view prints
     * them unescaped so a link or an emphasis can appear mid-sentence. Nothing
     * from a request may ever be interpolated into one of these strings.
     *
     * @return array<string,array<int,array{0:string,1:string}>>
     */
    private function faqGroups(): array
    {
        $domain = h(base_domain());

        return [
            'Getting started' => [
                ['Who is this for?',
                 '<p>Business coaches, business advisors and management consultants who run a book of '
                 . 'client companies — anywhere from one to a few dozen — and have a process they run '
                 . 'them through. If your clients are individuals working on their own lives rather '
                 . 'than businesses with staff, a general coaching platform will fit you better.</p>'],
                ['How long does setup take?',
                 '<p>Minutes. You pick an address, create your account, and land in your own workspace. '
                 . 'Setup installs a neutral ninety-day operating rhythm and two session agendas so you '
                 . 'start from a shape rather than a blank page, then walks you into adding your first '
                 . 'client organization. Most firms have their first client invited within fifteen minutes.</p>'],
                ['Do I have to use your methodology?',
                 '<p>No. The starter playbook exists to be rewritten or deleted. A playbook is a container '
                 . 'for phases, steps, completion criteria, gates and attached templates — whatever you '
                 . 'put in it is what your engagements run. Firms running EOS, Scaling Up, Pinnacle, '
                 . 'StratOp and entirely homegrown programs all describe them with the same objects.</p>'],
                ['Can I bring my existing clients over?',
                 '<p>Yes. Create the client organizations and engagements, invite their people, and pick '
                 . 'up the playbook at whatever phase they are actually in — you are not forced to start '
                 . 'everyone at step one. Documents upload in bulk.</p>'],
            ],

            'How it works' => [
                ['What is the difference between a client organization and a contact?',
                 '<p>The organization is the business you advise. Contacts are the people inside it — a '
                 . 'founder, a COO, a controller — who each get their own login and their own level of '
                 . 'visibility into the same engagement. This is the thing most coaching tools get wrong: '
                 . 'they assume one client means one person.</p>'],
                ['Can a third party watch progress without reading session notes?',
                 '<p>Yes, and it is enforced in code rather than by convention. A sponsor — a private '
                 . 'equity firm, a parent company, an SBDC program manager — gets read-only visibility '
                 . 'into progress and completion and cannot reach session content. That is the ICF '
                 . 'sponsor-versus-client distinction, and it is a permission rule, not a checkbox.</p>'],
                ['Are my private notes really private?',
                 '<p>Coach-private notes are a separate store from shared notes, and the client side has '
                 . 'no route to them at all. They are not shared notes with a flag turned off.</p>'],
                ['What does my client actually see?',
                 '<p>Your firm. Their portal lives at your workspace address, with your logo and your colors — '
                 . 'ours appears as a small footer credit that higher plans remove. They see their '
                 . 'engagement: commitments, sessions and agendas, documents delivered to them, '
                 . 'worksheets assigned to them, their scoreboard, and threads they are part of. '
                 . 'Nothing from any other client, and nothing you have not shared.</p>'],
                ['Do you send a lot of email?',
                 '<p>Less than you would expect, because every notification obeys a per-person preference '
                 . 'and most can be batched into a daily or weekly digest. Unsubscribing genuinely stops '
                 . 'the digests too. The exceptions that cannot be switched off are things a client must '
                 . 'act on — a document requiring acknowledgment, and sign-in mail.</p>'],
            ],

            'Your data' => [
                ['Can other firms see my clients?',
                 '<p>No. Every read and write against firm-owned data goes through a single scoping layer '
                 . 'that injects the firm identifier — individual queries are not trusted to remember it. '
                 . 'A test suite asks the database which tables carry a firm identifier and attacks every '
                 . 'one it finds, so a new table is covered the moment it exists. A cross-tenant leak is '
                 . 'the failure that would end this business, which is why it is the most tested thing here.</p>'],
                ['Can I get my data out?',
                 '<p>Whenever you want, in full, without asking anyone. The export keeps working after a '
                 . 'subscription lapses — permanently. Revoking it at the moment someone most needs it '
                 . 'would make trusting us with a client roster an irrational thing to have done.</p>'],
                ['What happens if a client asks to be erased?',
                 '<p>Their identifying details are pseudonymised everywhere their name is stored, and the '
                 . 'engagement record survives. Deleting the person outright would cascade through their '
                 . 'commitments and attendance and take the history of the work with it, which is the '
                 . 'opposite of what an erasure request should do to your records.</p>'],
                ['Do you delete old engagements?',
                 '<p>Only if you tell us to. Retention defaults to keep-forever. If you set a policy, a '
                 . 'closed engagement is scheduled and you are emailed about it thirty days before '
                 . 'anything is destroyed — and nothing is destroyed on a notice that was never '
                 . 'successfully sent, so a mail outage cannot become a silent deletion.</p>'],
                ['Where does it run?',
                 '<p>On Bizorca\'s own infrastructure, over HTTPS. Everyone signs in with a Bizorca Tools '
                 . 'account (passwords are bcrypt only), firm owners are required to use two-factor '
                 . 'authentication, and third-party credentials such as calendar tokens are encrypted at rest.</p>'],
            ],

            'Money and accounts' => [
                ['What does it cost?',
                 '<p>Plans run per advisor seat, with client-side contacts always free. The full grid is '
                 . 'on the <a href="' . h(app_url('/pricing')) . '" class="font-medium text-tide-600 underline underline-offset-4">pricing page</a>. '
                 . 'While Pilotage is in beta nothing is charged at all.</p>'],
                ['What happens when the beta ends?',
                 '<p>You hear it from us first, with time to decide. Nothing switches off overnight and '
                 . 'nothing is charged to a card we never collected.</p>'],
                ['What happens if I stop paying?',
                 '<p>The workspace becomes read-only. Everything stays exactly where it is, both sides can '
                 . 'still read all of it, your clients keep their access to documents you already '
                 . 'delivered, and the export still works. One payment makes it writable again.</p>'],
                ['Do you charge my clients anything?',
                 '<p>No. The platform never bills a client organization. What you invoice them is between '
                 . 'you and them.</p>'],
                ['Can I use my own domain?',
                 '<p>No, and that is a decision rather than a gap. Every firm lives at its own address on ' . $domain
                 . ', permanently. Custom domains bring certificate provisioning, DNS support and a second '
                 . 'class of tenant URL; your workspace already carries your name, logo and colors.</p>'],
                ['I am a client of a coach who uses this, not a coach.',
                 '<p>Then you want your advisor\'s address, not this page. It is in their invitation email '
                 . 'and at the bottom of any message from them — or use '
                 . '<a href="' . h(app_url('/signin')) . '" class="font-medium text-tide-600 underline underline-offset-4">your workspaces</a>, '
                 . 'which lists every workspace your Bizorca Tools account belongs to.</p>'],
            ],
        ];
    }
}
