<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\DB;
use TimeBank\Models\Announcement;
use TimeBank\Models\Group;
use TimeBank\Models\Member;
use TimeBank\Models\Message;

class GroupController extends BaseController
{
    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $groupModel = new Group($tenantId);

        $this->view('groups/index', [
            'groups' => $groupModel->getActive($tenantId),
        ]);
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function create(): void
    {
        $this->requireAuth();

        $this->view('groups/create');
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        $data = [
            'name'        => trim($this->request->post('name', '')),
            'description' => trim($this->request->post('description', '')),
        ];

        $this->validate($data, [
            'name'        => 'required|max:100',
            'description' => 'max:1000',
        ], '/groups/create');

        $groupModel = new Group($tenantId);
        $groupId    = $groupModel->create([
            'name'        => $data['name'],
            'description' => $data['description'],
            'created_by'  => $userId,
            'is_active'   => 1,
        ]);

        // Auto-join the creator as moderator
        $groupModel->join((int) $groupId, $userId, 'moderator');

        $this->redirect('/groups/' . $groupId);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function show(int $id): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $groupModel = new Group($tenantId);
        $group      = $groupModel->getWithMemberCount($id);

        if (!$group || (int) $group['tenant_id'] !== $tenantId) {
            flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        $userId    = (int) $this->currentUserId();
        $members   = $groupModel->getMembers($id);
        $isMember  = $groupModel->isMember($id, $userId);

        $messageModel      = new Message($tenantId);
        $recentMessages    = $messageModel->getGroupMessages($id, 1, 10);

        $announcementModel  = new Announcement($tenantId);
        $recentAnnouncements = $announcementModel->getRecent($tenantId, $id, 5);

        $this->view('groups/show', [
            'group'               => $group,
            'members'             => $members,
            'isMember'            => $isMember,
            'recentMessages'      => $recentMessages['data'],
            'recentAnnouncements' => $recentAnnouncements,
            'currentUserId'       => $userId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Join / Leave
    // -------------------------------------------------------------------------

    public function join(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId   = (int) $this->tenantId();
        $userId     = (int) $this->currentUserId();
        $groupModel = new Group($tenantId);
        $group      = $groupModel->find($id);

        if (!$group || (int) $group['tenant_id'] !== $tenantId) {
            flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        $groupModel->join($id, $userId, 'member');

        flash('success', 'You have joined ' . $group['name'] . '.');
        $this->redirect('/groups/' . $id);
    }

    public function leave(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId   = (int) $this->tenantId();
        $userId     = (int) $this->currentUserId();
        $groupModel = new Group($tenantId);
        $group      = $groupModel->find($id);

        if (!$group || (int) $group['tenant_id'] !== $tenantId) {
            flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        // Check if this member is the sole moderator
        $moderators = DB::fetchAll(
            "SELECT member_id FROM `tm_group_members` WHERE group_id = ? AND role = 'moderator'",
            [$id]
        );

        $moderatorIds = array_column($moderators, 'member_id');
        if (count($moderatorIds) === 1 && (int) $moderatorIds[0] === $userId) {
            flash('error', 'You are the only moderator of this group. Assign another moderator before leaving.');
            $this->redirect('/groups/' . $id);
        }

        $groupModel->leave($id, $userId);

        flash('success', 'You have left ' . $group['name'] . '.');
        $this->redirect('/groups');
    }

    // -------------------------------------------------------------------------
    // Group Message
    // -------------------------------------------------------------------------

    public function messageForm(int $id): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $userId     = (int) $this->currentUserId();
        $groupModel = new Group($tenantId);
        $group      = $groupModel->find($id);

        if (!$group || (int) $group['tenant_id'] !== $tenantId) {
            flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        if (!$groupModel->isMember($id, $userId)) {
            flash('error', 'You must be a member of this group to send messages.');
            $this->redirect('/groups/' . $id);
        }

        $this->view('groups/message', [
            'group' => $group,
        ]);
    }

    public function sendMessage(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId   = (int) $this->tenantId();
        $userId     = (int) $this->currentUserId();
        $groupModel = new Group($tenantId);
        $group      = $groupModel->find($id);

        if (!$group || (int) $group['tenant_id'] !== $tenantId) {
            flash('error', 'Group not found.');
            $this->redirect('/groups');
        }

        if (!$groupModel->isMember($id, $userId)) {
            flash('error', 'You must be a member of this group to send messages.');
            $this->redirect('/groups/' . $id);
        }

        $data = [
            'subject' => trim($this->request->post('subject', '')),
            'body'    => trim($this->request->post('body', '')),
        ];

        $this->validate($data, [
            'subject' => 'required',
            'body'    => 'required',
        ], '/groups/' . $id . '/message');

        $members    = $groupModel->getMembers($id);
        $recipients = array_map(fn($m) => (int) $m['id'], $members);
        // Exclude sender from recipient list
        $recipients = array_values(array_filter($recipients, fn($rid) => $rid !== $userId));

        $messageModel = new Message($tenantId);
        $messageModel->send($userId, $tenantId, $recipients, $data['subject'], $data['body'], $id);

        flash('success', 'Message sent to the group.');
        $this->redirect('/groups/' . $id);
    }
}
