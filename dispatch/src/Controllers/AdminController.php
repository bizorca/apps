<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Core\Request;
use Dispatch\Core\Database;
use Dispatch\Models\User;
use Dispatch\Models\Venue;
use Dispatch\Models\VenueSubmission;
use Dispatch\Models\Campaign;
use Dispatch\Models\ActionItem;
use Dispatch\Services\NotificationService;

class AdminController extends BaseController
{
    // -------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------
    public function index(array $params = []): void
    {
        $db = Database::getInstance();

        $stats = [
            'users'               => User::count(),
            'venues'              => Venue::count(),
            'campaigns'           => Campaign::count(),
            'pending_submissions' => VenueSubmission::countByStatus('pending'),
        ];

        $recentUsers = User::recent(5);

        $pendingSubmissions = VenueSubmission::allPending();

        $this->render('admin/index', [
            'title'              => 'Admin Dashboard',
            'stats'              => $stats,
            'recentUsers'        => $recentUsers,
            'pendingSubmissions' => $pendingSubmissions,
        ]);
    }

    // -------------------------------------------------------
    // Users
    // -------------------------------------------------------
    public function users(array $params = []): void
    {
        $users = User::all();
        $this->render('admin/users/index', [
            'title' => 'Manage Users',
            'users' => $users,
        ]);
    }

    public function updateUserRole(array $params = []): void
    {
        if (!Auth::verifyCsrf() || !Auth::isSysOp()) {
            $this->flash('error', 'Access denied.');
            $this->redirect('/admin/users');
        }

        $targetId = (int) ($params['id'] ?? 0);
        $role     = Request::post('role', 'user');
        $userId   = Auth::userId();

        if ($targetId === $userId) {
            $this->flash('error', 'You cannot change your own role.');
            $this->redirect('/admin/users');
        }

        if (!in_array($role, ['user', 'admin', 'sysop'], true)) {
            $this->flash('error', 'Invalid role.');
            $this->redirect('/admin/users');
        }

        User::updateRole($targetId, $role);
        $this->flash('success', 'User role updated.');
        $this->redirect('/admin/users');
    }

    // -------------------------------------------------------
    // Venues
    // -------------------------------------------------------
    public function venues(array $params = []): void
    {
        $venues = Venue::all();
        $types  = Venue::types();
        $this->render('admin/venues/index', [
            'title'  => 'Manage Venues',
            'venues' => $venues,
            'types'  => $types,
        ]);
    }

    public function createVenue(array $params = []): void
    {
        $this->render('admin/venues/create', [
            'title'   => 'Add Venue',
            'types'   => Venue::types(),
            'methods' => Venue::submissionMethods(),
        ]);
    }

    public function storeVenue(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/admin/venues/create');
        }

        $name = trim(Request::post('name', ''));
        if (empty($name)) {
            $this->flash('error', 'Venue name is required.');
            $this->redirect('/admin/venues/create');
        }

        // Build asset requirements JSON from structured inputs
        $assetTypes = Request::post('asset_types') ?? [];
        $assetSpecs = Request::post('asset_specs') ?? [];
        $assetReqs  = [];
        foreach ($assetTypes as $i => $type) {
            if (!empty($type)) {
                $assetReqs[] = [
                    'type'     => $type,
                    'specs'    => $assetSpecs[$i] ?? '',
                    'required' => true,
                ];
            }
        }

        Venue::create([
            'name'               => $name,
            'type'               => Request::post('type', 'other'),
            'submission_url'     => Request::post('submission_url') ?: null,
            'submission_email'   => Request::post('submission_email') ?: null,
            'lead_time_days'     => (int) (Request::post('lead_time_days') ?: 7),
            'buffer_days'        => (int) (Request::post('buffer_days') ?: 1),
            'submission_method'  => Request::post('submission_method', 'web'),
            'asset_requirements' => !empty($assetReqs) ? json_encode($assetReqs) : null,
            'notes'              => Request::post('notes'),
            'contact_name'       => Request::post('contact_name') ?: null,
            'contact_email'      => Request::post('contact_email') ?: null,
            'contact_notes'      => Request::post('contact_notes') ?: null,
            'is_active'          => Request::post('is_active') ? 1 : 0,
            'created_by'         => Auth::userId(),
        ]);

        $this->flash('success', 'Venue added to the library.');
        $this->redirect('/admin/venues');
    }

    public function editVenue(array $params = []): void
    {
        $venueId = (int) ($params['id'] ?? 0);
        $venue   = Venue::findById($venueId);

        if (!$venue) $this->notFound();

        $this->render('admin/venues/edit', [
            'title'   => 'Edit Venue: ' . $venue['name'],
            'venue'   => $venue,
            'types'   => Venue::types(),
            'methods' => Venue::submissionMethods(),
        ]);
    }

    public function updateVenue(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/admin/venues');
        }

        $venueId = (int) ($params['id'] ?? 0);
        $venue   = Venue::findById($venueId);
        if (!$venue) $this->notFound();

        if (empty(trim(Request::post('name', '')))) {
            $this->flash('error', 'Venue name is required.');
            $this->redirect('/admin/venues/' . $venueId . '/edit');
        }

        $assetTypes = Request::post('asset_types') ?? [];
        $assetSpecs = Request::post('asset_specs') ?? [];
        $assetReqs  = [];
        foreach ($assetTypes as $i => $type) {
            if (!empty($type)) {
                $assetReqs[] = ['type' => $type, 'specs' => $assetSpecs[$i] ?? '', 'required' => true];
            }
        }

        Venue::update($venueId, [
            'name'               => trim(Request::post('name', '')),
            'type'               => Request::post('type', 'other'),
            'submission_url'     => Request::post('submission_url') ?: null,
            'submission_email'   => Request::post('submission_email') ?: null,
            'lead_time_days'     => (int) (Request::post('lead_time_days') ?: 7),
            'buffer_days'        => (int) (Request::post('buffer_days') ?: 1),
            'submission_method'  => Request::post('submission_method', 'web'),
            'asset_requirements' => !empty($assetReqs) ? json_encode($assetReqs) : null,
            'notes'              => Request::post('notes'),
            'contact_name'       => Request::post('contact_name') ?: null,
            'contact_email'      => Request::post('contact_email') ?: null,
            'contact_notes'      => Request::post('contact_notes') ?: null,
            'is_active'          => Request::post('is_active') ? 1 : 0,
        ]);

        $this->flash('success', 'Venue updated.');
        $this->redirect('/admin/venues');
    }

    public function deleteVenue(array $params = []): void
    {
        if (!Auth::verifyCsrf() || !Auth::isSysOp()) {
            $this->flash('error', 'Access denied.');
            $this->redirect('/admin/venues');
        }

        $venueId = (int) ($params['id'] ?? 0);
        $venue   = Venue::findById($venueId);
        if (!$venue) $this->notFound();

        Venue::delete($venueId);
        $this->flash('success', 'Venue deleted.');
        $this->redirect('/admin/venues');
    }

    // -------------------------------------------------------
    // Venue Submissions (Triage Queue)
    // -------------------------------------------------------
    public function submissions(array $params = []): void
    {
        $all         = VenueSubmission::all();
        $pending     = array_filter($all, fn($s) => $s['status'] === 'pending');
        $underReview = array_filter($all, fn($s) => $s['status'] === 'under_review');
        $processed   = array_filter($all, fn($s) => in_array($s['status'], ['approved', 'rejected']));

        $this->render('admin/submissions/index', [
            'title'       => 'Triage Queue',
            'pending'     => array_values($pending),
            'underReview' => array_values($underReview),
            'processed'   => array_values($processed),
        ]);
    }

    public function showSubmission(array $params = []): void
    {
        $id         = (int) ($params['id'] ?? 0);
        $submission = VenueSubmission::findById($id);
        if (!$submission) $this->notFound();

        $this->render('admin/submissions/show', [
            'title'      => 'Review: ' . $submission['venue_name'],
            'submission' => $submission,
            'types'      => Venue::types(),
            'methods'    => Venue::submissionMethods(),
        ]);
    }

    public function approveSubmission(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request. Please try again.');
            $this->redirect('/admin/submissions');
        }

        $id         = (int) ($params['id'] ?? 0);
        $submission = VenueSubmission::findById($id);
        if (!$submission) $this->notFound();

        $adminNotes = Request::post('admin_notes');

        // Create the venue from the submission
        $assetTypes = Request::post('asset_types') ?? [];
        $assetSpecs = Request::post('asset_specs') ?? [];
        $assetReqs  = [];
        foreach ($assetTypes as $i => $type) {
            if (!empty($type)) {
                $assetReqs[] = ['type' => $type, 'specs' => $assetSpecs[$i] ?? '', 'required' => true];
            }
        }

        $venueName = trim(Request::post('venue_name') ?: $submission['venue_name']);

        Venue::create([
            'name'                 => $venueName,
            'type'                 => Request::post('type', $submission['venue_type'] ?? 'other'),
            'submission_url'       => Request::post('submission_url', $submission['submission_url']),
            'submission_email'     => Request::post('submission_email', $submission['submission_email']),
            'lead_time_days'       => (int) (Request::post('lead_time_days') ?: 7),
            'buffer_days'          => (int) (Request::post('buffer_days') ?: 1),
            'submission_method'    => Request::post('submission_method', 'web'),
            'asset_requirements'   => !empty($assetReqs) ? json_encode($assetReqs) : null,
            'notes'                => Request::post('notes'),
            'is_active'            => 1,
            'suggested_by_user_id' => $submission['user_id'],
            'created_by'           => Auth::userId(),
        ]);

        VenueSubmission::updateStatus($id, 'approved', Auth::userId(), $adminNotes);
        NotificationService::notifyVenueDecision((int) $submission['user_id'], $submission['venue_name'], 'approved', $adminNotes);

        $this->flash('success', "Venue '{$venueName}' approved and added to the library.");
        $this->redirect('/admin/submissions');
    }

    public function rejectSubmission(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request. Please try again.');
            $this->redirect('/admin/submissions');
        }

        $id         = (int) ($params['id'] ?? 0);
        $submission = VenueSubmission::findById($id);
        if (!$submission) $this->notFound();

        $notes = Request::post('admin_notes');
        VenueSubmission::updateStatus($id, 'rejected', Auth::userId(), $notes);
        NotificationService::notifyVenueDecision((int) $submission['user_id'], $submission['venue_name'], 'rejected', $notes);

        $this->flash('success', 'Submission rejected.');
        $this->redirect('/admin/submissions');
    }

    public function markUnderReview(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request. Please try again.');
            $this->redirect('/admin/submissions');
        }

        $id         = (int) ($params['id'] ?? 0);
        $submission = VenueSubmission::findById($id);
        if (!$submission) $this->notFound();

        VenueSubmission::updateStatus($id, 'under_review', Auth::userId(), Request::post('admin_notes'));
        $this->flash('info', 'Submission marked as under review.');
        $this->redirect('/admin/submissions/' . $id);
    }
}
