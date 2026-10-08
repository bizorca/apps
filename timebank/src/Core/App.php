<?php

declare(strict_types=1);

namespace TimeBank\Core;

use TimeBank\Controllers\AdminController;
use TimeBank\Controllers\AnnouncementController;
use TimeBank\Controllers\DashboardController;
use TimeBank\Controllers\EndorsementController;
use TimeBank\Controllers\GroupController;
use TimeBank\Controllers\LandingController;
use TimeBank\Controllers\MediaController;
use TimeBank\Controllers\MemberController;
use TimeBank\Controllers\MembershipController;
use TimeBank\Controllers\MessageController;
use TimeBank\Controllers\OfferController;
use TimeBank\Controllers\PaymentController;
use TimeBank\Controllers\TransactionController;

class App
{
    private Router $router;

    public function run(): void
    {
        // The route (not the URL path; see Url) starts with the community slug:
        // /demo/dashboard -> community "demo", routed as /dashboard.
        $route  = Url::path();
        $tenant = Tenant::resolve($route);
        $slug   = Tenant::slug();

        if ($tenant !== null) {
            $path = Tenant::stripPrefix($route);
            self::useCommunityClock((string) ($tenant['timezone'] ?? ''));
        } elseif ($slug !== '') {
            // A slug that matches no active community.
            Response::abort(404, 'There is no timebank registered at "' . $slug . '".');
        } else {
            $path = $route;   // site level: the landing page and payment webhooks
        }

        Url::setTenantPath($path);

        $this->router = new Router();
        if ($tenant !== null) {
            $this->registerRoutes();
        } else {
            $this->registerSiteRoutes();
        }
        $this->router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
    }

    /**
     * Show and compute times in the community's own timezone (its settings
     * page has always stored one; the original never applied it). PHP and the
     * MySQL session move together, so date('Y-m-d') and CURDATE() agree and
     * TIMESTAMP columns read back as local time.
     */
    public static function useCommunityClock(string $timezone): void
    {
        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Exception) {
            return;   // unknown zone: stay on UTC
        }
        date_default_timezone_set($tz->getName());
        $offset = (new \DateTime('now', $tz))->format('P');   // e.g. -07:00
        DB::getInstance()->exec("SET time_zone = '" . $offset . "'");
    }

    /** Routes with no community in front of them. */
    private function registerSiteRoutes(): void
    {
        $r = $this->router;
        $r->get('/',                         [LandingController::class, 'index']);
        // Stripe calls one fixed URL for every community; the donation row it
        // names carries the tenant. In the original this route only existed
        // under a community path, which Stripe could not be told about.
        $r->post('/payments/stripe/webhook', [PaymentController::class, 'stripeWebhook']);
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // ------------------------------------------------------------------
        // Membership. Signing in, registering and passwords live on the shared
        // tools account (/account/); joining a community happens here.
        // ------------------------------------------------------------------
        $r->get('/',                               [MembershipController::class, 'home']);
        $r->get('/join',                           [MembershipController::class, 'joinForm']);
        $r->post('/join',                          [MembershipController::class, 'join']);
        $r->get('/login',                          [MembershipController::class, 'login']);
        $r->get('/register',                       [MembershipController::class, 'register']);
        $r->get('/logout',                         [MembershipController::class, 'logout']);
        $r->get('/forgot-password',                [MembershipController::class, 'forgotPassword']);

        // ------------------------------------------------------------------
        // Dashboard
        // ------------------------------------------------------------------
        $r->get('/dashboard',                      [DashboardController::class, 'index']);
        $r->get('/search',                         [DashboardController::class, 'search']);

        // ------------------------------------------------------------------
        // Members / Profile
        // ------------------------------------------------------------------
        $r->get('/members',                        [MemberController::class, 'index']);
        $r->get('/members/{id}',                   [MemberController::class, 'show']);
        $r->get('/profile',                        [MemberController::class, 'editProfile']);
        $r->get('/profile/edit',                   [MemberController::class, 'editProfile']);   // the views link here
        $r->post('/profile/edit',                  [MemberController::class, 'updateProfile']); // the form posts here
        $r->post('/profile',                       [MemberController::class, 'updateProfile']);
        $r->post('/profile/avatar',                [MemberController::class, 'uploadAvatar']);
        $r->post('/profile/delete-avatar',         [MemberController::class, 'deleteAvatar']);

        // ------------------------------------------------------------------
        // Offers & Requests
        // ------------------------------------------------------------------
        $r->get('/offers',                         [OfferController::class, 'index']);
        $r->get('/offers/create',                  [OfferController::class, 'create']);
        $r->post('/offers',                        [OfferController::class, 'store']);
        $r->post('/offers/create',                 [OfferController::class, 'store']);          // the form posts here
        $r->get('/offers/{id}',                    [OfferController::class, 'show']);
        $r->get('/offers/{id}/edit',               [OfferController::class, 'edit']);
        $r->post('/offers/{id}/edit',              [OfferController::class, 'update']);
        $r->post('/offers/{id}/delete',            [OfferController::class, 'destroy']);

        $r->get('/requests',                       [OfferController::class, 'requests']);
        $r->get('/requests/create',                [OfferController::class, 'createRequest']);
        $r->post('/requests',                      [OfferController::class, 'storeRequest']);
        $r->post('/requests/create',               [OfferController::class, 'storeRequest']);   // the form posts here

        // ------------------------------------------------------------------
        // Transactions
        // ------------------------------------------------------------------
        $r->get('/transactions',                   [TransactionController::class, 'index']);
        $r->get('/transactions/record',            [TransactionController::class, 'create']);
        $r->post('/transactions',                  [TransactionController::class, 'store']);
        $r->post('/transactions/record',           [TransactionController::class, 'store']);    // the form posts here
        // Before /transactions/{id}, which would otherwise take "statement" as an id.
        $r->get('/transactions/statement',         [TransactionController::class, 'statement']);   // the nav links here
        $r->get('/transactions/{id}',              [TransactionController::class, 'show']);
        $r->post('/transactions/{id}/delete',      [TransactionController::class, 'destroy']);
        $r->get('/statement',                      [TransactionController::class, 'statement']);

        // ------------------------------------------------------------------
        // Messages
        // ------------------------------------------------------------------
        $r->get('/messages',                       [MessageController::class, 'index']);
        $r->get('/messages/compose',               [MessageController::class, 'compose']);
        $r->post('/messages',                      [MessageController::class, 'send']);
        $r->post('/messages/compose',              [MessageController::class, 'send']);         // compose and reply forms post here
        $r->get('/messages/{id}',                  [MessageController::class, 'show']);

        // ------------------------------------------------------------------
        // Announcements
        // ------------------------------------------------------------------
        $r->get('/announcements',                  [AnnouncementController::class, 'index']);
        $r->get('/announcements/create',           [AnnouncementController::class, 'create']);
        $r->post('/announcements',                 [AnnouncementController::class, 'store']);
        $r->post('/announcements/create',          [AnnouncementController::class, 'store']);   // the form posts here
        $r->get('/announcements/{id}',             [AnnouncementController::class, 'show']);
        $r->post('/announcements/{id}/delete',     [AnnouncementController::class, 'destroy']);

        // ------------------------------------------------------------------
        // Groups
        // ------------------------------------------------------------------
        $r->get('/groups',                         [GroupController::class, 'index']);
        $r->get('/groups/create',                  [GroupController::class, 'create']);
        $r->post('/groups',                        [GroupController::class, 'store']);
        $r->post('/groups/create',                 [GroupController::class, 'store']);          // the form posts here
        $r->get('/groups/{id}',                    [GroupController::class, 'show']);
        $r->post('/groups/{id}/join',              [GroupController::class, 'join']);
        $r->post('/groups/{id}/leave',             [GroupController::class, 'leave']);
        $r->get('/groups/{id}/message',            [GroupController::class, 'messageForm']);
        $r->post('/groups/{id}/message',           [GroupController::class, 'sendMessage']);

        // ------------------------------------------------------------------
        // Endorsements
        // ------------------------------------------------------------------
        $r->get('/endorsements/create/{member_id}', [EndorsementController::class, 'create']);
        $r->post('/endorsements',                   [EndorsementController::class, 'store']);
        $r->post('/endorsements/create',            [EndorsementController::class, 'store']);   // the form posts here

        // ------------------------------------------------------------------
        // Admin
        // ------------------------------------------------------------------
        $r->get('/admin',                                    [AdminController::class, 'dashboard']);
        $r->get('/admin/members',                            [AdminController::class, 'members']);
        $r->get('/admin/members/create',                     [AdminController::class, 'createMember']);
        $r->post('/admin/members',                           [AdminController::class, 'storeMember']);
        $r->post('/admin/members/create',                    [AdminController::class, 'storeMember']);   // the form posts here
        $r->get('/admin/members/{id}',                       [AdminController::class, 'showMember']);
        $r->post('/admin/members/{id}/toggle',               [AdminController::class, 'toggleMember']);
        $r->post('/admin/members/{id}/approve',              [AdminController::class, 'approveMember']); // posted from 3 views; no handler in the original
        $r->post('/admin/members/{id}/record-hours',         [AdminController::class, 'recordHours']);
        $r->get('/admin/transactions',                       [AdminController::class, 'transactions']);
        $r->get('/admin/reports',                            [AdminController::class, 'reports']);
        $r->get('/admin/reports/{slug}',                     [AdminController::class, 'report']);
        $r->get('/admin/categories',                         [AdminController::class, 'categories']);
        $r->post('/admin/categories',                        [AdminController::class, 'storeCategory']);
        $r->post('/admin/categories/create',                 [AdminController::class, 'storeCategory']);  // the form posts here
        $r->post('/admin/categories/{id}/update',            [AdminController::class, 'updateCategory']); // form existed, no handler
        $r->post('/admin/categories/{id}/toggle',            [AdminController::class, 'toggleCategory']); // form existed, no handler
        $r->post('/admin/categories/{id}/delete',            [AdminController::class, 'deleteCategory']);
        $r->get('/admin/email-templates',                    [AdminController::class, 'emailTemplates']);
        $r->get('/admin/email-templates/{slug}/edit',        [AdminController::class, 'editEmailTemplate']);
        $r->post('/admin/email-templates/{slug}',            [AdminController::class, 'updateEmailTemplate']);
        $r->post('/admin/email-templates/{slug}/edit',       [AdminController::class, 'updateEmailTemplate']); // the form posts here
        $r->get('/admin/settings',                           [AdminController::class, 'settings']);
        $r->post('/admin/settings',                          [AdminController::class, 'updateSettings']);
        $r->get('/admin/donations',                          [AdminController::class, 'donations']);
        $r->post('/admin/donations/{id}/forgive',            [AdminController::class, 'forgiveDonation']);
        $r->post('/admin/donations/request-all',             [AdminController::class, 'requestDonations']);
        $r->get('/admin/groups',                             [AdminController::class, 'groups']);
        $r->post('/admin/groups/{id}/delete',                [AdminController::class, 'deleteGroup']);    // button existed, no handler

        // ------------------------------------------------------------------
        // Uploaded images (avatars, offer photos), served from outside the web
        // root to members of this community only.
        // ------------------------------------------------------------------
        $r->get('/media/{kind}/{file}',            [MediaController::class, 'show']);

        // ------------------------------------------------------------------
        // Payments
        // ------------------------------------------------------------------
        $r->post('/payments/paypal/create',        [PaymentController::class, 'paypalCreate']);
        $r->get('/payments/paypal/success',        [PaymentController::class, 'paypalSuccess']);
        $r->get('/payments/paypal/cancel',         [PaymentController::class, 'paypalCancel']);
        $r->post('/payments/stripe/create',        [PaymentController::class, 'stripeCreate']);
    }
}
