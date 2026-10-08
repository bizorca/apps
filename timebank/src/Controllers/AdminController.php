<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Auth;
use TimeBank\Core\DB;
use TimeBank\Core\Response;
use TimeBank\Core\Mailer;
use TimeBank\Models\Announcement;
use TimeBank\Models\Category;
use TimeBank\Models\Donation;
use TimeBank\Models\EmailTemplate;
use TimeBank\Models\Endorsement;
use TimeBank\Models\Group;
use TimeBank\Models\Member;
use TimeBank\Models\Notification;
use TimeBank\Models\Offer;
use TimeBank\Models\TenantModel;
use TimeBank\Models\Transaction;

class AdminController extends BaseController
{
    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function dashboard(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId = (int) $this->tenantId();
        $txModel  = new Transaction($tenantId);
        $summary  = $txModel->getTenantSummary($tenantId);

        $activeOffers = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_offers` WHERE tenant_id = ? AND is_active = 1 AND type = 'offer'",
            [$tenantId]
        );

        $currentMonthHours = DB::fetch(
            "SELECT COALESCE(SUM(hours), 0) AS total
             FROM `tm_transactions`
             WHERE tenant_id = ? AND status = 'confirmed'
               AND MONTH(service_date) = MONTH(CURDATE()) AND YEAR(service_date) = YEAR(CURDATE())",
            [$tenantId]
        );

        $pendingMembers = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM `tm_members` WHERE tenant_id = ? AND is_approved = 0 AND is_active = 1",
            [$tenantId]
        );

        $tenant = (new TenantModel())->find($tenantId);
        $this->view('admin/dashboard', [
            // The view reads $stats[...] and $recentTransactions; the original
            // passed only the flat values below, so every card showed 0.
            'stats' => [
                'total_members'      => $summary['active_members'],
                'total_hours'        => $summary['total_hours'],
                'active_offers'      => (int) ($activeOffers['cnt'] ?? 0),
                'total_transactions' => $summary['transaction_count'],
                'month_hours'        => (float) ($currentMonthHours['total'] ?? 0),
                'community_fund'     => (float) ($tenant['community_fund_balance'] ?? 0),
            ],
            'recentTransactions' => $txModel->getRecentActivity($tenantId, 10),
            'totalMembers'      => $summary['active_members'],
            'totalHours'        => $summary['total_hours'],
            'activeOffers'      => (int) ($activeOffers['cnt'] ?? 0),
            'totalTransactions' => $summary['transaction_count'],
            'currentMonthHours' => (float) ($currentMonthHours['total'] ?? 0),
            'pendingMembers'    => (int) ($pendingMembers['cnt'] ?? 0),
        ]);
    }

    // -------------------------------------------------------------------------
    // Members
    // -------------------------------------------------------------------------

    public function members(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId  = (int) $this->tenantId();
        $page      = max(1, (int) $this->request->get('page', '1'));
        $perPage   = 25;
        $status    = $this->request->get('status', '');
        $role      = $this->request->get('role', '');
        $search    = trim($this->request->get('search', ''));

        $conditions = ['m.tenant_id = ?'];
        $params     = [$tenantId];

        if ($status === 'active') {
            $conditions[] = 'm.is_active = 1';
        } elseif ($status === 'inactive') {
            $conditions[] = 'm.is_active = 0';
        } elseif ($status === 'pending') {
            $conditions[] = 'm.is_approved = 0';
            $conditions[] = 'm.is_active = 1';
        }

        if ($role !== '') {
            $conditions[] = 'm.role = ?';
            $params[]     = $role;
        }

        if ($search !== '') {
            $conditions[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR m.display_name LIKE ? OR m.email LIKE ?)";
            $like          = '%' . $search . '%';
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = $like;
        }

        $where  = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        $countRow = DB::fetch("SELECT COUNT(*) AS cnt FROM `tm_members` m WHERE {$where}", $params);
        $total    = (int) ($countRow['cnt'] ?? 0);
        $pages    = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $members = DB::fetchAll(
            "SELECT m.*, m.balance
             FROM `tm_members` m
             WHERE {$where}
             ORDER BY m.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $this->view('admin/members/index', [   // the original rendered 'admin/members', which does not exist (500)
            // The view loops over $members['data']; the original passed the
            // bare list, so the admin members page was always empty.
            'members'    => ['data' => $members, 'total' => $total, 'pages' => $pages, 'current' => $page],
            'pagination' => ['data' => $members, 'total' => $total, 'pages' => $pages, 'current' => $page],
            'filters'    => compact('status', 'role', 'search'),
        ]);
    }

    public function createMember(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $this->view('admin/members/create');
    }

    /**
     * Add a member by email. Everyone signs in with a shared tools account,
     * so this attaches a membership to that account, creating the account
     * (with no usable password) if the address has none yet; the person then
     * sets a password through "Forgot your password?". The original created a
     * per-community login with a password the admin typed in.
     */
    public function storeMember(): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();

        $data = [
            'first_name' => trim((string) $this->request->post('first_name', '')),
            'last_name'  => trim((string) $this->request->post('last_name', '')),
            'email'      => strtolower(trim((string) $this->request->post('email', ''))),
            'role'       => (string) $this->request->post('role', 'member'),
            'bio'        => trim((string) $this->request->post('bio', '')),
            'city'       => trim((string) $this->request->post('city', '')),
            'state'      => trim((string) $this->request->post('state', '')),
        ];

        $this->validate($data, [
            'first_name' => 'required|min:2|maxlen:75',
            'last_name'  => 'required|min:2|maxlen:75',
            'email'      => 'required|email',
            'bio'        => 'maxlen:2000',
            'city'       => 'maxlen:75',
            'state'      => 'maxlen:50',
        ], '/admin/members/create');

        // Only a super admin (or the site owner) may create admins.
        $allowedRoles = Auth::isSuperAdmin() ? ['member', 'admin', 'super_admin'] : ['member'];
        $role         = in_array($data['role'], $allowedRoles, true) ? $data['role'] : 'member';

        $account = DB::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$data['email']]);
        if ($account && DB::fetch('SELECT id FROM `tm_members` WHERE tenant_id = ? AND user_id = ? LIMIT 1', [$tenantId, (int) $account['id']])) {
            $_SESSION['tm_errors'] = ['email' => ['That person is already a member of this timebank.']];
            $_SESSION['tm_old']    = $data;
            flash('error', 'That person is already a member of this timebank.');
            $this->redirect('/admin/members/create');
        }

        $createdAccount = false;
        DB::transaction(function () use (&$account, &$createdAccount, $data, $tenantId, $role): void {
            if (!$account) {
                DB::insert('users', [
                    'email'         => $data['email'],
                    'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'name'          => trim($data['first_name'] . ' ' . $data['last_name']),
                ]);
                $account = DB::fetch('SELECT * FROM users WHERE id = ?', [(int) DB::lastInsertId()]);
                $createdAccount = true;
            }

            DB::insert('tm_members', [
                'tenant_id'    => $tenantId,
                'user_id'      => (int) $account['id'],
                'email'        => $account['email'],
                'first_name'   => $data['first_name'],
                'last_name'    => $data['last_name'],
                'display_name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'role'         => $role,
                'bio'          => $data['bio'] !== '' ? $data['bio'] : null,
                'city'         => $data['city'] !== '' ? $data['city'] : null,
                'state'        => $data['state'] !== '' ? $data['state'] : null,
                'is_active'    => $this->request->post('is_active') !== null ? 1 : 0,   // unchecked boxes send nothing
                'is_approved'  => $this->request->post('is_approved') !== null ? 1 : 0,
                'balance'      => '0.00',
            ]);
        });

        if ($this->request->post('send_welcome', '')) {
            $tenant = $this->tenant;
            Mailer::sendTemplate($tenantId, 'welcome', $account['email'], $data['first_name'] . ' ' . $data['last_name'], [
                'first_name'      => $data['first_name'],
                'community_name'  => $tenant['name'] ?? 'Our Timebank',
                'welcome_credits' => $tenant['welcome_credits'] ?? 0,
                'currency_name'   => $tenant['currency_name'] ?? 'hours',
                'login_url'       => url_abs('/dashboard'),
            ]);
        }

        flash('success', $createdAccount
            ? 'Member added. They have no password yet: ask them to use "Forgot your password?" at ' . TM_ORIGIN . '/account/forgot.php to set one.'
            : 'Member added to their existing Bizorca Tools account.');
        $this->redirect('/admin/members');
    }

    /**
     * Approve a pending member. The views post here from three places; the
     * original had no such route or handler. Approval grants the welcome
     * credits that joining would have granted had approval not been required
     * (the original never granted them at all in approval-required communities).
     */
    public function approveMember(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $member   = (new Member($tenantId))->find($id);
        if (!$member) {
            flash('error', 'Member not found.');
            $this->redirect('/admin/members');
        }

        if ((int) $member['is_approved'] === 0) {
            $tenant  = (new TenantModel())->find($tenantId);
            $welcome = round((float) ($tenant['welcome_credits'] ?? 0), 2);
            DB::transaction(function () use ($tenantId, $id, $welcome): void {
                DB::update('tm_members', ['is_approved' => 1, 'is_active' => 1], ['id' => $id, 'tenant_id' => $tenantId]);
                $already = DB::fetch(
                    "SELECT id FROM `tm_transactions` WHERE tenant_id = ? AND provider_id = ? AND receiver_id IS NULL AND description = 'Welcome credits' LIMIT 1",
                    [$tenantId, $id]
                );
                if ($welcome > 0 && !$already) {
                    (new Transaction($tenantId))->creditFromCommunityFund($id, $welcome, 'Welcome credits', (int) $this->currentUserId());
                }
            });
            flash('success', 'Member approved.');
        }

        Response::back('/admin/members');
    }

    public function showMember(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId    = (int) $this->tenantId();
        $memberModel = new Member($tenantId);
        $member      = $memberModel->findWithStats($id);

        if (!$member || (int) $member['tenant_id'] !== $tenantId) {
            flash('error', 'Member not found.');
            $this->redirect('/admin/members');
        }

        $txModel      = new Transaction($tenantId);
        $txResult     = $txModel->getMemberTransactions($id, $tenantId, null, null, 1, 10);
        $endorseModel = new Endorsement($tenantId);
        $endorsements = $endorseModel->getForMember($id, $tenantId);

        $this->view('admin/members/show', [
            // The view reads $memberStats; the original never passed it (all zeros).
            'memberStats'  => [
                'total_transactions' => (int) $member['transactions_as_provider'] + (int) $member['transactions_as_receiver'],
                'hours_given'        => $member['total_hours_given'],
                'hours_received'     => $member['total_hours_received'],
            ],
            'member'       => $member,
            'transactions' => $txResult['data'],
            'endorsements' => $endorsements,
        ]);
    }

    public function toggleMember(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId    = (int) $this->tenantId();
        $memberModel = new Member($tenantId);
        $member      = $memberModel->find($id);

        if (!$member || (int) $member['tenant_id'] !== $tenantId) {
            flash('error', 'Member not found.');
            $this->redirect('/admin/members');
        }

        $newActive = (int) $member['is_active'] === 1 ? 0 : 1;
        $memberModel->update($id, ['is_active' => $newActive]);

        // Also handle is_approved when activating
        if ($newActive === 1 && !(bool) $member['is_approved']) {
            $memberModel->update($id, ['is_approved' => 1]);
        }

        $statusLabel = $newActive ? 'activated' : 'deactivated';
        flash('success', 'Member account ' . $statusLabel . '.');
        Response::back('/admin/members');
    }

    public function recordHours(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $adminId  = (int) $this->currentUserId();

        $data = [
            'provider_id'  => $this->request->post('provider_id', ''),
            'receiver_id'  => $this->request->post('receiver_id', ''),
            'hours'        => $this->request->post('hours', ''),
            'service_date' => $this->request->post('service_date', date('Y-m-d')),
            'description'  => trim($this->request->post('description', '')),
        ];

        $this->validate($data, [
            'hours'        => 'required|numeric|min:0.25',
            'service_date' => 'required',
            'description'  => 'required',
        ], '/admin/members/' . $id);

        $providerId = $data['provider_id'] !== '' ? (int) $data['provider_id'] : $id;
        $receiverId = $data['receiver_id'] !== '' ? (int) $data['receiver_id'] : null;
        $hours      = (float) $data['hours'];

        // An admin may grant hours with no receiver (an adjustment), but both
        // parties must belong to this community and the hours must be sane.
        if (!$this->communityMember($providerId) || ($receiverId !== null && (!$this->communityMember($receiverId) || $receiverId === $providerId))) {
            flash('error', 'Both people must be different, active members of this timebank.');
            Response::back('/admin/members/' . $id);
        }
        if ($hours <= 0 || abs(fmod($hours * 4, 1)) > 0.001 || $hours > 24) {
            flash('error', 'Hours must be in quarter-hour increments, from 0.25 to 24.');
            Response::back('/admin/members/' . $id);
        }

        $txModel = new Transaction($tenantId);
        $txModel->record([
            'tenant_id'    => $tenantId,
            'provider_id'  => $providerId,
            'receiver_id'  => $receiverId,
            'hours'        => (float) $data['hours'],
            'service_date' => $data['service_date'],
            'description'  => $data['description'],
            'recorded_by'  => $adminId,
            'type'         => 'one_to_one',
        ]);

        flash('success', 'Transaction recorded.');
        Response::back('/admin/members/' . $id);
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    public function transactions(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId = (int) $this->tenantId();
        $page     = max(1, (int) $this->request->get('page', '1'));
        $perPage  = 25;
        $from     = $this->request->get('from', '') ?: null;
        $to       = $this->request->get('to', '') ?: null;
        $memberId = $this->request->get('member_id', '') !== '' ? (int) $this->request->get('member_id') : null;

        $conditions = ['t.tenant_id = ?'];
        $params     = [$tenantId];

        if ($from !== null) {
            $conditions[] = 't.service_date >= ?';
            $params[]     = $from;
        }
        if ($to !== null) {
            $conditions[] = 't.service_date <= ?';
            $params[]     = $to;
        }
        if ($memberId !== null) {
            $conditions[] = '(t.provider_id = ? OR t.receiver_id = ?)';
            $params[]     = $memberId;
            $params[]     = $memberId;
        }

        $where  = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        $countRow = DB::fetch("SELECT COUNT(*) AS cnt FROM `tm_transactions` t WHERE {$where}", $params);
        $total    = (int) ($countRow['cnt'] ?? 0);
        $pages    = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $transactions = DB::fetchAll(
            "SELECT t.*,
                COALESCE(p.display_name, CONCAT(p.first_name, ' ', p.last_name)) AS provider_name,
                COALESCE(r.display_name, CONCAT(r.first_name, ' ', r.last_name)) AS receiver_name
             FROM `tm_transactions` t
             JOIN `tm_members` p ON p.id = t.provider_id
             LEFT JOIN `tm_members` r ON r.id = t.receiver_id
             WHERE {$where}
             ORDER BY t.service_date DESC, t.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $memberModel = new Member($tenantId);

        $this->view('admin/transactions', [
            'transactions' => ['data' => $transactions, 'total' => $total, 'pages' => $pages, 'current' => $page],   // the view reads ['data'] (and pagination); the original passed the bare list, so this page was always empty
            'pagination'   => ['data' => $transactions, 'total' => $total, 'pages' => $pages, 'current' => $page],
            'members'      => $memberModel->findActive($tenantId),
            'filters'      => compact('from', 'to', 'memberId'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    public function reports(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $this->view('admin/reports/index', [
            'reports' => [
                ['slug' => 'member-balances',       'name' => 'Member Balances',         'description' => 'Current balance for every member.'],
                ['slug' => 'transaction-history',   'name' => 'Transaction History',     'description' => 'Full confirmed transaction log.'],
                ['slug' => 'category-breakdown',    'name' => 'Category Breakdown',      'description' => 'Hours exchanged grouped by category.'],
                ['slug' => 'member-hours-summary',  'name' => 'Member Hours Summary',    'description' => 'Hours given, received, and net per member.'],
                ['slug' => 'inactive-members',      'name' => 'Inactive Members',        'description' => 'Members with no transactions in 90+ days.'],
                ['slug' => 'new-members',           'name' => 'New Members',             'description' => 'Members registered in the last 30 days.'],
            ],
        ]);
    }

    public function report(string $slug): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId = (int) $this->tenantId();
        $data     = [];

        $txModel = new Transaction($tenantId);

        switch ($slug) {
            case 'member-balances':
                $data['rows'] = DB::fetchAll(
                    "SELECT id, first_name, last_name, display_name, email, balance, role, is_active, created_at
                     FROM `tm_members` WHERE tenant_id = ? ORDER BY balance DESC",
                    [$tenantId]
                );
                break;

            case 'transaction-history':
                $data['rows'] = DB::fetchAll(
                    "SELECT t.*,
                        COALESCE(p.display_name, CONCAT(p.first_name, ' ', p.last_name)) AS provider_name,
                        COALESCE(r.display_name, CONCAT(r.first_name, ' ', r.last_name)) AS receiver_name
                     FROM `tm_transactions` t
                     JOIN `tm_members` p ON p.id = t.provider_id
                     LEFT JOIN `tm_members` r ON r.id = t.receiver_id
                     WHERE t.tenant_id = ? AND t.status = 'confirmed'
                     ORDER BY t.service_date DESC",
                    [$tenantId]
                );
                break;

            case 'category-breakdown':
                $data['rows'] = $txModel->getCategoryBreakdown($tenantId);
                break;

            case 'member-hours-summary':
                $data['rows'] = $txModel->getMemberHoursSummary($tenantId);
                break;

            case 'inactive-members':
                $data['rows'] = DB::fetchAll(
                    "SELECT m.id, m.first_name, m.last_name, m.display_name, m.email,
                            m.balance, m.last_login_at, m.created_at,
                            MAX(t.service_date) AS last_transaction_date
                     FROM `tm_members` m
                     LEFT JOIN `tm_transactions` t ON (t.provider_id = m.id OR t.receiver_id = m.id)
                                              AND t.status = 'confirmed'
                     WHERE m.tenant_id = ? AND m.is_active = 1 AND m.is_approved = 1
                     GROUP BY m.id
                     HAVING last_transaction_date IS NULL
                         OR last_transaction_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                     ORDER BY last_transaction_date ASC",
                    [$tenantId]
                );
                break;

            case 'new-members':
                $data['rows'] = DB::fetchAll(
                    "SELECT id, first_name, last_name, display_name, email, role, balance, created_at
                     FROM `tm_members`
                     WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     ORDER BY created_at DESC",
                    [$tenantId]
                );
                break;

            default:
                flash('error', 'Unknown report.');
                $this->redirect('/admin/reports');
        }

        // The views read $members (or $categories); the original passed only
        // $rows, so every report rendered empty.
        $data[$slug === 'category-breakdown' ? 'categories' : 'members'] = $data['rows'];

        if ($this->request->get('export', '') === 'csv') {
            $this->csv($slug, $data['rows']);
        }

        $this->view('admin/reports/' . $slug, array_merge($data, ['slug' => $slug]));
    }

    /** The "Export CSV" button on each report (linked, but never handled before). */
    private function csv(string $slug, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $slug . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ',', '"', '\\');
            foreach ($rows as $row) {
                // A leading = + - @ makes spreadsheet apps run the cell as a formula.
                fputcsv($out, array_map(fn($v) => is_string($v) && !is_numeric($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row), ',', '"', '\\');
            }
        }
        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    public function categories(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId      = (int) $this->tenantId();
        $categoryModel = new Category($tenantId);

        $categories = DB::fetchAll(
            "SELECT c.*, COUNT(o.id) AS offer_count
             FROM `tm_categories` c
             LEFT JOIN `tm_offers` o ON o.category_id = c.id
             WHERE c.tenant_id = ?
             GROUP BY c.id
             ORDER BY c.name ASC",
            [$tenantId]
        );

        $this->view('admin/categories', [
            'categories' => $categories,
        ]);
    }

    public function storeCategory(): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();

        $data = [
            'name' => trim($this->request->post('name', '')),
            'icon' => trim($this->request->post('icon', '')),
        ];

        $this->validate($data, [
            'name' => 'required|max:100',
        ], '/admin/categories');

        $categoryModel = new Category($tenantId);
        $categoryModel->create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?: 'tag',   // icon is NOT NULL; the original inserted NULL, so adding a category always failed
        ]);

        flash('success', 'Category created.');
        $this->redirect('/admin/categories');
    }

    public function deleteCategory(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();

        // The id is in the route; the original read a category_id field the
        // form never sent, so it always tried to delete category 0.
        $inUse = DB::fetch(
            "SELECT id FROM `tm_offers` WHERE category_id = ? AND tenant_id = ? LIMIT 1",
            [$id, $tenantId]
        );
        if ($inUse) {
            flash('error', 'Cannot delete a category that has offers assigned to it. Hide it instead.');
            $this->redirect('/admin/categories');
        }

        (new Category($tenantId))->delete($id);

        flash('success', 'Category deleted.');
        $this->redirect('/admin/categories');
    }

    /** Rename a category (the form existed; the original had no handler). */
    public function updateCategory(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $name = trim((string) $this->request->post('name', ''));
        $this->validate(['name' => $name], ['name' => 'required|max:100'], '/admin/categories');

        (new Category((int) $this->tenantId()))->update($id, ['name' => $name]);

        flash('success', 'Category updated.');
        $this->redirect('/admin/categories');
    }

    /** Show or hide a category (the form existed; the original had no handler). */
    public function toggleCategory(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $model    = new Category($tenantId);
        $cat      = $model->find($id);
        if ($cat) {
            $model->update($id, ['is_active' => (int) $cat['is_active'] === 1 ? 0 : 1]);
            flash('success', (int) $cat['is_active'] === 1 ? 'Category hidden.' : 'Category shown.');
        }
        $this->redirect('/admin/categories');
    }

    // -------------------------------------------------------------------------
    // Email Templates
    // -------------------------------------------------------------------------

    public function emailTemplates(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId       = (int) $this->tenantId();
        $templateModel  = new EmailTemplate($tenantId);

        $this->view('admin/email-templates/index', [
            'templates' => $templateModel->getAllForTenant($tenantId),
        ]);
    }

    public function editEmailTemplate(string $slug): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId      = (int) $this->tenantId();
        $templateModel = new EmailTemplate($tenantId);
        $template      = $templateModel->getBySlug($tenantId, $slug);

        if (!$template) {
            flash('error', 'Email template not found.');
            $this->redirect('/admin/email-templates');
        }

        $this->view('admin/email-templates/edit', [
            'template' => $template,
        ]);
    }

    public function updateEmailTemplate(string $slug): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId      = (int) $this->tenantId();
        $templateModel = new EmailTemplate($tenantId);
        $template      = $templateModel->getBySlug($tenantId, $slug);

        if (!$template) {
            flash('error', 'Email template not found.');
            $this->redirect('/admin/email-templates');
        }

        $data = [
            'subject' => trim($this->request->post('subject', '')),
            'body'    => trim($this->request->post('body', '')),
        ];

        $this->validate($data, [
            'subject' => 'required',
            'body'    => 'required',
        ], '/admin/email-templates/' . $slug . '/edit');

        DB::update(
            'tm_email_templates',
            [
                'subject' => $data['subject'],
                'body'    => $data['body'],
            ],
            ['id' => (int) $template['id']]
        );

        flash('success', 'Email template updated.');
        $this->redirect('/admin/email-templates');
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public function settings(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId    = (int) $this->tenantId();
        $tenantModel = new TenantModel();
        $tenant      = $tenantModel->find($tenantId);
        $settings    = $tenantModel->getSettings($tenant ?? []);

        $this->view('admin/settings', [
            'tenant'   => $tenant,
            'settings' => $settings,
        ]);
    }

    public function updateSettings(): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();

        $data = [
            'name'            => trim($this->request->post('name', '')),
            'tagline'         => trim($this->request->post('tagline', '')),
            'timezone'        => trim($this->request->post('timezone', 'UTC')),
            'currency_name'        => trim((string) $this->request->post('currency_name', 'Hour')),
            'currency_name_plural' => trim((string) $this->request->post('currency_name_plural', 'Hours')),
            'welcome_credits'      => (string) $this->request->post('welcome_credits', '0'),
        ];

        $this->validate($data, [
            'name'                 => 'required|max:150',
            'currency_name'        => 'required|max:50',
            'currency_name_plural' => 'required|max:50',
            'welcome_credits'      => 'numeric',
        ], '/admin/settings');

        $welcome = (float) $data['welcome_credits'];
        if ($welcome < 0 || $welcome > 24 || abs(fmod($welcome * 4, 1)) > 0.001) {
            flash('error', 'Welcome credits must be 0 to 24, in quarter-hour increments.');
            $this->redirect('/admin/settings');
        }
        if (!in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $data['timezone'] = 'America/Chicago';
        }

        $tenantModel = new TenantModel();
        $tenantModel->update($tenantId, [
            'name'            => $data['name'],
            'tagline'         => $data['tagline'],
            'timezone'        => $data['timezone'],
            'currency_name'        => $data['currency_name'],
            'currency_name_plural' => $data['currency_name_plural'],   // the form sent it; the original never saved it
            'welcome_credits'      => Transaction::decimal($welcome),
        ]);

        $settingsData = [
            'allow_self_registration' => (bool) $this->request->post('allow_self_registration', false),
            'require_approval'        => (bool) $this->request->post('require_approval', false),
        ];
        $tenantModel->updateSettings($tenantId, $settingsData);

        flash('success', 'Settings saved.');
        $this->redirect('/admin/settings');
    }

    // -------------------------------------------------------------------------
    // Donations
    // -------------------------------------------------------------------------

    public function donations(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId      = (int) $this->tenantId();
        $page          = max(1, (int) $this->request->get('page', '1'));
        $status        = $this->request->get('status', '') ?: null;
        $donationModel = new Donation($tenantId);
        $result        = $donationModel->getForTenant($tenantId, $status, $page, 25);

        $this->view('admin/donations', [
            'donations'  => $result['data'],
            'pagination' => $result,
            'status'     => $status,
        ]);
    }

    public function forgiveDonation(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId      = (int) $this->tenantId();
        $donationModel = new Donation($tenantId);
        $donationModel->forgive($id);

        flash('success', 'Donation forgiven.');
        Response::back('/admin/donations');
    }

    public function requestDonations(): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $tenantId      = (int) $this->tenantId();
        $donationModel = new Donation($tenantId);
        $count         = $donationModel->requestAll($tenantId);

        flash('success', 'Donation requests sent to ' . $count . ' member' . ($count !== 1 ? 's' : '') . '.');
        $this->redirect('/admin/donations');
    }

    // -------------------------------------------------------------------------
    // Groups
    // -------------------------------------------------------------------------

    public function groups(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $tenantId   = (int) $this->tenantId();
        $groupModel = new Group($tenantId);

        $groups = DB::fetchAll(
            "SELECT g.*, COUNT(gm.member_id) AS member_count,
                COALESCE(c.display_name, CONCAT(c.first_name, ' ', c.last_name)) AS created_by_name
             FROM `tm_groups` g
             LEFT JOIN `tm_group_members` gm ON gm.group_id = g.id
             LEFT JOIN `tm_members` c ON c.id = g.created_by
             WHERE g.tenant_id = ?
             GROUP BY g.id
             ORDER BY g.name ASC",
            [$tenantId]
        );

        $this->view('admin/groups', [
            'groups' => $groups,
        ]);
    }

    /** Delete a group (the admin page had the button; the original had no handler). */
    public function deleteGroup(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->verifyCsrf();

        $deleted = (new Group((int) $this->tenantId()))->delete($id);
        flash($deleted ? 'success' : 'error', $deleted ? 'Group deleted.' : 'Group not found.');
        $this->redirect('/admin/groups');
    }
}
