<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\Mailer;
use TimeBank\Models\Member;
use TimeBank\Models\Message;
use TimeBank\Models\Notification;

class MessageController extends BaseController
{
    // -------------------------------------------------------------------------
    // Inbox
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();
        $page     = max(1, (int) $this->request->get('page', '1'));

        $messageModel = new Message($tenantId);
        $result       = $messageModel->getInbox($userId, $tenantId, $page, 25);
        $unreadCount  = $messageModel->getUnreadCount($userId, $tenantId);

        // The view reads $messages['inbox'] and $messages['sent']; the original
        // passed a flat list, so the inbox always looked empty.
        $sent = $messageModel->getSent($userId, $tenantId, 1, 25);

        $this->view('messages/index', [
            'messages'    => ['inbox' => $result['data'], 'sent' => $sent['data']],
            'pagination'  => $result,
            'unreadCount' => $unreadCount,
        ]);
    }

    // -------------------------------------------------------------------------
    // Compose
    // -------------------------------------------------------------------------

    public function compose(): void
    {
        $this->requireAuth();

        $tenantId    = (int) $this->tenantId();
        $memberModel = new Member($tenantId);

        // The views link with ?to=ID; the original read ?member_id= only.
        $to            = (string) ($this->request->get('member_id', '') ?: $this->request->get('to', ''));
        $prefilledId   = $to !== '' ? (int) $to : null;
        $prefilledMember = null;

        if ($prefilledId !== null) {
            $prefilledMember = $memberModel->find($prefilledId);
        }

        $this->view('messages/compose', [
            'members'         => $memberModel->findActive($tenantId),
            'prefilledMember' => $prefilledMember,
            'currentUserId'   => (int) $this->currentUserId(),
        ]);
    }

    public function send(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int) $this->tenantId();
        $userId   = (int) $this->currentUserId();

        $data = [
            // Both forms (compose and reply) send a single recipient_id; the
            // original read only recipient_ids, so no message could be sent.
            'recipient_ids' => $this->request->post('recipient_ids', null) ?? ((string) $this->request->post('recipient_id', '') !== '' ? [(string) $this->request->post('recipient_id')] : []),
            'subject'       => trim($this->request->post('subject', '')),
            'body'          => trim($this->request->post('body', '')),
        ];

        // Normalise: might come in as a comma-separated string from some form setups
        if (is_string($data['recipient_ids'])) {
            $data['recipient_ids'] = array_filter(array_map('trim', explode(',', $data['recipient_ids'])));
        }

        $this->validate([
            'recipient_ids' => !empty($data['recipient_ids']) ? 'valid' : '',
            'subject'       => $data['subject'],
            'body'          => $data['body'],
        ], [
            'subject' => 'required|max:255',
            'body'    => 'required|max:5000',
        ], '/messages/compose');

        if (empty($data['recipient_ids'])) {
            $_SESSION['tm_errors'] = ['recipient_ids' => ['Please select at least one recipient.']];
            $_SESSION['tm_old']    = $data;
            flash('error', 'Please correct the errors below.');
            $this->redirect('/messages/compose');
        }

        $recipientIds = array_map('intval', (array) $data['recipient_ids']);
        // Remove the sender, duplicates, and anyone who is not an active member
        // of THIS community (the original delivered to any member id posted,
        // including members of other timebanks).
        $recipientIds = array_values(array_unique(array_filter(
            $recipientIds,
            fn($id) => $id !== $userId && $this->communityMember($id) !== null
        )));

        if (empty($recipientIds)) {
            flash('error', 'You cannot send a message only to yourself.');
            $this->redirect('/messages/compose');
        }

        $messageModel = new Message($tenantId);
        $messageId    = $messageModel->send($userId, $tenantId, $recipientIds, $data['subject'], $data['body']);

        $memberModel = new Member($tenantId);
        $sender      = $this->currentUser();
        $senderName  = ($sender['display_name'] ?? '') ?: trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));
        $notifModel  = new Notification($tenantId);

        foreach ($recipientIds as $recipientId) {
            $recipient = $memberModel->find($recipientId);
            if (!$recipient) {
                continue;
            }

            $prefs = $memberModel->getEmailPreferences($recipient);
            if ($prefs['new_message']) {
                Mailer::sendTemplate(
                    $tenantId,
                    'new_message',
                    $recipient['email'],
                    $recipient['first_name'] . ' ' . $recipient['last_name'],
                    [
                        'first_name'     => $recipient['first_name'],
                        'sender_name'    => $senderName,
                        'subject'        => $data['subject'],
                        'preview'        => truncate($data['body'], 200),
                        'message_url'    => url_abs('/messages/' . $messageId),
                        'community_name' => $this->tenant['name'] ?? 'Our Timebank',
                    ]
                );
            }

            $notifModel->notify(
                $tenantId,
                $recipientId,
                'message',
                'New message from ' . $senderName,
                $data['subject'],
                '/messages/' . $messageId
            );
        }

        flash('success', 'Message sent.');
        $this->redirect('/messages');
    }

    // -------------------------------------------------------------------------
    // Show Thread
    // -------------------------------------------------------------------------

    public function show(int $id): void
    {
        $this->requireAuth();

        $tenantId     = (int) $this->tenantId();
        $userId       = (int) $this->currentUserId();
        $messageModel = new Message($tenantId);

        $thread = $messageModel->getThread($id, $userId);

        if ($thread === false) {
            flash('error', 'Message not found or you do not have access to it.');
            $this->redirect('/messages');
        }

        // Mark as read for this recipient
        $messageModel->markRead($id, $userId);

        $this->view('messages/show', [
            'message'    => $thread['message'],
            'recipients' => $thread['recipients'],
        ]);
    }
}
