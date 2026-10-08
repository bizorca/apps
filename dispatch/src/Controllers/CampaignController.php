<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Core\Database;
use Dispatch\Core\Request;
use Dispatch\Models\Campaign;
use Dispatch\Models\ActionItem;
use Dispatch\Models\Venue;
use Dispatch\Models\User;
use Dispatch\Models\Document;
use Dispatch\Services\ScheduleEngine;

class CampaignController extends BaseController
{
    public function index(array $params = []): void
    {
        $userId    = Auth::userId();
        $campaigns = Campaign::findByUser($userId);

        foreach ($campaigns as &$c) {
            $c['pending_count'] = count(array_filter(
                ActionItem::findByCampaign((int) $c['id']),
                fn($a) => in_array($a['status'], ['pending', 'overdue', 'submitted', 'no_response'])
            ));
        }
        unset($c);

        $this->render('campaigns/index', ['title' => 'My Campaigns', 'campaigns' => $campaigns]);
    }

    public function create(array $params = []): void
    {
        $venues = Venue::allActive();
        $this->render('campaigns/create', [
            'title'  => 'New Campaign',
            'venues' => $venues,
        ]);
    }

    public function store(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/campaigns/create');
        }

        $userId = Auth::userId();
        $name   = trim(Request::post('name', ''));
        $date   = Request::post('event_date', '');

        if (empty($name) || empty($date)) {
            $this->flash('error', 'Campaign name and event date are required.');
            $this->redirect('/campaigns/create');
        }

        if (strtotime($date) === false) {
            $this->flash('error', 'Please enter a valid event date.');
            $this->redirect('/campaigns/create');
        }

        [$recurrenceType, $recurrenceDayPattern] = self::parseRecurrencePost();

        $campaignId = Campaign::create([
            'user_id'                => $userId,
            'name'                   => $name,
            'description'            => Request::post('description'),
            'event_date'             => $date,
            'event_time'             => Request::post('event_time') ?: null,
            'location'               => Request::post('location'),
            'recurrence_type'        => $recurrenceType,
            'recurrence_day_pattern' => $recurrenceDayPattern,
            'recurrence_interval'    => Request::post('recurrence_interval') ? max(1, (int) Request::post('recurrence_interval')) : null,
            'recurrence_end_date'    => Request::post('recurrence_end_date') ?: null,
            'base_assets'            => Request::post('base_assets'),
            'asset_link'             => Request::post('asset_link') ?: null,
        ]);

        $venueIds = Request::post('venue_ids') ?? [];
        if (!is_array($venueIds)) $venueIds = [];

        $successParts = [];
        if (!empty($venueIds)) {
            Campaign::syncVenues($campaignId, $venueIds);
            $count = ScheduleEngine::generate($campaignId, $venueIds);
            $successParts[] = "{$count} action items scheduled";
        }

        if ($recurrenceType) {
            $campaign = Campaign::findById($campaignId);
            if ($campaign) {
                $created = ScheduleEngine::generateRecurrences($campaign);
                if (!empty($created)) {
                    $successParts[] = count($created) . ' recurring instances created';
                }
            }
        }

        if (!empty($successParts)) {
            $this->flash('success', 'Campaign created — ' . implode(', ', $successParts) . '.');
        } else {
            $this->flash('success', 'Campaign created. Add venues to generate your action plan.');
        }

        $this->redirect('/campaigns/' . $campaignId);
    }

    public function show(array $params = []): void
    {
        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);

        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        $actionItems = ActionItem::findByCampaign($campaignId);
        $venues      = Campaign::getVenues($campaignId);
        $allUsers    = User::allVerified();
        $documents   = Document::findByCampaign($campaignId);

        $estimatedTotal = array_sum(array_map(fn($a) => (float) ($a['estimated_cost'] ?? 0), $actionItems));
        $actualTotal    = array_sum(array_map(fn($a) => (float) ($a['actual_cost'] ?? 0), $actionItems));

        $this->render('campaigns/show', [
            'title'          => $campaign['name'],
            'campaign'       => $campaign,
            'actionItems'    => $actionItems,
            'venues'         => $venues,
            'allUsers'       => $allUsers,
            'documents'      => $documents,
            'estimatedTotal' => $estimatedTotal,
            'actualTotal'    => $actualTotal,
        ]);
    }

    public function edit(array $params = []): void
    {
        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);

        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        $allVenues        = Venue::allActive();
        $selectedVenueIds = Campaign::getVenueIds($campaignId);

        $this->render('campaigns/edit', [
            'title'            => 'Edit: ' . $campaign['name'],
            'campaign'         => $campaign,
            'allVenues'        => $allVenues,
            'selectedVenueIds' => $selectedVenueIds,
        ]);
    }

    public function update(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/campaigns');
        }

        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);

        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        $name = trim(Request::post('name', ''));
        $date = Request::post('event_date', '');

        if (empty($name) || empty($date)) {
            $this->flash('error', 'Campaign name and event date are required.');
            $this->redirect('/campaigns/' . $campaignId . '/edit');
        }

        // store() checked this and update() did not, so a malformed date
        // reached MySQL and came back as a 500.
        if (strtotime($date) === false) {
            $this->flash('error', 'Please enter a valid event date.');
            $this->redirect('/campaigns/' . $campaignId . '/edit');
        }

        $oldDate     = $campaign['event_date'];
        $oldVenueIds = array_map('intval', Campaign::getVenueIds($campaignId));
        sort($oldVenueIds);

        [$recurrenceType, $recurrenceDayPattern] = self::parseRecurrencePost();

        $postEventRating = Request::post('post_event_rating') ? (int) Request::post('post_event_rating') : null;
        if ($postEventRating !== null) {
            $postEventRating = max(1, min(5, $postEventRating));
        }

        Campaign::update($campaignId, [
            'name'                   => $name,
            'description'            => Request::post('description'),
            'event_date'             => $date,
            'event_time'             => Request::post('event_time') ?: null,
            'location'               => Request::post('location'),
            'recurrence_type'        => $recurrenceType,
            'recurrence_day_pattern' => $recurrenceDayPattern,
            'recurrence_interval'    => Request::post('recurrence_interval') ? max(1, (int) Request::post('recurrence_interval')) : null,
            'recurrence_end_date'    => Request::post('recurrence_end_date') ?: null,
            'base_assets'            => Request::post('base_assets'),
            'asset_link'             => Request::post('asset_link') ?: null,
            'post_event_notes'       => Request::post('post_event_notes') ?: null,
            'post_event_rating'      => $postEventRating,
        ]);

        $newVenueIds = Request::post('venue_ids') ?? [];
        if (!is_array($newVenueIds)) $newVenueIds = [];
        $sortedNew = array_map('intval', $newVenueIds);
        sort($sortedNew);

        Campaign::syncVenues($campaignId, $newVenueIds);

        if ($date !== $oldDate || $sortedNew !== $oldVenueIds) {
            $count = ScheduleEngine::generate($campaignId, $newVenueIds);
            $this->flash('success', "Campaign updated — {$count} action items regenerated (completed items preserved).");
        } else {
            $this->flash('success', 'Campaign updated.');
        }

        $this->redirect('/campaigns/' . $campaignId);
    }

    public function destroy(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/campaigns');
        }

        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);

        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        Campaign::delete($campaignId);
        $this->flash('success', 'Campaign deleted.');
        $this->redirect('/campaigns');
    }

    public function archive(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/campaigns');
        }

        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);

        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        Campaign::updateStatus($campaignId, 'completed');
        $this->flash('success', 'Campaign archived.');
        $this->redirect('/campaigns');
    }

    public function showClone(array $params = []): void
    {
        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);

        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        $this->render('campaigns/clone', [
            'title'    => 'Clone: ' . $campaign['name'],
            'campaign' => $campaign,
        ]);
    }

    public function storeClone(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/campaigns');
        }

        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);
        $userId     = Auth::userId();

        if (!$campaign || (int) $campaign['user_id'] !== $userId) {
            $this->notFound();
        }

        $newDate = Request::post('event_date', '');
        if (empty($newDate) || strtotime($newDate) === false) {
            $this->flash('error', 'A valid event date is required.');
            $this->redirect('/campaigns/' . $campaignId . '/clone');
        }

        $newId    = Campaign::cloneFrom($campaignId, $userId, $newDate);
        $venueIds = Campaign::getVenueIds($campaignId);

        if (!empty($venueIds)) {
            Campaign::syncVenues($newId, $venueIds);
            ScheduleEngine::generate($newId, $venueIds);
        }

        $this->flash('success', 'Campaign cloned. Review the details and adjust as needed.');
        $this->redirect('/campaigns/' . $newId . '/edit');
    }

    public function calendar(array $params = []): void
    {
        $userId   = Auth::userId();
        $monthStr = Request::get('month', date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            $monthStr = date('Y-m');
        }

        [$year, $month] = array_map('intval', explode('-', $monthStr));
        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $firstDay = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $lastDay  = (clone $firstDay)->modify('last day of this month');

        $db = Database::getInstance();

        $campaigns = Campaign::findForCalendar($userId, $firstDay->format('Y-m-d'), $lastDay->format('Y-m-d'));

        $actionItems = $db->fetchAll(
            'SELECT ai.id, ai.due_date, ai.status, ai.campaign_id, v.name as venue_name, c.name as campaign_name
             FROM dp_action_items ai
             JOIN dp_venues v ON v.id = ai.venue_id
             JOIN dp_campaigns c ON c.id = ai.campaign_id
             WHERE (ai.user_id = ? OR ai.assigned_to_user_id = ?)
               AND ai.status IN (\'pending\', \'submitted\', \'no_response\')
               AND ai.due_date BETWEEN ? AND ?
             ORDER BY ai.due_date ASC',
            [$userId, $userId, $firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]
        );

        $prevMonth = (clone $firstDay)->modify('-1 month')->format('Y-m');
        $nextMonth = (clone $firstDay)->modify('+1 month')->format('Y-m');

        $this->render('campaigns/calendar', [
            'title'       => date('F Y', $firstDay->getTimestamp()),
            'year'        => $year,
            'month'       => $month,
            'firstDay'    => $firstDay,
            'campaigns'   => $campaigns,
            'actionItems' => $actionItems,
            'prevMonth'   => $prevMonth,
            'nextMonth'   => $nextMonth,
        ]);
    }

    public function packet(array $params = []): void
    {
        $campaignId = (int) ($params['id'] ?? 0);
        $campaign   = Campaign::findById($campaignId);

        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) {
            $this->notFound();
        }

        $actionItems = ActionItem::findByCampaign($campaignId);

        $this->render('campaigns/packet', [
            'title'       => $campaign['name'] . ' — Submission Packet',
            'campaign'    => $campaign,
            'actionItems' => $actionItems,
        ], 'print');
    }

    // -------------------------------------------------------
    // Action item status transitions
    // -------------------------------------------------------

    private function resolveItem(int $campaignId, int $itemId, int $userId): array
    {
        $item = ActionItem::findById($itemId);
        if (!$item
            || (int) $item['campaign_id'] !== $campaignId
            || ((int) $item['user_id'] !== $userId && (int) ($item['assigned_to_user_id'] ?? 0) !== $userId)
        ) {
            $this->notFound();
        }
        return $item;
    }

    public function completeActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        ActionItem::markComplete((int) $item['id']);
        $this->flash('success', 'Marked done: ' . $item['venue_name']);
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function submitActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        ActionItem::markSubmitted((int) $item['id']);
        $this->flash('success', 'Marked submitted. Follow-up reminder set for 7 days from now.');
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function confirmActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        ActionItem::markConfirmed((int) $item['id']);
        $this->flash('success', 'Confirmed listed: ' . $item['venue_name']);
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function rejectActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        ActionItem::markRejected((int) $item['id']);
        $this->flash('info', 'Marked rejected: ' . $item['venue_name']);
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function noResponseActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        ActionItem::markNoResponse((int) $item['id']);
        $this->flash('info', 'Marked no response.');
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function skipActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        ActionItem::markSkipped((int) $item['id'], Request::post('notes'));
        $this->flash('info', 'Action item skipped.');
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function reopenActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        ActionItem::markPending((int) $item['id']);
        $this->flash('info', 'Action item reopened.');
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function assignAllActionItems(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId     = (int) ($params['id'] ?? 0);
        $campaign       = Campaign::findById($campaignId);
        if (!$campaign || (int) $campaign['user_id'] !== Auth::userId()) $this->notFound();

        $assignedUserId = Request::post('assigned_to_user_id') ? (int) Request::post('assigned_to_user_id') : null;
        if ($assignedUserId !== null && !User::findById($assignedUserId)) {
            $this->flash('error', 'User not found.');
            $this->redirect('/campaigns/' . $campaignId);
        }
        ActionItem::assignAllByCampaign($campaignId, $assignedUserId);
        $this->flash('success', $assignedUserId ? 'All open tasks assigned.' : 'All assignments cleared.');
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function assignActionItem(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId     = (int) ($params['id'] ?? 0);
        $item           = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        $assignedUserId = Request::post('assigned_to_user_id') ? (int) Request::post('assigned_to_user_id') : null;
        if ($assignedUserId !== null && !User::findById($assignedUserId)) {
            $this->flash('error', 'User not found.');
            $this->redirect('/campaigns/' . $campaignId);
        }
        ActionItem::assign((int) $item['id'], $assignedUserId);
        $this->flash('success', $assignedUserId ? 'Action item assigned.' : 'Assignment cleared.');
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function updateActionItemCosts(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        $estimated  = Request::post('estimated_cost') !== '' ? (float) Request::post('estimated_cost') : null;
        $actual     = Request::post('actual_cost') !== '' ? (float) Request::post('actual_cost') : null;
        ActionItem::updateCosts((int) $item['id'], $estimated, $actual);
        $this->redirect('/campaigns/' . $campaignId);
    }

    public function setPublicationDate(array $params = []): void
    {
        if (!Auth::verifyCsrf()) { $this->flash('error', 'Invalid request.'); $this->redirect('/dashboard'); }
        $campaignId = (int) ($params['id'] ?? 0);
        $item       = $this->resolveItem($campaignId, (int) ($params['itemId'] ?? 0), Auth::userId());
        $pubDate    = trim(Request::post('target_publication_date') ?? '');

        $venue      = Venue::findById((int) $item['venue_id']);
        $baseDate   = ($pubDate !== '') ? $pubDate : $item['event_date'];
        $dueDate    = ScheduleEngine::calculateDueDate(
            $baseDate,
            (int) ($venue['lead_time_days'] ?? 7),
            (int) ($venue['buffer_days'] ?? 1)
        );

        ActionItem::setPublicationDate((int) $item['id'], $pubDate !== '' ? $pubDate : null, $dueDate);
        $this->flash('success', $pubDate !== '' ? 'Target issue date set.' : 'Target issue date cleared.');
        $this->redirect('/campaigns/' . $campaignId);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Parse recurrence fields from POST. Returns [type, day_pattern].
     * day_pattern is non-null only for monthly_weekday type.
     */
    private static function parseRecurrencePost(): array
    {
        $type = Request::post('recurrence_type') ?: null;
        if ($type !== null && !in_array($type, ['daily', 'weekly', 'monthly', 'monthly_weekday'], true)) {
            $type = null;
        }

        $dayPattern = null;
        if ($type === 'monthly_weekday') {
            $day   = (int) Request::post('recurrence_weekday_day', 0);
            $weeks = Request::post('recurrence_weekday_weeks') ?? [];
            if (!is_array($weeks)) $weeks = [];
            $weeks = array_values(array_unique(array_filter(
                array_map('intval', $weeks),
                fn($w) => $w >= 1 && $w <= 5
            )));
            sort($weeks);

            if ($day >= 1 && $day <= 7 && !empty($weeks)) {
                $dayPattern = json_encode(['day' => $day, 'weeks' => $weeks]);
            } else {
                $type = null; // invalid pattern — treat as no recurrence
            }
        }

        return [$type, $dayPattern];
    }
}
