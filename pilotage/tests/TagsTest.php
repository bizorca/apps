<?php

declare(strict_types=1);

/**
 * Tags across object types.
 *
 * The load-bearing group is "One idea, one tag" — slugging is what stops a
 * firm accumulating three spellings of the same thing that no search can
 * reconcile, which is the failure the old comma-separated column had.
 */

use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;
use Bizorca\Pilotage\Repositories\EngagementRepository;
use Bizorca\Pilotage\Repositories\TaskRepository;
use Bizorca\Pilotage\Repositories\UserRepository;
use Bizorca\Pilotage\Services\Tags;

$db = Database::conn();
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['pl_taggings', 'pl_tags', 'pl_documents', 'pl_issues', 'pl_tasks',
          'pl_engagements', 'pl_client_orgs', 'pl_users', 'pl_tenants'] as $t) {
    $db->exec('TRUNCATE TABLE ' . $t);
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
$db->exec("INSERT INTO pl_tenants (id,slug,name,status) VALUES (1,'acme','Acme','active'),(2,'other','Other','active')");

$users = new UserRepository(1);
$coachId = $users->create(['email' => 'coach@acme.test', 'name' => 'A Coach', 'role' => 'coach', 'status' => 'active']);

$orgs = new ClientOrgRepository(1);
$alpha = $orgs->createOrg(['name' => 'Alpha Manufacturing', 'status' => 'active']);
$beta  = $orgs->createOrg(['name' => 'Beta Dental', 'status' => 'active']);

$engagements = new EngagementRepository(1);
$engA = $engagements->createEngagement(['client_org_id' => $alpha, 'title' => 'Alpha Q3', 'status' => 'active']);
$engB = $engagements->createEngagement(['client_org_id' => $beta, 'title' => 'Beta Q3', 'status' => 'active']);

$tasks = new TaskRepository(1);
$taskA = $tasks->createTask(['engagement_id' => $engA, 'title' => 'Prepare the bank pack']);
$taskB = $tasks->createTask(['engagement_id' => $engB, 'title' => 'Call the relationship manager']);


T::group('Slugging — one idea, one tag');

T::same('bank-financing', Tags::slug('Bank financing'), 'spaces become hyphens, case folds');
T::same('bank-financing', Tags::slug('  BANK   FINANCING  '), 'extra whitespace collapses');
T::same('bank-financing', Tags::slug('Bank-Financing'), 'existing hyphens survive');
T::same('cash-flow', Tags::slug('Cash flow'), 'two words');
T::same('cash-flow', Tags::slug('cash-flow'), 'already-hyphenated matches it');
T::same('cash-flow', Tags::slug('Cash / Flow!'), 'punctuation is not a distinguishing feature');
T::same('', Tags::slug('!!!'), 'punctuation alone is not a tag');
T::same('', Tags::slug('   '), 'nor is whitespace');

// The whole reason for slugging.
$t1 = Tags::ensure(1, 'Cash flow', $coachId);
$t2 = Tags::ensure(1, 'cashflow', $coachId);
$t3 = Tags::ensure(1, 'CASH FLOW', $coachId);
T::same($t1, $t3, '"Cash flow" and "CASH FLOW" are the same tag');
T::ok($t1 !== $t2, 'but "cashflow" as one word is genuinely different — we do not guess at intent');

T::throws(InvalidArgumentException::class, static fn () => Tags::ensure(1, '   '), 'an empty tag is refused');
T::throws(InvalidArgumentException::class, static fn () => Tags::ensure(1, '###'), 'so is one that reduces to nothing');


T::group('Attaching across object types — the point of the feature');

Tags::attach(1, 'task', $taskA, 'Bank financing', $coachId);
Tags::attach(1, 'task', $taskB, 'Bank financing', $coachId);

$db->exec("INSERT INTO pl_documents (tenant_id, context, engagement_id, client_org_id, title, status)
           VALUES (1, 'deliverable', {$engA}, {$alpha}, 'Loan covenant analysis', 'delivered')");
$docId = (int) $db->lastInsertId();
Tags::attach(1, 'document', $docId, 'bank financing', $coachId);

$db->exec("INSERT INTO pl_issues (tenant_id, engagement_id, title, origin)
           VALUES (1, {$engB}, 'Covenant breach risk', 'ad_hoc')");
$issueId = (int) $db->lastInsertId();
Tags::attach(1, 'issue', $issueId, 'BANK FINANCING', $coachId);

$tag = Tags::findBySlug(1, 'bank financing');
T::ok($tag !== null, 'the tag resolves however it was typed');

$objects = Tags::objectsFor(1, (int) $tag['id']);
T::same(4, count($objects), 'four things carry it — across two clients and three object types');

$types = array_unique(array_column($objects, 'object_type'));
sort($types);
T::same(['document', 'issue', 'task'], $types, 'a task, a document and an issue');

$orgNames = array_unique(array_column($objects, 'org_name'));
sort($orgNames);
T::same(['Alpha Manufacturing', 'Beta Dental'], $orgNames,
    'spanning both clients — this is the question the string column could never answer');

$titles = array_column($objects, 'title');
T::ok(in_array('Prepare the bank pack', $titles, true), 'results carry real titles, not bare ids');
T::ok(in_array('Loan covenant analysis', $titles, true), 'from every type');

T::throws(InvalidArgumentException::class,
    static fn () => Tags::attach(1, 'nonsense', 1, 'x'), 'an untaggable object type is refused');


T::group('Exact matching — what LIKE could not do');

Tags::attach(1, 'task', $taskA, 'bankruptcy', $coachId);
$bankruptcy = Tags::findBySlug(1, 'bankruptcy');

T::ok($bankruptcy !== null, 'bankruptcy is its own tag');
T::ok((int) $bankruptcy['id'] !== (int) $tag['id'], 'and is NOT bank financing');
T::same(1, count(Tags::objectsFor(1, (int) $bankruptcy['id'])), "searching it does not drag in 'bank financing'");


T::group('Idempotence and detaching');

$before = count(Tags::objectsFor(1, (int) $tag['id']));
Tags::attach(1, 'task', $taskA, 'Bank financing', $coachId);
T::same($before, count(Tags::objectsFor(1, (int) $tag['id'])), 'attaching the same tag twice changes nothing');

T::ok(Tags::detach(1, 'task', $taskA, (int) $tag['id']), 'a tag can be removed');
T::same($before - 1, count(Tags::objectsFor(1, (int) $tag['id'])), 'and it goes');
T::ok(!Tags::detach(1, 'task', $taskA, (int) $tag['id']), 'removing it twice does nothing');


T::group('Sync replaces wholesale — what a form submits');

Tags::sync(1, 'task', $taskA, 'Growth, Hiring , people ops', $coachId);
$names = array_column(Tags::forObject(1, 'task', $taskA), 'name');
sort($names);
T::same(['Growth', 'Hiring', 'people ops'], $names, 'three tags, whitespace trimmed');

Tags::sync(1, 'task', $taskA, 'Growth', $coachId);
T::same(['Growth'], array_column(Tags::forObject(1, 'task', $taskA), 'name'), 'sync removes what is no longer listed');

Tags::sync(1, 'task', $taskA, '', $coachId);
T::same([], Tags::forObject(1, 'task', $taskA), 'an empty string clears them');

Tags::sync(1, 'task', $taskA, 'Growth, , ,  ', $coachId);
T::same(1, count(Tags::forObject(1, 'task', $taskA)), 'empty pieces between commas are ignored');

T::same('Growth', Tags::stringFor(1, 'task', $taskA), 'and it round-trips back to a string for the edit field');


T::group('Enumeration — the other thing the string could not do');

$all = Tags::all(1);
T::ok(count($all) > 0, 'every tag in the firm can be listed');
T::ok(isset($all[0]['use_count']), 'with a usage count');
T::ok((int) $all[0]['use_count'] >= (int) end($all)['use_count'], 'most-used first, so a picker is useful');


T::group('Rename and merge — fixing a typo actually fixes it');

$typo = Tags::ensure(1, 'Recieveables', $coachId);
Tags::attach(1, 'task', $taskB, 'Recieveables', $coachId);

T::ok(Tags::rename(1, $typo, 'Receivables'), 'a tag can be renamed');
T::same(null, Tags::findBySlug(1, 'Recieveables'), 'the old spelling no longer resolves');
$fixed = Tags::findBySlug(1, 'Receivables');
T::ok($fixed !== null, 'the new one does');
T::same($typo, (int) $fixed['id'], 'and it is the same tag — everything tagged with it moved too');

T::throws(RuntimeException::class,
    static fn () => Tags::rename(1, $typo, 'Growth'),
    'renaming onto an existing tag is refused — that is a merge, and should be deliberate');

$dupe = Tags::ensure(1, 'Recruiting', $coachId);
Tags::attach(1, 'task', $taskA, 'Recruiting', $coachId);
$hiring = Tags::findBySlug(1, 'Hiring');

$moved = Tags::merge(1, $dupe, (int) $hiring['id']);
T::ok($moved >= 1, 'merging moves the objects across');
T::same(null, Tags::findBySlug(1, 'Recruiting'), 'and the folded tag is gone');
T::ok(count(Tags::objectsFor(1, (int) $hiring['id'])) >= 1, 'while the target keeps everything');

T::same(0, Tags::merge(1, (int) $hiring['id'], (int) $hiring['id']), 'merging a tag into itself is a no-op');


/**
 * The migrator that carried the old comma-separated `pl_documents.tags` column
 * into this model is gone, along with the column (migration 021). It had zero
 * rows to carry on every environment — development, test and production — so
 * the group that exercised it has gone too rather than being kept as a test of
 * a method that no longer exists.
 */


T::group('Tenant isolation');

T::same([], Tags::all(2), "tenant B sees none of tenant A's tags");
T::same(null, Tags::findBySlug(2, 'bank financing'), 'nor finds one by slug');
T::same([], Tags::objectsFor(2, (int) $tag['id']), "nor what carries it");
T::same([], Tags::forObject(2, 'task', $taskA), 'nor an object\'s tags');
T::ok(!Tags::detach(2, 'task', $taskB, (int) $tag['id']), 'nor detaches one');
T::ok(!Tags::delete(2, (int) $tag['id']), 'nor deletes one');

// A same-named tag in another tenant is a genuinely separate row.
$otherTag = Tags::ensure(2, 'Bank financing', null);
T::ok($otherTag !== (int) $tag['id'], 'the same name in another firm is a different tag entirely');


T::group('Slugging holds up on real-world names');

// Ampersands, digits, accents and symbols all appear in genuine tag names.
T::same('bank-debt', Tags::slug('Bank & debt'), 'an ampersand acts as a separator');
T::same('bank-debt', Tags::slug('Bank-debt'), 'and collapses with a hyphen — same idea, same tag');
T::ok(Tags::slug('Bank & debt') !== Tags::slug('Bank and debt'),
    'but "and" spelled out stays distinct — we separate on punctuation, we do not guess at words');

T::same('r-d-credits', Tags::slug('R&D credits'), 'R&D survives as something findable');
T::same('q4-2026', Tags::slug('Q4 2026'), 'digits are kept');
T::same('café', Tags::slug('café'), 'accented letters are kept — \p{L} not [a-z]');
T::same('50-margin', Tags::slug('50% margin'), 'a percent sign separates');

// Length cap: the column is VARCHAR(60), so the slug must never exceed it.
$long = str_repeat('verylongtagname ', 12);
T::ok(strlen(Tags::slug($long)) <= Tags::MAX_LENGTH, 'a very long name is truncated to fit the column');
T::ok(Tags::ensure(1, $long) > 0, 'and still stores without a driver error');
