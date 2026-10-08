<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Models\Announcement;
use TimeBank\Models\Member;
use TimeBank\Models\Message;
use TimeBank\Models\Notification;
use TimeBank\Models\Offer;
use TimeBank\Models\Transaction;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $tenantId = (int) $this->tenantId();
        $user     = $this->currentUser();
        $userId   = (int) $this->currentUserId();

        $offerModel        = new Offer($tenantId);
        $announcementModel = new Announcement($tenantId);
        $messageModel      = new Message($tenantId);
        $notifModel        = new Notification($tenantId);
        $txModel           = new Transaction($tenantId);
        $memberModel       = new Member($tenantId);

        $recentOffers        = $offerModel->getRecent($tenantId, 'offer', 5);
        $recentRequests      = $offerModel->getRecent($tenantId, 'request', 5);
        $recentAnnouncements = $announcementModel->getRecent($tenantId, null, 3);
        $unreadMessages      = $messageModel->getUnreadCount($userId, $tenantId);
        $unreadNotifications = $notifModel->getUnreadCount($userId, $tenantId);
        $memberWithStats     = $memberModel->findWithStats($userId);
        $balance             = $memberWithStats ? (float) $memberWithStats['balance'] : 0.0;

        $recentTransactions = $txModel->getMemberTransactions(
            $userId,
            $tenantId,
            null,
            null,
            1,
            5
        );

        $this->view('dashboard/index', [
            'recentOffers'        => $recentOffers,
            'recentRequests'      => $recentRequests,
            'recentAnnouncements' => $recentAnnouncements,
            'unreadMessages'      => $unreadMessages,
            'unreadNotifications' => $unreadNotifications,
            'balance'             => $balance,
            'recentTransactions'  => $recentTransactions['data'],
            'member'              => $user,
        ]);
    }

    public function search(): void
    {
        $this->requireAuth();

        $tenantId = (int) $this->tenantId();
        $query    = trim($this->request->get('q', ''));

        $members       = [];
        $offers        = [];
        $requests      = [];
        $announcements = [];

        if ($query !== '') {
            $memberModel       = new Member($tenantId);
            $offerModel        = new Offer($tenantId);
            $announcementModel = new Announcement($tenantId);

            $members = $memberModel->searchMembers($tenantId, $query);

            $offerResults   = $offerModel->search($tenantId, $query, 'offer');
            $requestResults = $offerModel->search($tenantId, $query, 'request');
            $offers         = $offerResults['data'];
            $requests       = $requestResults['data'];

            // Announcements — simple LIKE search against title and body
            $like          = '%' . $query . '%';
            $announcements = \TimeBank\Core\DB::fetchAll(
                "SELECT a.*,
                    COALESCE(m.display_name, CONCAT(m.first_name, ' ', m.last_name)) AS author_name
                 FROM `tm_announcements` a
                 JOIN `tm_members` m ON m.id = a.author_id
                 WHERE a.tenant_id = ? AND (a.title LIKE ? OR a.body LIKE ?)
                 ORDER BY a.is_pinned DESC, a.created_at DESC
                 LIMIT 20",
                [$tenantId, $like, $like]
            );
        }

        $this->view('dashboard/search', [
            // The view reads $results[...]; the original passed the four lists
            // separately, so search never showed a result.
            'results'       => compact('members', 'offers', 'requests', 'announcements'),
            'query'         => $query,
            'members'       => $members,
            'offers'        => $offers,
            'requests'      => $requests,
            'announcements' => $announcements,
        ]);
    }
}
