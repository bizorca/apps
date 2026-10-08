<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\DB;
use TimeBank\Models\Endorsement;
use TimeBank\Models\Member;

class EndorsementController extends BaseController
{
    // -------------------------------------------------------------------------
    // Create Form
    // -------------------------------------------------------------------------

    public function create(int $memberId): void
    {
        $this->requireAuth();

        $tenantId  = (int) $this->tenantId();
        $userId    = (int) $this->currentUserId();

        if ($memberId === $userId) {
            flash('error', 'You cannot endorse yourself.');
            $this->redirect('/members/' . $memberId);
        }

        $memberModel = new Member($tenantId);
        $toMember    = $memberModel->find($memberId);

        if (!$toMember || (int) $toMember['tenant_id'] !== $tenantId) {
            flash('error', 'Member not found.');
            $this->redirect('/members');
        }

        // Recent shared (confirmed) transactions between these two members
        $sharedTransactions = DB::fetchAll(
            "SELECT t.id, t.description, t.service_date, t.hours
             FROM `tm_transactions` t
             WHERE t.tenant_id = ?
               AND t.status = 'confirmed'
               AND (
                   (t.provider_id = ? AND t.receiver_id = ?)
                   OR
                   (t.provider_id = ? AND t.receiver_id = ?)
               )
             ORDER BY t.service_date DESC
             LIMIT 20",
            [$tenantId, $userId, $memberId, $memberId, $userId]
        );

        $this->view('endorsements/create', [
            'toMember'           => $toMember,
            'member'             => $toMember,   // the view reads $member; the original passed only toMember
            'sharedTransactions' => $sharedTransactions,
            'transactions'       => $sharedTransactions,   // the view reads $transactions
        ]);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        $data = [
            'to_member_id'   => $this->request->post('to_member_id', ''),
            'transaction_id' => $this->request->post('transaction_id', ''),
            'rating'         => $this->request->post('rating', ''),
            'comment'        => trim($this->request->post('comment', '')),
        ];

        $this->validate($data, [
            'to_member_id' => 'required',
            'rating'       => 'required|integer',
            'comment'      => 'required|max:500',
        ], '/members/' . ($data['to_member_id'] ?: ''));

        $toMemberId    = (int) $data['to_member_id'];
        $transactionId = $data['transaction_id'] !== '' ? (int) $data['transaction_id'] : null;
        $rating        = (int) $data['rating'];

        if ($toMemberId === $userId) {
            flash('error', 'You cannot endorse yourself.');
            $this->redirect('/members/' . $toMemberId);
        }

        // Endorse only a member of this community, and only for a transaction
        // the two of you actually shared (the original checked neither).
        if (!$this->communityMember($toMemberId)) {
            flash('error', 'Member not found.');
            $this->redirect('/members');
        }
        if ($transactionId !== null) {
            $shared = DB::fetch(
                "SELECT id FROM `tm_transactions`
                 WHERE id = ? AND tenant_id = ? AND status = 'confirmed'
                   AND ((provider_id = ? AND receiver_id = ?) OR (provider_id = ? AND receiver_id = ?))",
                [$transactionId, $tenantId, $userId, $toMemberId, $toMemberId, $userId]
            );
            if (!$shared) {
                $transactionId = null;
            }
        }

        if ($rating < 1 || $rating > 5) {
            flash('error', 'Rating must be between 1 and 5.');
            $this->redirect('/members/' . $toMemberId);
        }

        $endorsementModel = new Endorsement($tenantId);

        if ($transactionId !== null && $endorsementModel->alreadyEndorsed($userId, $toMemberId, $transactionId)) {
            flash('error', 'You have already left an endorsement for that transaction.');
            $this->redirect('/members/' . $toMemberId);
        }

        $endorsementModel->create([
            'from_member_id' => $userId,
            'to_member_id'   => $toMemberId,
            'transaction_id' => $transactionId,
            'rating'         => $rating,
            'comment'        => $data['comment'],
        ]);

        flash('success', 'Endorsement submitted. Thank you!');
        $this->redirect('/members/' . $toMemberId);
    }
}
