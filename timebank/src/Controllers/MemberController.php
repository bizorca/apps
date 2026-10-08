<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Uploads;
use TimeBank\Models\Category;
use TimeBank\Models\Endorsement;
use TimeBank\Models\Member;
use TimeBank\Models\Offer;

class MemberController extends BaseController
{
    // -------------------------------------------------------------------------
    // Directory
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $tenantId = (int) $this->tenantId();
        $page     = max(1, (int) $this->request->get('page', '1'));

        $filters = [
            'city'   => trim($this->request->get('city', '')),
            'search' => trim($this->request->get('name', '')),
        ];

        $categoryFilter = $this->request->get('category', '');
        if ($categoryFilter !== '') {
            $filters['category_id'] = (int) $categoryFilter;
        }

        // Remove empty filters
        $filters = array_filter($filters, fn($v) => $v !== '' && $v !== null);

        $memberModel = new Member($tenantId);
        $result      = $memberModel->directory($tenantId, $filters, $page, 24);

        $categoryModel = new Category($tenantId);

        $this->view('members/directory', [
            'members'    => $result,   // the view reads ['data'] (and pagination); the original passed the bare list, so this page was always empty
            'pagination' => $result,
            'filters'    => $filters,
            'categories' => $categoryModel->all('name ASC'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Member Profile
    // -------------------------------------------------------------------------

    public function show(int $id): void
    {
        $this->requireAuth();

        $tenantId    = (int) $this->tenantId();
        $currentId   = (int) $this->currentUserId();
        $memberModel = new Member($tenantId);

        $member = $memberModel->findWithStats($id);

        if (!$member || (int) $member['tenant_id'] !== $tenantId) {
            flash('error', 'Member not found.');
            $this->redirect('/members');
        }

        $endorsementModel = new Endorsement($tenantId);
        $endorsements     = $endorsementModel->getForMember($id, $tenantId);
        $avgRating        = $endorsementModel->getAverageRating($id, $tenantId);

        $offerModel = new Offer($tenantId);
        $allOffers  = $offerModel->getByMember($id);
        $offers     = array_filter($allOffers, fn($o) => $o['type'] === 'offer' && (bool) $o['is_active']);
        $requests   = array_filter($allOffers, fn($o) => $o['type'] === 'request' && (bool) $o['is_active']);

        $privacy     = $memberModel->getPrivacySettings($member);
        $isAdmin     = \TimeBank\Core\Auth::isAdmin();
        $isSelf      = $currentId === $id;
        $showContact = $isSelf || $isAdmin;

        // Respect privacy settings for other members
        if (!$showContact) {
            if (!$privacy['show_email'])   { $member['email']   = null; }
            if (!$privacy['show_phone'])   { $member['phone']   = null; }
            if (!$privacy['show_address']) { $member['address'] = null; }
        }

        $this->view('members/show', [
            'member'       => $member,
            'endorsements' => $endorsements,
            'avgRating'    => $avgRating,
            'offers'       => array_values($offers),
            'requests'     => array_values($requests),
            'isSelf'       => $isSelf,
            'isAdmin'      => $isAdmin,
        ]);
    }

    // -------------------------------------------------------------------------
    // Edit Profile
    // -------------------------------------------------------------------------

    public function editProfile(): void
    {
        $this->requireAuth();

        $tenantId      = (int) $this->tenantId();
        $categoryModel = new Category($tenantId);

        $this->view('members/edit', [
            'member'     => $this->currentUser(),
            'categories' => $categoryModel->all('name ASC'),
        ]);
    }

    public function updateProfile(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        // Email is the shared account's and is not edited here (see
        // /account/settings.php); the original edited a per-community copy.
        $data = [
            'first_name'   => trim((string) $this->request->post('first_name', '')),
            'last_name'    => trim((string) $this->request->post('last_name', '')),
            'display_name' => trim((string) $this->request->post('display_name', '')),
            'bio'          => trim((string) $this->request->post('bio', '')),
            'phone'        => trim((string) $this->request->post('phone', '')),
            'address'      => trim((string) $this->request->post('address', '')),
            'city'         => trim((string) $this->request->post('city', '')),
            'state'        => trim((string) $this->request->post('state', '')),
            'zip'          => trim((string) $this->request->post('zip', '')),
            'country'      => strtoupper(trim((string) $this->request->post('country', 'US'))),
        ];

        $this->validate($data, [
            'first_name'   => 'required|maxlen:75',
            'last_name'    => 'required|maxlen:75',
            'display_name' => 'maxlen:100',
            'bio'          => 'maxlen:2000',
            'phone'        => 'maxlen:30',
            'address'      => 'maxlen:255',
            'city'         => 'maxlen:75',
            'state'        => 'maxlen:50',
            'zip'          => 'maxlen:20',
            'country'      => 'maxlen:3',
        ], '/profile');

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        // The form's own display name wins; blank means "use my full name".
        // The original always overwrote it, and never saved address, zip or country.
        if ($data['display_name'] === '') {
            $data['display_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        }
        foreach (['phone', 'address', 'city', 'state', 'zip'] as $optional) {
            if ($data[$optional] === '') {
                $data[$optional] = null;
            }
        }
        if ($data['country'] === '') {
            $data['country'] = 'US';
        }

        $data['privacy_settings'] = json_encode([
            'show_email'   => (bool) $this->request->post('show_email', false),
            'show_phone'   => (bool) $this->request->post('show_phone', false),
            'show_address' => (bool) $this->request->post('show_address', false),
        ]);

        // The checkboxes are named weekly_digest / new_message /
        // transaction_recorded. The original read pref_weekly_digest etc., so
        // every profile save switched all three notifications off.
        $data['email_preferences'] = json_encode([
            'weekly_digest'        => (bool) $this->request->post('weekly_digest', false),
            'new_message'          => (bool) $this->request->post('new_message', false),
            'transaction_recorded' => (bool) $this->request->post('transaction_recorded', false),
        ]);

        (new Member($tenantId))->update($userId, $data);

        flash('success', 'Your profile has been updated.');
        $this->redirect('/profile');
    }

    // -------------------------------------------------------------------------
    // Avatar
    // -------------------------------------------------------------------------

    public function uploadAvatar(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        $stored = Uploads::storeImage($_FILES['avatar'] ?? null, 'avatars', $tenantId, (string) $userId, 2 * 1024 * 1024);
        if (is_string($stored) && !str_starts_with($stored, 'uploads/')) {
            flash('error', $stored);
            $this->redirect('/profile');
        }
        if ($stored === null) {
            flash('error', 'No file uploaded or upload error occurred.');
            $this->redirect('/profile');
        }

        $memberModel = new Member($tenantId);
        $member      = $memberModel->find($userId);
        $memberModel->update($userId, ['avatar_path' => $stored]);
        Uploads::delete($member['avatar_path'] ?? null, $tenantId);

        flash('success', 'Avatar updated.');
        $this->redirect('/profile');
    }

    public function deleteAvatar(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId    = (int) $this->tenantId();
        $userId      = (int) $this->currentUserId();
        $memberModel = new Member($tenantId);
        $member      = $memberModel->find($userId);

        // The original used ltrim($path, 'uploads/'), which strips a set of
        // characters rather than a prefix, so the file was never found.
        Uploads::delete($member['avatar_path'] ?? null, $tenantId);
        $memberModel->update($userId, ['avatar_path' => null]);

        flash('success', 'Avatar removed.');
        $this->redirect('/profile');
    }
}
