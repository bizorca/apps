<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Core\Request;
use Dispatch\Models\FlyerLocation;
use Dispatch\Models\Campaign;

class FlyerController extends BaseController
{
    public function index(array $params = []): void
    {
        $userId  = Auth::userId();
        $flyers  = FlyerLocation::findByUser($userId);
        $active  = array_filter($flyers, fn($f) => $f['status'] === 'active');
        $removed = array_filter($flyers, fn($f) => $f['status'] !== 'active');

        $this->render('flyers/index', [
            'title'   => 'Flyer Tracker',
            'flyers'  => $flyers,
            'active'  => array_values($active),
            'removed' => array_values($removed),
        ]);
    }

    public function create(array $params = []): void
    {
        $campaigns = Campaign::findActiveByUser(Auth::userId());
        $this->render('flyers/create', [
            'title'     => 'Log Flyer Location',
            'campaigns' => $campaigns,
        ]);
    }

    public function store(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/flyers/create');
        }

        $name = trim(Request::post('location_name', ''));
        if (empty($name)) {
            $this->flash('error', 'Location name is required.');
            $this->redirect('/flyers/create');
        }

        $userId     = Auth::userId();
        $campaignId = Request::post('campaign_id') ?: null;
        if ($campaignId) {
            $campaign = Campaign::findById((int) $campaignId);
            if (!$campaign || (int) $campaign['user_id'] !== $userId) {
                $campaignId = null;
            }
        }

        FlyerLocation::create([
            'user_id'       => $userId,
            'campaign_id'   => $campaignId ? (int) $campaignId : null,
            'location_name' => $name,
            'address'       => Request::post('address'),
            'posted_at'     => Request::post('posted_at') ?: date('Y-m-d'),
            'quantity'      => (int) (Request::post('quantity') ?: 1),
            'notes'         => Request::post('notes'),
            'status'        => 'active',
        ]);

        $this->flash('success', 'Flyer location logged.');
        $this->redirect('/flyers');
    }

    public function edit(array $params = []): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $flyer  = FlyerLocation::findById($id);
        $userId = Auth::userId();

        if (!$flyer || (int) $flyer['user_id'] !== $userId) {
            $this->notFound();
        }

        $campaigns = Campaign::findActiveByUser($userId);
        $this->render('flyers/edit', [
            'title'     => 'Edit Flyer Location',
            'flyer'     => $flyer,
            'campaigns' => $campaigns,
        ]);
    }

    public function update(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/flyers');
        }

        $id    = (int) ($params['id'] ?? 0);
        $flyer = FlyerLocation::findById($id);

        $userId = Auth::userId();

        if (!$flyer || (int) $flyer['user_id'] !== $userId) {
            $this->notFound();
        }

        $name = trim(Request::post('location_name', ''));
        if (empty($name)) {
            $this->flash('error', 'Location name is required.');
            $this->redirect('/flyers/' . $id . '/edit');
        }

        $campaignId = Request::post('campaign_id') ?: null;
        if ($campaignId) {
            $campaign = Campaign::findById((int) $campaignId);
            if (!$campaign || (int) $campaign['user_id'] !== $userId) {
                $campaignId = null;
            }
        }

        $status = Request::post('status', 'active');
        if (!in_array($status, ['active', 'removed', 'unknown'], true)) {
            $status = 'active';
        }

        FlyerLocation::update($id, [
            'location_name' => $name,
            'address'       => Request::post('address'),
            'posted_at'     => Request::post('posted_at'),
            'removed_at'    => Request::post('removed_at') ?: null,
            'quantity'      => (int) (Request::post('quantity') ?: 1),
            'notes'         => Request::post('notes'),
            'status'        => $status,
            'campaign_id'   => $campaignId ? (int) $campaignId : null,
        ]);

        $this->flash('success', 'Flyer location updated.');
        $this->redirect('/flyers');
    }

    public function destroy(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/flyers');
        }

        $id    = (int) ($params['id'] ?? 0);
        $flyer = FlyerLocation::findById($id);

        if (!$flyer || (int) $flyer['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        FlyerLocation::delete($id);
        $this->flash('success', 'Flyer location removed.');
        $this->redirect('/flyers');
    }
}
