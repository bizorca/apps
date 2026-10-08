<?php

declare(strict_types=1);

/**
 * M8: threads, mentions, and reply-by-email.
 *
 * The load-bearing groups are the internal-message wall and the inbound-reply
 * token — the second because trusting a From header would let anyone post as
 * anyone.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Comments;
use Bizorca\Pilotage\Services\Messaging;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_reply_tokens', 'pl_mentions', 'pl_thread_participants', 'pl_messages', 'pl_threads',
          'pl_announcements', 'pl_comments', 'pl_engagement_members', 'pl_engagements',
          'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");

$users = new UserRepository(1);
$coachId  = $users->create(['email' => 'coach@acme.test',  'name' => 'A Coach',     'role' => 'coach',     'status' => 'active']);
$assocId  = $users->create(['email' => 'sam@acme.test',    'name' => 'Sam Analyst', 'role' => 'associate', 'status' => 'active']);

$orgs = new ClientOrgRepository(1);
$orgId = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active', 'owner_user_id' => $coachId]);

$ownerId  = $users->create(['email' => 'dana@alpha.test', 'name' => 'Dana Reyes', 'role' => 'client_owner', 'client_org_id' => $orgId, 'status' => 'active']);

$engagements = new EngagementRepository(1);
$engId = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Q3 rhythm', 'status' => 'active', 'coach_user_id' => $coachId]);

$coach = ['id' => $coachId, 'name' => 'A Coach'];
$owner = ['id' => $ownerId, 'name' => 'Dana Reyes'];


T::group('Threads');

$threadId = Messaging::createThread(1, $engId, 'Lead time on the new line', 'Where did we land on the changeover?', $coach);
T::ok($threadId > 0, 'thread created');

$thread = Messaging::thread(1, $threadId);
T::same(1, (int) $thread['message_count'], 'the first message is counted');
T::ok($thread['last_message_at'] !== null, 'and stamped');

Messaging::post(1, $threadId, 'Four hours, down from nine.', $owner);
T::same(2, (int) Messaging::thread(1, $threadId)['message_count'], 'a reply increments the count');

T::throws(InvalidArgumentException::class,
    static fn () => Messaging::createThread(1, $engId, '', 'body', $coach), 'a thread needs a subject');
T::throws(InvalidArgumentException::class,
    static fn () => Messaging::createThread(1, $engId, 'Subject', '   ', $coach), 'and a first message');
T::throws(InvalidArgumentException::class,
    static fn () => Messaging::post(1, $threadId, '   ', $coach), 'an empty message is refused');


T::group('The internal wall');

Messaging::post(1, $threadId, 'Internal: Dana is dodging the covenant question.', $coach, false);

T::same(3, count(Messaging::messages(1, $threadId, false)), 'the coach sees all three');
T::same(2, count(Messaging::messages(1, $threadId, true)), 'the client sees two');

$clientJson = json_encode(Messaging::messages(1, $threadId, true));
T::ok(!str_contains($clientJson, 'dodging'), 'no internal text reaches the client');
T::ok(!str_contains($clientJson, 'covenant'), 'really, none of it');

// A thread with nothing but internal messages must not appear at all — the
// subject alone would leak that a conversation is happening.
$secret = Messaging::createThread(1, $engId, 'Renewal risk', 'Internal only.', $coach, false);
$coachThreads = array_column(Messaging::threads(1, $engId, false), 'subject');
$clientThreads = array_column(Messaging::threads(1, $engId, true), 'subject');

T::ok(in_array('Renewal risk', $coachThreads, true), 'the coach sees the internal thread');
T::ok(!in_array('Renewal risk', $clientThreads, true), 'the client does not see it at all — not even the subject');
T::ok(in_array('Lead time on the new line', $clientThreads, true), 'but does see the shared one');


T::group('Read state');

T::ok(Messaging::unreadCount(1, $ownerId, true) > 0, 'the client has unread messages');
Messaging::markRead(1, $threadId, $ownerId);
T::same(0, Messaging::unreadCount(1, $ownerId, true), 'reading clears them');

// Posted in the SAME SECOND as the read above. With a timestamp comparison
// this silently counted as already-read; keying on message id fixes it.
Messaging::post(1, $threadId, 'One more thing.', $coach);
T::same(1, Messaging::unreadCount(1, $ownerId, true), 'a message posted in the same second as a read is still unread');

// Self-authored messages never count. The coach has read nothing, so their
// count is exactly the messages OTHERS wrote.
Messaging::markRead(1, $threadId, $coachId);
T::same(0, Messaging::unreadCount(1, $coachId, false), 'after reading, nothing is unread');
Messaging::post(1, $threadId, 'A coach note to self.', $coach);
T::same(0, Messaging::unreadCount(1, $coachId, false), 'your own new message is never unread to you');
Messaging::post(1, $threadId, 'And a client reply.', $owner);
T::same(1, Messaging::unreadCount(1, $coachId, false), "but someone else's is");


T::group('Mentions');

$msgId = Messaging::post(1, $threadId, 'Can @sam pull the changeover numbers?', $coach);
$mentions = Messaging::unreadMentions(1, $assocId);
T::same(1, count($mentions), 'a mention by local part resolves');
T::same('message', $mentions[0]['object_type'], 'and is attached to the message');

Messaging::post(1, $threadId, 'Also @dana@alpha.test should see this.', $coach);
T::same(1, count(Messaging::unreadMentions(1, $ownerId)), 'a mention by full email resolves');

Messaging::post(1, $threadId, 'And @nobody-here is not a person.', $coach);
T::same(1, count(Messaging::unreadMentions(1, $ownerId)), 'an unknown handle mentions nobody');

// The rule that matters: an internal message cannot mention a client.
$before = count(Messaging::unreadMentions(1, $ownerId));
Messaging::post(1, $threadId, 'Internal: ask @dana@alpha.test quietly.', $coach, false);
T::same($before, count(Messaging::unreadMentions(1, $ownerId)),
    'an internal message cannot mention a client-side user into it');
T::ok(count(Messaging::unreadMentions(1, $assocId)) >= 1, 'but can still mention firm-side people');

T::same(1, Messaging::markMentionsRead(1, $ownerId), 'mentions can be marked read');
T::same([], Messaging::unreadMentions(1, $ownerId), 'and then they are gone');


T::group('Reply by email — the token is the identity');

$replyTo = Messaging::replyAddress(1, 'thread', $threadId, $ownerId);
T::ok(str_starts_with($replyTo, 'reply+'), 'the address carries a token');
T::ok(str_contains($replyTo, '@'), 'and is a real address');
T::ok(!str_contains(explode('@', $replyTo)[0], '.'), 'dots are escaped so the local part stays valid');

$countBefore = (int) Messaging::thread(1, $threadId)['message_count'];
$id = Messaging::acceptInboundReply($replyTo, "Sounds right to me.\n\nDana");
T::ok($id !== null, 'a valid reply posts');
T::same($countBefore + 1, (int) Messaging::thread(1, $threadId)['message_count'], 'and is counted');

$posted = $db->query('SELECT * FROM pl_messages WHERE id = ' . (int) $id)->fetch();
T::same('email', $posted['via'], 'it is marked as arriving by email');
T::same($ownerId, (int) $posted['author_id'], 'attributed to the token holder, NOT to a From header');
T::same(1, (int) $posted['client_visible'], 'an emailed reply is always client-visible — there is no checkbox in an inbox');

T::same(null, Messaging::acceptInboundReply('reply+garbage@pilotagehq.com', 'hi'), 'a malformed token is refused');
T::same(null, Messaging::acceptInboundReply('dana@alpha.test', 'hi'), 'a plain address with no token is refused');
T::same(null, Messaging::acceptInboundReply($replyTo, '   '), 'an empty reply is refused');

// Tampering.
$plain = str_replace('~', '.', explode('@', substr($replyTo, 6))[0]);
[$sel, $ver] = \Bizorca\Pilotage\Auth\Token::split($plain);
$tampered = 'reply+' . str_replace('.', '~', $sel . '.' . (($ver[0] === '0' ? '1' : '0') . substr($ver, 1))) . '@pilotagehq.com';
T::same(null, Messaging::acceptInboundReply($tampered, 'hi'), 'a tampered verifier is refused');

$expiring = Messaging::replyAddress(1, 'thread', $threadId, $ownerId);
$db->exec("UPDATE pl_reply_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY)");
T::same(null, Messaging::acceptInboundReply($expiring, 'late'), 'an expired token is refused');


T::group('Quoted-reply stripping');

T::same('Sounds right.', Messaging::stripQuotedReply("Sounds right.\n\n> On Tuesday you wrote:\n> the whole thread"), 'angle-quoted history removed');
T::same('Yes.', Messaging::stripQuotedReply("Yes.\n\nOn 5 August 2026, A Coach wrote:\nblah"), '"On ... wrote:" removed');
T::same('Agreed.', Messaging::stripQuotedReply("Agreed.\n--\nDana Reyes\nCFO"), 'signature removed');
T::same('Fine.', Messaging::stripQuotedReply("Fine.\n-----Original Message-----\nblah"), 'Outlook divider removed');
T::same('Just this.', Messaging::stripQuotedReply('Just this.'), 'a plain reply is untouched');


T::group('Reply-by-email also works on a comment thread');

$db->exec("INSERT INTO pl_tasks (tenant_id, engagement_id, title, owner_user_id) VALUES (1, {$engId}, 'Send the P&L', {$ownerId})");
$taskId = (int) $db->lastInsertId();

$taskReply = Messaging::replyAddress(1, 'task', $taskId, $ownerId);
$commentId = Messaging::acceptInboundReply($taskReply, 'Uploading it now.');

T::ok($commentId !== null, 'an emailed reply to a task becomes a comment');
T::same(1, count(Comments::forObject(1, 'task', $taskId, true)), 'and appears on the task');


T::group('Soft delete');

$doomed = Messaging::post(1, $threadId, 'Sent in error.', $coach);
T::ok(Messaging::deleteMessage(1, $doomed, $coachId), 'an author can delete their own message');
T::ok(!Messaging::deleteMessage(1, $doomed, $ownerId), 'nobody else can');

$still = $db->query('SELECT deleted_at FROM pl_messages WHERE id = ' . $doomed)->fetch();
T::ok($still !== false && $still['deleted_at'] !== null, 'the row survives — soft delete, not destruction');

$bodies = array_column(Messaging::messages(1, $threadId, false), 'body');
T::ok(!in_array('Sent in error.', $bodies, true), 'but it is out of the thread');


T::group('Tenant isolation');

T::same(null, Messaging::thread(2, $threadId), "tenant B cannot read tenant A's thread");
T::same([], Messaging::messages(2, $threadId, false), 'nor its messages');
T::same([], Messaging::threads(2, $engId, false), 'nor its thread list');
T::same([], Messaging::unreadMentions(2, $assocId), 'nor its mentions');
T::same(0, Messaging::unreadCount(2, $ownerId, false), 'nor its unread counts');
T::ok(!Messaging::deleteMessage(2, $msgId, $coachId), 'nor delete its messages');
T::same(0, Messaging::markMentionsRead(2, $assocId), 'nor clear its mentions');
