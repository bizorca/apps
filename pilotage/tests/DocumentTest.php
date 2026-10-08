<?php

declare(strict_types=1);

/**
 * M7: documents.
 *
 * This module stores clients' tax returns and bank statements, so the groups
 * that matter are the refusals: what the storage layer will not accept, what a
 * client cannot see, and what a share link stops doing.
 */

use Bizorca\Pilotage\Auth\Policy;
use Bizorca\Pilotage\Auth\Qualifiers;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\DocumentService;
use Bizorca\Pilotage\Services\Storage;

$db = Database::conn();

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_document_access_log', 'pl_share_link_views', 'pl_share_links',
          'pl_document_request_items', 'pl_document_requests', 'pl_document_deliveries',
          'pl_document_versions', 'pl_documents', 'pl_comments', 'pl_tasks', 'pl_issues',
          'pl_engagement_members', 'pl_engagements',
          'pl_users', 'pl_client_orgs', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme Advisory','active'),(2,'northstar','Northstar','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);

$orgs = new ClientOrgRepository(1);
$orgId = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active', 'owner_user_id' => $coachId]);
$ownerId = $users->create(['email' => 'owner@alpha.test', 'name' => 'An Owner', 'role' => 'client_owner', 'client_org_id' => $orgId, 'status' => 'active']);

$engagements = new EngagementRepository(1);
$engId = $engagements->createEngagement(['client_org_id' => $orgId, 'title' => 'Q3 rhythm', 'status' => 'active', 'coach_user_id' => $coachId]);

/** Build a $_FILES-shaped array from literal bytes. */
$fakeUpload = static function (string $name, string $bytes): array {
    $tmp = tempnam(sys_get_temp_dir(), 'ptgtest');
    file_put_contents($tmp, $bytes);
    return ['tmp_name' => $tmp, 'name' => $name, 'size' => strlen($bytes), 'error' => UPLOAD_ERR_OK];
};

$pdfBytes = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');


T::group('Storage refuses what it should');

T::throws(RuntimeException::class,
    static fn () => Storage::put(1, $fakeUpload('evil.html', '<script>alert(1)</script>')),
    'HTML is refused — served from our own origin it would be stored XSS');

T::throws(RuntimeException::class,
    static fn () => Storage::put(1, $fakeUpload('shell.php', '<?php system($_GET["c"]); ?>')),
    'PHP is refused');

// A .pdf extension does not make something a PDF. The sniff is on content.
T::throws(RuntimeException::class,
    static fn () => Storage::put(1, $fakeUpload('notreally.pdf', '<script>alert(1)</script>')),
    'a lying extension does not get past the content sniff');

T::throws(RuntimeException::class,
    static fn () => Storage::put(1, $fakeUpload('empty.pdf', '')),
    'an empty file is refused');

T::throws(RuntimeException::class,
    static fn () => Storage::put(1, ['tmp_name' => '', 'name' => 'x', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE]),
    'a missing upload is refused');

T::group('Storage keeps bytes out of the web root and off predictable paths');

$stored = Storage::put(1, $fakeUpload('Trailing 12 P&L.pdf', $pdfBytes));

T::same(64, strlen($stored['storage_key']), 'the on-disk name is a 64-char random key');
T::ok(ctype_xdigit($stored['storage_key']), 'and it is hex');
T::ok(!str_contains($stored['storage_key'], 'Trailing'), 'the user filename is NOT the on-disk name');
T::same('application/pdf', $stored['mime_type'], 'mime sniffed from content');
T::same(hash('sha256', $pdfBytes), $stored['sha256'], 'content hash recorded');

$path = Storage::pathFor(1, $stored['storage_key']);
T::ok(is_file($path), 'the file landed on disk');
T::ok(!str_contains($path, '/public'), 'the path is outside the web root');
T::ok(str_contains($path, '/files/1/'), 'and is namespaced by tenant');

// Path traversal via a crafted key.
foreach (['../../etc/passwd', '../' . str_repeat('a', 60), str_repeat('g', 64), 'short', ''] as $bad) {
    T::throws(InvalidArgumentException::class,
        static fn () => Storage::pathFor(1, $bad),
        'a malformed storage key is refused: ' . substr($bad, 0, 14));
}

T::group('Filenames are sanitised for the download header');

T::same('passwd', Storage::safeName('../../etc/passwd'), 'traversal stripped from display name');
T::same('report.pdf', Storage::safeName('report.pdf'), 'ordinary names survive');
T::same('badname.pdf', Storage::safeName("bad\"name.pdf"), 'quotes stripped so the header cannot be broken');
T::same('ab.pdf', Storage::safeName("a\r\nb.pdf"), 'CRLF stripped so headers cannot be injected');
T::same('download', Storage::safeName('   '), 'a blank name falls back');


T::group('Documents, versions, and the delivery lifecycle');

$docId = DocumentService::create(1, [
    'context'       => 'deliverable',
    'engagement_id' => $engId,
    'client_org_id' => $orgId,
    'title'         => 'Margin analysis',
], $fakeUpload('margin-v1.pdf', $pdfBytes), $coachId);

$doc = DocumentService::find(1, $docId);
T::same('draft', $doc['status'], 'a deliverable starts as a draft');
T::ok($doc['current_version_id'] !== null, 'it has a current version');

DocumentService::addVersion(1, $docId, $fakeUpload('margin-v2.pdf', $pdfBytes . 'v2'), $coachId);

T::same(2, count(DocumentService::versions(1, $docId, false)), 'the coach sees both versions');
T::same(1, count(DocumentService::versions(1, $docId, true)), 'the client sees only the current one');
T::same(2, (int) DocumentService::versions(1, $docId, false)[0]['version_number'], 'newest version first');

T::ok(DocumentService::deliver(1, $docId, $coachId, 'First cut for review.'), 'it delivers');
T::same('delivered', DocumentService::find(1, $docId)['status'], 'status is delivered');

T::ok(DocumentService::acknowledge(1, $docId, $ownerId, '203.0.113.9'), 'the client acknowledges');
T::same('acknowledged', DocumentService::find(1, $docId)['status'], 'status is acknowledged');

$delivery = $db->query('SELECT * FROM pl_document_deliveries ORDER BY id DESC LIMIT 1')->fetch();
T::ok($delivery['acknowledged_at'] !== null, 'acknowledgment is timestamped');
T::same($ownerId, (int) $delivery['acknowledged_by'], 'and attributed');
T::ok($delivery['acknowledged_ip'] !== null, 'and the address is logged — advisors get paid on delivery');

T::ok(!DocumentService::acknowledge(1, $docId, $ownerId), 'acknowledging twice does nothing');

// Revising something already delivered pulls it back to draft.
DocumentService::addVersion(1, $docId, $fakeUpload('margin-v3.pdf', $pdfBytes . 'v3'), $coachId);
T::same('draft', DocumentService::find(1, $docId)['status'], 'a new version un-delivers the document');
T::same(3, count(DocumentService::versions(1, $docId, false)), 'three versions now');

T::group('Context rules');

T::throws(InvalidArgumentException::class,
    static fn () => DocumentService::create(1, ['context' => 'library', 'engagement_id' => $engId], $fakeUpload('t.pdf', $pdfBytes)),
    'a library template cannot belong to an engagement');
T::throws(InvalidArgumentException::class,
    static fn () => DocumentService::create(1, ['context' => 'deliverable'], $fakeUpload('t.pdf', $pdfBytes)),
    'a deliverable must belong to an engagement');
T::throws(InvalidArgumentException::class,
    static fn () => DocumentService::create(1, ['context' => 'nonsense', 'engagement_id' => $engId], $fakeUpload('t.pdf', $pdfBytes)),
    'an unknown context is refused');

$clientFileId = DocumentService::create(1, [
    'context' => 'client_file', 'engagement_id' => $engId, 'client_org_id' => $orgId, 'title' => 'Trailing 12 P&L',
], $fakeUpload('p&l.pdf', $pdfBytes), $ownerId);
T::same('delivered', DocumentService::find(1, $clientFileId)['status'], "a client's own upload is not a draft awaiting review");

T::throws(RuntimeException::class,
    static fn () => DocumentService::deliver(1, $clientFileId, $coachId),
    'only a deliverable is delivered');

$libId = DocumentService::create(1, ['context' => 'library', 'title' => 'Engagement letter template'],
    $fakeUpload('letter.pdf', $pdfBytes), $coachId);
T::same(1, count(DocumentService::library(1)), 'the library holds it');

// Tags come from the real model (013), not the comma-separated column that was
// dropped in 021. The search joins them rather than LIKE-matching a string, so
// this exercises the path that actually exists.
\Bizorca\Pilotage\Services\Tags::attach(1, 'document', $libId, 'onboarding', $coachId);

T::same(1, count(DocumentService::library(1, 'onboarding')), 'and it is findable by tag');
T::same(1, count(DocumentService::library(1, 'onboard')),
    'a partial tag name still matches, which is what a search box should do');
T::same(0, count(DocumentService::library(1, 'legal')),
    'but a tag it does not carry finds nothing — the match is against tag NAMES now, not a blob of text');
T::same(1, count(DocumentService::library(1, 'Engagement letter')), 'titles still match');
T::same(1, count(DocumentService::library(1, 'Engagement')), 'and by title');
T::same(0, count(DocumentService::library(1, 'nonexistent')), 'and misses cleanly');


T::group('Client-side listing hides work in progress');

$coachSees = DocumentService::forEngagement(1, $engId, false);
$clientSees = DocumentService::forEngagement(1, $engId, true);

T::same(2, count($coachSees), 'the coach sees the draft deliverable and the client file');
T::same(1, count($clientSees), 'the client sees only what is delivered');
T::same('Trailing 12 P&L', $clientSees[0]['title'], 'namely their own upload — the deliverable went back to draft');

DocumentService::deliver(1, $docId, $coachId);
T::same(2, count(DocumentService::forEngagement(1, $engId, true)), 'redelivering makes it visible again');


T::group('Document requests');

$reqId = DocumentService::createRequest(1, $engId, 'Financial pack', [
    ['label' => 'Trailing 12 P&L'],
    ['label' => 'Current balance sheet'],
    ['label' => 'Cap table', 'required' => false],
], date('Y-m-d', strtotime('+14 days')), $coachId);

$requests = DocumentService::requests(1, $engId);
T::same(1, count($requests), 'the request exists');
T::same(3, $requests[0]['total_count'], 'with three items');
T::same(0, $requests[0]['done_count'], 'none fulfilled yet');

T::throws(InvalidArgumentException::class,
    static fn () => DocumentService::createRequest(1, $engId, '', [['label' => 'x']]),
    'a request needs a title');
T::throws(InvalidArgumentException::class,
    static fn () => DocumentService::createRequest(1, $engId, 'Empty', []),
    'a request needs at least one item');
T::throws(InvalidArgumentException::class,
    static fn () => DocumentService::createRequest(1, $engId, 'Blank items', [['label' => '   ']]),
    'blank items do not count');

$items = $requests[0]['items'];
T::ok(DocumentService::fulfilRequestItem(1, (int) $items[0]['id'], $clientFileId, $ownerId), 'an item is fulfilled');
T::ok(!DocumentService::fulfilRequestItem(1, (int) $items[0]['id'], $clientFileId, $ownerId), 'and cannot be fulfilled twice');

T::same('open', DocumentService::requests(1, $engId)[0]['status'], 'the request stays open while a required item is outstanding');

DocumentService::fulfilRequestItem(1, (int) $items[1]['id'], $clientFileId, $ownerId);
T::same('complete', DocumentService::requests(1, $engId)[0]['status'], 'it completes once every REQUIRED item is in');
T::same(2, DocumentService::requests(1, $engId)[0]['done_count'], 'the optional item is still outstanding and that is fine');


T::group('Share links expire, cap, and revoke');

$link = DocumentService::createShareLink(1, $docId, $coachId, 'Marie at the bank', 14, 2);
T::ok(str_contains($link['plaintext'], '.'), 'a selector/verifier token is issued');

$resolved = DocumentService::resolveShareLink($link['plaintext'], '203.0.113.5', 'curl/8');
T::ok($resolved !== null, 'a valid link resolves');
T::same($docId, (int) $resolved['document']['id'], 'to the right document');

$views = (int) $db->query('SELECT COUNT(*) c FROM pl_share_link_views')->fetch()['c'];
T::same(1, $views, 'the view is logged — handing a P&L to an outsider is worth a record');

T::ok(DocumentService::resolveShareLink($link['plaintext']) !== null, 'a second view is allowed under the cap of two');
T::same(null, DocumentService::resolveShareLink($link['plaintext']), 'the third exceeds the cap and is refused');

$link2 = DocumentService::createShareLink(1, $docId, $coachId, 'Attorney', 14);
T::ok(DocumentService::revokeShareLink(1, (int) $link2['id']), 'a link can be revoked');
T::same(null, DocumentService::resolveShareLink($link2['plaintext']), 'and stops working immediately');

$link3 = DocumentService::createShareLink(1, $docId, $coachId, 'Expired', 14);
$db->exec('UPDATE pl_share_links SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ' . (int) $link3['id']);
T::same(null, DocumentService::resolveShareLink($link3['plaintext']), 'an expired link is refused');

[$sel, $ver] = \Bizorca\Pilotage\Auth\Token::split(DocumentService::createShareLink(1, $docId, $coachId)['plaintext']);
$tampered = $sel . '.' . (($ver[0] === '0' ? '1' : '0') . substr($ver, 1));
T::same(null, DocumentService::resolveShareLink($tampered), 'a tampered token is refused');
T::same(null, DocumentService::resolveShareLink('garbage'), 'a malformed token is refused');

$capped = DocumentService::createShareLink(1, $docId, $coachId, 'Forever?', 3650);
$days = (strtotime((string) $capped['expires_at']) - time()) / 86400;
T::ok($days <= 91, 'link lifetime is hard-capped at 90 days — a link that never dies is not a share');


T::group('Tenant isolation');

T::same(null, DocumentService::find(2, $docId), "tenant B cannot read tenant A's document");
T::same([], DocumentService::versions(2, $docId, false), 'nor its versions');
T::same([], DocumentService::forEngagement(2, $engId, false), 'nor its engagement documents');
T::same([], DocumentService::library(2), 'nor its library');
T::same([], DocumentService::requests(2, $engId), 'nor its requests');
T::same([], DocumentService::shareLinks(2, $docId), 'nor its share links');
T::ok(!DocumentService::deliver(2, $docId, $coachId), 'nor deliver it');
T::ok(!DocumentService::acknowledge(2, $docId, $ownerId), 'nor acknowledge it');
T::ok(!DocumentService::revokeShareLink(2, (int) $link['id']), 'nor revoke its links');
T::throws(RuntimeException::class,
    static fn () => DocumentService::createShareLink(2, $docId, $coachId),
    "nor mint a share link for tenant A's document");
T::same(0, Storage::tenantUsage(2), "and tenant B's storage usage is its own");
T::ok(Storage::tenantUsage(1) > 0, "while tenant A's is not");


T::group('Qualifiers — M7 pays what it owes');

Policy::resetQualifiers();
Qualifiers::register();

$remaining = Policy::unresolvedQualifiers();
T::ok(!in_array('delivered', $remaining, true), 'delivered is now resolved');
T::ok(!in_array('shared', $remaining, true), 'shared is now resolved');

$owner   = ['id' => $ownerId, 'role' => 'client_owner',  'status' => 'active', 'client_org_id' => $orgId];
$member  = ['id' => 77,       'role' => 'client_member', 'status' => 'active', 'client_org_id' => $orgId];
$sponsor = ['id' => 78,       'role' => 'sponsor',       'status' => 'active', 'client_org_id' => $orgId];

T::ok(Policy::can($owner, Policy::READ, 'document_deliverable', ['delivered' => true]), 'a client owner reads a delivered document');
T::ok(!Policy::can($owner, Policy::READ, 'document_deliverable', ['delivered' => false]), 'but not a draft');
T::ok(!Policy::can($owner, Policy::READ, 'document_deliverable', []), 'and a missing flag denies');
T::ok(!Policy::can($owner, Policy::READ, 'document_deliverable', null), 'as does no context at all');

T::ok(Policy::can($member, Policy::READ, 'document_deliverable', ['shared' => true]), 'a team member reads a shared document');
T::ok(!Policy::can($member, Policy::READ, 'document_deliverable', ['shared' => false]), 'but not an unshared one');
T::ok(Policy::can($sponsor, Policy::READ, 'document_deliverable', ['shared' => true]), 'a sponsor reads a shared document');
T::ok(!Policy::can($sponsor, Policy::READ, 'document_client_file', ['shared' => true]), "but never the client's own uploads");

Policy::resetQualifiers();


// Clean the files this suite wrote.
foreach ($db->query('SELECT tenant_id, storage_key FROM pl_document_versions')->fetchAll() as $v) {
    Storage::delete((int) $v['tenant_id'], (string) $v['storage_key']);
}
