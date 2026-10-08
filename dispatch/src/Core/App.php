<?php

declare(strict_types=1);

namespace Dispatch\Core;

use Dispatch\Controllers\HomeController;
use Dispatch\Controllers\AuthController;
use Dispatch\Controllers\DashboardController;
use Dispatch\Controllers\CampaignController;
use Dispatch\Controllers\VenueController;
use Dispatch\Controllers\FlyerController;
use Dispatch\Controllers\AdminController;
use Dispatch\Controllers\ProfileController;
use Dispatch\Controllers\NotificationController;
use Dispatch\Controllers\DocumentController;

class App
{
    private Router $router;

    public function __construct()
    {
        // Resume the shared session if the visitor has one; never create one.
        Session::start();

        // Init router
        $this->router = new Router();

        // Register routes
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // --- Public routes ---
        $r->get('/', [HomeController::class, 'index']);
        $r->get('/about', [HomeController::class, 'about']);
        $r->get('/features', [HomeController::class, 'features']);
        $r->get('/pricing', [HomeController::class, 'pricing']);

        // --- Accounts: the shared tools account owns these; the old paths
        //     redirect into /account/ and come back to the dashboard. ---
        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->get('/login/local', [AuthController::class, 'showLogin']);
        $r->get('/sso/login', [AuthController::class, 'showLogin']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->get('/forgot-password', [AuthController::class, 'showForgot']);
        $r->post('/logout', [AuthController::class, 'logout'], ['auth']);

        // --- Dashboard (auth required) ---
        $r->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

        // --- Campaigns ---
        $r->get('/campaigns', [CampaignController::class, 'index'], ['auth']);
        $r->get('/campaigns/create', [CampaignController::class, 'create'], ['auth']);
        $r->get('/campaigns/calendar', [CampaignController::class, 'calendar'], ['auth']);
        $r->post('/campaigns', [CampaignController::class, 'store'], ['auth']);
        $r->get('/campaigns/{id}', [CampaignController::class, 'show'], ['auth']);
        $r->get('/campaigns/{id}/edit', [CampaignController::class, 'edit'], ['auth']);
        $r->get('/campaigns/{id}/clone', [CampaignController::class, 'showClone'], ['auth']);
        $r->get('/campaigns/{id}/packet', [CampaignController::class, 'packet'], ['auth']);
        $r->post('/campaigns/{id}', [CampaignController::class, 'update'], ['auth']);
        $r->post('/campaigns/{id}/delete', [CampaignController::class, 'destroy'], ['auth']);
        $r->post('/campaigns/{id}/archive', [CampaignController::class, 'archive'], ['auth']);
        $r->post('/campaigns/{id}/clone', [CampaignController::class, 'storeClone'], ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/complete',     [CampaignController::class, 'completeActionItem'],     ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/submit',       [CampaignController::class, 'submitActionItem'],       ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/confirm',      [CampaignController::class, 'confirmActionItem'],      ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/reject',       [CampaignController::class, 'rejectActionItem'],       ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/no-response',  [CampaignController::class, 'noResponseActionItem'],   ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/skip',         [CampaignController::class, 'skipActionItem'],         ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/reopen',       [CampaignController::class, 'reopenActionItem'],       ['auth']);
        $r->post('/campaigns/{id}/action-items/assign-all',                  [CampaignController::class, 'assignAllActionItems'],     ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/assign',            [CampaignController::class, 'assignActionItem'],         ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/costs',             [CampaignController::class, 'updateActionItemCosts'],    ['auth']);
        $r->post('/campaigns/{id}/action-items/{itemId}/publication-date',  [CampaignController::class, 'setPublicationDate'],       ['auth']);

        // --- Venues ---
        $r->get('/venues', [VenueController::class, 'index'], ['auth']);
        $r->get('/venues/suggest', [VenueController::class, 'showSuggest'], ['auth']);
        $r->post('/venues/suggest', [VenueController::class, 'suggest'], ['auth']);

        // --- Flyers ---
        $r->get('/flyers', [FlyerController::class, 'index'], ['auth']);
        $r->get('/flyers/create', [FlyerController::class, 'create'], ['auth']);
        $r->post('/flyers', [FlyerController::class, 'store'], ['auth']);
        $r->get('/flyers/{id}/edit', [FlyerController::class, 'edit'], ['auth']);
        $r->post('/flyers/{id}', [FlyerController::class, 'update'], ['auth']);
        $r->post('/flyers/{id}/delete', [FlyerController::class, 'destroy'], ['auth']);

        // --- Notifications ---
        $r->get('/notifications', [NotificationController::class, 'index'], ['auth']);
        $r->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], ['auth']);

        // --- Documents ---
        $r->get('/documents',                          [DocumentController::class, 'index'],          ['auth']);
        $r->post('/documents',                         [DocumentController::class, 'store'],          ['auth']);
        $r->get('/documents/{id}/view',                [DocumentController::class, 'view'],           ['auth']);
        $r->get('/documents/{id}/download',            [DocumentController::class, 'download'],       ['auth']);
        $r->post('/documents/{id}/template',           [DocumentController::class, 'toggleTemplate'], ['auth']);
        $r->post('/documents/{id}/delete',             [DocumentController::class, 'destroy'],        ['auth']);

        // --- Profile ---
        $r->get('/profile', [ProfileController::class, 'show'], ['auth']);
        $r->post('/profile', [ProfileController::class, 'update'], ['auth']);

        // --- Admin (sysop/admin required) ---
        $r->get('/admin', [AdminController::class, 'index'], ['auth', 'admin']);
        $r->get('/admin/users', [AdminController::class, 'users'], ['auth', 'admin']);
        $r->post('/admin/users/{id}/role', [AdminController::class, 'updateUserRole'], ['auth', 'sysop']);
        $r->get('/admin/venues', [AdminController::class, 'venues'], ['auth', 'admin']);
        $r->get('/admin/venues/create', [AdminController::class, 'createVenue'], ['auth', 'admin']);
        $r->post('/admin/venues', [AdminController::class, 'storeVenue'], ['auth', 'admin']);
        $r->get('/admin/venues/{id}/edit', [AdminController::class, 'editVenue'], ['auth', 'admin']);
        $r->post('/admin/venues/{id}', [AdminController::class, 'updateVenue'], ['auth', 'admin']);
        $r->post('/admin/venues/{id}/delete', [AdminController::class, 'deleteVenue'], ['auth', 'sysop']);
        $r->get('/admin/submissions', [AdminController::class, 'submissions'], ['auth', 'admin']);
        $r->get('/admin/submissions/{id}', [AdminController::class, 'showSubmission'], ['auth', 'admin']);
        $r->post('/admin/submissions/{id}/approve', [AdminController::class, 'approveSubmission'], ['auth', 'admin']);
        $r->post('/admin/submissions/{id}/reject', [AdminController::class, 'rejectSubmission'], ['auth', 'admin']);
        $r->post('/admin/submissions/{id}/review', [AdminController::class, 'markUnderReview'], ['auth', 'admin']);
    }

    public function run(): void
    {
        $this->router->dispatch();
    }
}
