<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Auth;
use TimeBank\Models\Announcement;
use TimeBank\Models\Group;

class AnnouncementController extends BaseController
{
    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $tenantId          = (int) $this->tenantId();
        $page              = max(1, (int) $this->request->get('page', '1'));
        $announcementModel = new Announcement($tenantId);
        $result            = $announcementModel->getTenantAnnouncements($tenantId, $page, 20);

        $this->view('announcements/index', [
            'announcements' => $result,   // the view reads ['data'] (and pagination); the original passed the bare list, so this page was always empty
            'pagination'    => $result,
        ]);
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function create(): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $groupModel = new Group($tenantId);

        $this->view('announcements/create', [
            'groups'  => $groupModel->getActive($tenantId),
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        $data = [
            'title'   => trim($this->request->post('title', '')),
            'body'    => trim($this->request->post('body', '')),
            'group_id' => $this->request->post('group_id', '') ?: null,
        ];

        $this->validate($data, [
            'title' => 'required|max:255',
            'body'  => 'required',
        ], '/announcements/create');

        // A group must be one of this community's (the original took any id).
        if ($data['group_id'] !== null) {
            $group = (new Group($tenantId))->find((int) $data['group_id']);
            $data['group_id'] = $group ? (int) $group['id'] : null;
        }

        $isPinned = 0;
        if (Auth::isAdmin() && $this->request->post('is_pinned', '')) {
            $isPinned = 1;
        }

        $announcementModel = new Announcement($tenantId);
        $announcementModel->create([
            'author_id' => $userId,
            'title'     => $data['title'],
            'body'      => $data['body'],
            'group_id'  => $data['group_id'] !== null ? (int) $data['group_id'] : null,
            'is_pinned' => $isPinned,
        ]);

        flash('success', 'Announcement posted.');
        $this->redirect('/announcements');
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function show(int $id): void
    {
        $this->requireAuth();

        $tenantId          = (int) $this->tenantId();
        $announcementModel = new Announcement($tenantId);
        $announcement      = $announcementModel->getWithAuthor($id);

        if (!$announcement) {
            flash('error', 'Announcement not found.');
            $this->redirect('/announcements');
        }

        $this->view('announcements/show', [
            'announcement' => $announcement,
            'isAdmin'      => Auth::isAdmin(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function destroy(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId          = (int) $this->tenantId();
        $announcementModel = new Announcement($tenantId);
        $announcement      = $announcementModel->find($id);

        if (!$announcement) {
            flash('error', 'Announcement not found.');
            $this->redirect('/announcements');
        }

        $announcementModel->delete($id);

        flash('success', 'Announcement deleted.');
        $this->redirect('/announcements');
    }
}
