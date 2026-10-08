<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Auth;
use TimeBank\Core\Uploads;
use TimeBank\Models\Category;
use TimeBank\Models\Offer;

class OfferController extends BaseController
{
    // -------------------------------------------------------------------------
    // Offers
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $page       = max(1, (int) $this->request->get('page', '1'));
        $categoryId = $this->request->get('category', '') !== '' ? (int) $this->request->get('category') : null;

        $offerModel = new Offer($tenantId);
        $result     = $offerModel->browseAll($tenantId, 'offer', $categoryId, $page, 20);

        $categoryModel = new Category($tenantId);

        $this->view('offers/index', [
            'offers'      => $result,   // the view reads ['data'] (and pagination); the original passed the bare list, so this page was always empty
            'pagination'  => $result,
            'categories'  => $categoryModel->all('name ASC'),
            'categoryId'  => $categoryId,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $tenantId      = (int) $this->tenantId();
        $categoryModel = new Category($tenantId);

        $this->view('offers/create', [
            'categories' => $categoryModel->all('name ASC'),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        $data = [
            'title'       => trim($this->request->post('title', '')),
            'description' => trim($this->request->post('description', '')),
            'category_id' => $this->request->post('category_id', ''),
        ];

        $this->validate($data, [
            'title'       => 'required|max:200',
            'description' => 'required',
            'category_id' => 'required',
        ], '/offers/create');

        $this->requireCategory($data['category_id'], '/offers/create');
        $imagePath = $this->handleOfferImage($tenantId, $userId);

        $offerModel = new Offer($tenantId);
        $offerModel->create([
            'member_id'   => $userId,
            'title'       => $data['title'],
            'description' => $data['description'],
            'category_id' => (int) $data['category_id'],
            'type'        => 'offer',
            'is_active'   => 1,
            'image_path'  => $imagePath,
        ]);

        flash('success', 'Your offer has been posted.');
        $this->redirect('/offers');
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $offerModel = new Offer($tenantId);
        $offer      = $offerModel->getWithMember($id);

        // Requests are listed with links to this same page, and the view
        // handles both types; the original refused anything but an offer, so
        // every request link said "Offer not found".
        if (!$offer) {
            flash('error', 'Offer not found.');
            $this->redirect('/offers');
        }

        $this->view('offers/show', ['offer' => $offer]);
    }

    public function edit(int $id): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $userId     = (int) $this->currentUserId();
        $offerModel = new Offer($tenantId);
        $offer      = $offerModel->find($id);

        if (!$offer) {
            flash('error', 'Offer not found.');
            $this->redirect('/offers');
        }

        if ((int) $offer['member_id'] !== $userId && !Auth::isAdmin()) {
            flash('error', 'You do not have permission to edit this offer.');
            $this->redirect('/offers/' . $id);
        }

        $categoryModel = new Category($tenantId);

        $this->view('offers/edit', [
            'offer'      => $offer,
            'categories' => $categoryModel->all('name ASC'),
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId   = (int) $this->tenantId();
        $userId     = (int) $this->currentUserId();
        $offerModel = new Offer($tenantId);
        $offer      = $offerModel->find($id);

        if (!$offer) {
            flash('error', 'Offer not found.');
            $this->redirect('/offers');
        }

        if ((int) $offer['member_id'] !== $userId && !Auth::isAdmin()) {
            flash('error', 'You do not have permission to edit this offer.');
            $this->redirect('/offers/' . $id);
        }

        $data = [
            'title'       => trim($this->request->post('title', '')),
            'description' => trim($this->request->post('description', '')),
            'category_id' => $this->request->post('category_id', ''),
        ];

        $this->validate($data, [
            'title'       => 'required|max:200',
            'description' => 'required',
            'category_id' => 'required',
        ], '/offers/' . $id . '/edit');

        $this->requireCategory($data['category_id'], '/offers/' . $id . '/edit');

        $updateData = [
            'title'       => $data['title'],
            'description' => $data['description'],
            'category_id' => (int) $data['category_id'],
            'is_active'   => (bool) $this->request->post('is_active', true) ? 1 : 0,
        ];

        // Handle new image upload — keep old one if none provided
        $newImage = $this->handleOfferImage($tenantId, (int) $offer['member_id']);
        if ($newImage !== null) {
            // Delete old image if present
            Uploads::delete($offer['image_path'] ?? null, $tenantId);
            $updateData['image_path'] = $newImage;
        }

        $offerModel->update($id, $updateData);

        flash('success', 'Offer updated.');
        $this->redirect('/offers/' . $id);
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId   = (int) $this->tenantId();
        $userId     = (int) $this->currentUserId();
        $offerModel = new Offer($tenantId);
        $offer      = $offerModel->find($id);

        if (!$offer) {
            flash('error', 'Offer not found.');
            $this->redirect('/offers');
        }

        if ((int) $offer['member_id'] !== $userId && !Auth::isAdmin()) {
            flash('error', 'You do not have permission to delete this offer.');
            $this->redirect('/offers/' . $id);
        }

        Uploads::delete($offer['image_path'] ?? null, $tenantId);

        $offerModel->delete($id);

        flash('success', 'Offer deleted.');
        $this->redirect('/offers');
    }

    // -------------------------------------------------------------------------
    // Requests
    // -------------------------------------------------------------------------

    public function requests(): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        $page       = max(1, (int) $this->request->get('page', '1'));
        $categoryId = $this->request->get('category', '') !== '' ? (int) $this->request->get('category') : null;

        $offerModel = new Offer($tenantId);
        $result     = $offerModel->browseAll($tenantId, 'request', $categoryId, $page, 20);

        $categoryModel = new Category($tenantId);

        $this->view('offers/requests', [
            'requests'    => $result['data'],
            'offers'      => $result,   // the view reads ['data'] (and pagination); the original passed the bare list, so this page was always empty
            'pagination'  => $result,
            'categories'  => $categoryModel->all('name ASC'),
            'categoryId'  => $categoryId,
        ]);
    }

    public function createRequest(): void
    {
        $this->requireAuth();

        $tenantId      = (int) $this->tenantId();
        $categoryModel = new Category($tenantId);

        $this->view('offers/create-request', [
            'categories' => $categoryModel->all('name ASC'),
        ]);
    }

    public function storeRequest(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        $data = [
            'title'       => trim($this->request->post('title', '')),
            'description' => trim($this->request->post('description', '')),
            'category_id' => $this->request->post('category_id', ''),
        ];

        $this->validate($data, [
            'title'       => 'required|max:200',
            'description' => 'required',
            'category_id' => 'required',
        ], '/requests/create');

        $this->requireCategory($data['category_id'], '/requests/create');
        $imagePath = $this->handleOfferImage($tenantId, $userId);

        $offerModel = new Offer($tenantId);
        $offerModel->create([
            'member_id'   => $userId,
            'title'       => $data['title'],
            'description' => $data['description'],
            'category_id' => (int) $data['category_id'],
            'type'        => 'request',
            'is_active'   => 1,
            'image_path'  => $imagePath,
        ]);

        flash('success', 'Your request has been posted.');
        $this->redirect('/requests');
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /** Optional offer image: the stored path, or null when none was sent. */
    private function handleOfferImage(int $tenantId, int $memberId): ?string
    {
        $stored = Uploads::storeImage($_FILES['image'] ?? null, 'offers', $tenantId, (string) $memberId, 5 * 1024 * 1024);
        if (is_string($stored) && !str_starts_with($stored, 'uploads/')) {
            flash('error', $stored);
            \TimeBank\Core\Response::back('/offers');
        }
        return $stored;
    }

    /** The category must be an active one of THIS community (the original took any id). */
    private function requireCategory(string $categoryId, string $back): void
    {
        $ok = \TimeBank\Core\DB::fetch(
            'SELECT id FROM `tm_categories` WHERE id = ? AND tenant_id = ? AND is_active = 1',
            [(int) $categoryId, (int) $this->tenantId()]
        );
        if (!$ok) {
            flash('error', 'Choose a category from the list.');
            $this->redirect($back);
        }
    }
}
