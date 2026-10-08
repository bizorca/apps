<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Models\Campaign;
use Dispatch\Models\ActionItem;
use Dispatch\Models\FlyerLocation;
use Dispatch\Services\ScheduleEngine;

class DashboardController extends BaseController
{
    public function index(array $params = []): void
    {
        $userId = Auth::userId();

        $stats     = ScheduleEngine::getUserStats($userId);
        $dueToday  = ActionItem::findDueTodayByUser($userId);
        $upcoming  = ActionItem::findUpcomingByUser($userId, 7);
        $followUps = ActionItem::findFollowUpsDueByUser($userId);
        $assigned  = ActionItem::findAssignedToUser($userId);
        $campaigns = Campaign::findActiveByUser($userId);
        $flyers    = FlyerLocation::findActiveByUser($userId);

        $this->render('dashboard/index', [
            'title'     => 'Dashboard',
            'stats'     => $stats,
            'dueToday'  => $dueToday,
            'upcoming'  => $upcoming,
            'followUps' => $followUps,
            'assigned'  => $assigned,
            'campaigns' => $campaigns,
            'flyers'    => $flyers,
        ]);
    }
}
