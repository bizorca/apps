<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Auth;
use TimeBank\Core\DB;
use TimeBank\Core\Mailer;
use TimeBank\Models\Category;
use TimeBank\Models\Member;
use TimeBank\Models\Notification;
use TimeBank\Models\Offer;
use TimeBank\Models\Transaction;

class TransactionController extends BaseController
{
    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $page     = max(1, (int) $this->request->get('page', '1'));

        $txModel = new Transaction($tenantId);
        $result  = $txModel->getMemberTransactions($userId, $tenantId, null, null, $page, 25);

        $this->view('transactions/index', [
            'transactions' => $result,   // the view reads ['data'] (and pagination); the original passed the bare list, so this page was always empty
            'pagination'   => $result,
        ]);
    }

    // -------------------------------------------------------------------------
    // Record
    // -------------------------------------------------------------------------

    public function create(): void
    {
        $this->requireAuth();

        $tenantId   = (int) $this->tenantId();
        // The views link with ?offer=ID (and ?provider=ID); the original read
        // ?offer_id= and so never prefilled from an offer.
        $offerParam = (string) ($this->request->get('offer_id', '') ?: $this->request->get('offer', ''));
        $offerId    = $offerParam !== '' ? (int) $offerParam : null;

        $memberModel   = new Member($tenantId);
        $categoryModel = new Category($tenantId);
        $members       = $memberModel->findActive($tenantId);
        $categories    = $categoryModel->all('name ASC');

        $selectedOffer = null;
        if ($offerId !== null) {
            $offerModel    = new Offer($tenantId);
            $selectedOffer = $offerModel->getWithMember($offerId);
        }

        $this->view('transactions/record', [
            'members'       => $members,
            'categories'    => $categories,
            'selectedOffer' => $selectedOffer,
            'offer'         => $selectedOffer,   // the view reads $offer; the original passed only selectedOffer
            'offer_list'    => \TimeBank\Core\DB::fetchAll(   // "link to an offer" dropdown; never passed before
                "SELECT id, title FROM `tm_offers` WHERE tenant_id = ? AND is_active = 1 AND type = 'offer' ORDER BY title",
                [$tenantId]
            ),
            'currentUserId' => (int) $this->currentUserId(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $isAdmin  = Auth::isAdmin();

        $data = [
            'provider_id'  => (string) $this->request->post('provider_id', ''),
            'receiver_id'  => (string) $this->request->post('receiver_id', ''),
            'hours'        => (string) $this->request->post('hours', ''),
            'prep_hours'   => (string) $this->request->post('prep_hours', '0'),
            'service_date' => (string) $this->request->post('service_date', ''),
            'description'  => trim((string) $this->request->post('description', '')),
            'offer_id'     => (string) $this->request->post('offer_id', ''),
            'type'         => (string) $this->request->post('type', 'one_to_one'),
        ];

        $this->validate($data, [
            'provider_id'  => 'required|integer',
            'hours'        => 'required|numeric|min:0.25',
            'service_date' => 'required',
            'description'  => 'required|max:1000',
        ], '/transactions/record');

        $hours = (float) $data['hours'];
        $fail  = function (string $field, string $message) use ($data): never {
            $_SESSION['tm_errors'] = [$field => [$message]];
            $_SESSION['tm_old']    = $data;
            flash('error', $message);
            $this->redirect('/transactions/record');
        };

        if ($hours < 0.25 || abs(fmod($hours * 4, 1)) > 0.001 || $hours > 24) {
            $fail('hours', 'Hours must be in quarter-hour increments (0.25, 0.50, 0.75, 1.00, etc.), up to 24.');
        }
        $prep = (float) ($data['prep_hours'] !== '' ? $data['prep_hours'] : 0);
        if ($prep < 0 || abs(fmod($prep * 4, 1)) > 0.001 || $prep > 24) {
            $fail('prep_hours', 'Prep hours must be in quarter-hour increments, from 0 to 24.');
        }
        $date = \DateTime::createFromFormat('!Y-m-d', $data['service_date']);
        if (!$date || $date->format('Y-m-d') !== $data['service_date']) {
            $fail('service_date', 'Please give the service date as a valid date.');
        }

        // Every party must be an active member of THIS community. The original
        // accepted any member id from the form.
        $providerId = (int) $data['provider_id'];
        if (!$this->communityMember($providerId)) {
            $fail('provider_id', 'Choose a provider from this timebank.');
        }

        $offerId = null;
        if ($data['offer_id'] !== '') {
            $offer = (new Offer($tenantId))->find((int) $data['offer_id']);
            $offerId = $offer ? (int) $offer['id'] : null;
        }

        $txModel = new Transaction($tenantId);
        $base = [
            'provider_id'  => $providerId,
            'hours'        => $hours,
            'prep_hours'   => $prep,
            'service_date' => $data['service_date'],
            'description'  => $data['description'],
            'recorded_by'  => $userId,
            'offer_id'     => $offerId,
        ];

        if ($data['type'] === 'one_to_many') {
            // A class or workshop: the provider earns the hours from each
            // participant. The original ignored the participants it asked for
            // and credited the provider from nothing.
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->post('participants', [])))));
            $ids = array_values(array_filter($ids, fn($id) => $id !== $providerId));
            if (!$ids) {
                $fail('participants', 'Add at least one participant other than the provider.');
            }
            foreach ($ids as $id) {
                if (!$this->communityMember($id)) {
                    $fail('participants', 'Every participant must be a member of this timebank.');
                }
            }
            if (!$isAdmin && $providerId !== $userId && !in_array($userId, $ids, true)) {
                $fail('provider_id', 'You must be the provider or one of the participants.');
            }

            // One ordinary transaction per participant, all or nothing, so
            // every statement, balance report and endorsement sees the class
            // exactly like a one-to-one exchange.
            $txId = DB::transaction(function () use ($txModel, $base, $ids): int {
                $first = 0;
                foreach ($ids as $id) {
                    $txId  = $txModel->record($base + ['type' => 'one_to_many', 'receiver_id' => $id]);
                    $first = $first ?: $txId;
                }
                return $first;
            });
            $otherPartyIds = $providerId === $userId ? $ids : [$providerId];
        } elseif ($data['type'] === 'one_to_one') {
            $receiverId = $data['receiver_id'] !== '' ? (int) $data['receiver_id'] : 0;
            if (!$receiverId || !$this->communityMember($receiverId)) {
                // Without a receiver nobody pays: the original let any member
                // credit themselves this way.
                $fail('receiver_id', 'Choose who received the service.');
            }
            if ($receiverId === $providerId) {
                $fail('receiver_id', 'The provider and the receiver must be different people.');
            }
            if (!$isAdmin && $providerId !== $userId && $receiverId !== $userId) {
                $fail('provider_id', 'You must be either the provider or the receiver in the transaction.');
            }

            $txId = $txModel->record($base + ['type' => 'one_to_one', 'receiver_id' => $receiverId]);
            $otherPartyIds = [$providerId === $userId ? $receiverId : $providerId];
        } else {
            // "Group Project" (many providers, one receiver) had no way to name
            // the providers, so the original recorded it with no one paying.
            $fail('type', 'Group projects are recorded as one transaction per provider.');
        }

        $memberModel   = new Member($tenantId);
        $currentMember = $this->currentUser();
        $currentName   = ($currentMember['display_name'] ?? '') ?: trim(($currentMember['first_name'] ?? '') . ' ' . ($currentMember['last_name'] ?? ''));
        $notifModel    = new Notification($tenantId);

        foreach ($otherPartyIds as $otherPartyId) {
            if ($otherPartyId === $userId) {
                continue;
            }
            $otherMember = $memberModel->find($otherPartyId);
            if (!$otherMember) {
                continue;
            }

            if ($memberModel->getEmailPreferences($otherMember)['transaction_recorded']) {
                Mailer::sendTemplate(
                    $tenantId,
                    'transaction_recorded',
                    $otherMember['email'],
                    $otherMember['first_name'] . ' ' . $otherMember['last_name'],
                    [
                        'first_name'           => $otherMember['first_name'],
                        'hours'                => Transaction::decimal($hours),
                        'currency_unit'        => $this->tenant['currency_name'] ?? 'Hour',
                        'other_member'         => $currentName,
                        'description'          => $data['description'],
                        'service_date'         => $data['service_date'],
                        'balance'              => number_format((float) $otherMember['balance'], 2),
                        'currency_name_plural' => $this->tenant['currency_name_plural'] ?? 'Hours',
                        'community_name'       => $this->tenant['name'] ?? 'Our Timebank',
                    ]
                );
            }

            $notifModel->notify(
                $tenantId,
                $otherPartyId,
                'transaction',
                'Transaction recorded',
                $currentName . ' recorded a ' . Transaction::decimal($hours) . '-hour transaction: ' . truncate($data['description'], 80),
                '/transactions/' . $txId
            );
        }

        flash('success', 'Transaction recorded successfully.');
        $this->redirect('/transactions');
    }
    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function show(int $id): void
    {
        $this->requireAuth();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $txModel  = new Transaction($tenantId);
        $tx       = $txModel->getWithNames($id);

        if (!$tx) {
            flash('error', 'Transaction not found.');
            $this->redirect('/transactions');
        }

        $isInvolved = (int) $tx['provider_id'] === $userId || (int) $tx['receiver_id'] === $userId;
        if (!$isInvolved && !Auth::isAdmin()) {
            flash('error', 'You do not have permission to view that transaction.');
            $this->redirect('/transactions');
        }

        $this->view('transactions/show', ['transaction' => $tx]);
    }

    // -------------------------------------------------------------------------
    // Delete / Refund
    // -------------------------------------------------------------------------

    public function destroy(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $txModel  = new Transaction($tenantId);

        $success = $txModel->deleteAndRefund($id, $userId, Auth::isAdmin());

        if ($success) {
            flash('success', 'Transaction cancelled and balances reversed.');
        } else {
            flash('error', 'Could not cancel that transaction. You may not have permission, or it was already cancelled.');
        }

        $this->redirect('/transactions');
    }

    // -------------------------------------------------------------------------
    // Statement
    // -------------------------------------------------------------------------

    public function statement(): void
    {
        $this->requireAuth();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $page     = max(1, (int) $this->request->get('page', '1'));
        $from     = $this->request->get('from', '') ?: null;
        $to       = $this->request->get('to', '') ?: null;

        $memberModel = new Member($tenantId);
        $result      = $memberModel->getStatement($userId, $tenantId, $from, $to, $page, 25);
        $member      = $this->currentUser();

        $this->view('transactions/statement', [
            'transactions' => $result['data'],
            'pagination'   => $result,
            'from'         => $from,
            'to'           => $to,
            'member'       => $member,
        ]);
    }
}
