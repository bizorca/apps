<?php
require __DIR__ . '/_bootstrap.php';
require_once CW_ROOT . '/includes/structures.php';
require_once CW_ROOT . '/includes/documents.php';
requireLogin();

$user = getCurrentUser();
if (!in_array($user['role'], ['client'], true)) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$case = getUserCase((int)$user['id']);
if (!$case) {
    header('Location: ' . url('/intake.php'));
    exit;
}

$db = getDB();

// Load business record
$stmt = $db->prepare('SELECT * FROM cw_businesses WHERE id = ? LIMIT 1');
$stmt->execute([$case['business_id']]);
$business = $stmt->fetch() ?: [];

// Load most recent assessment
$stmt = $db->prepare('SELECT * FROM cw_structure_assessments WHERE case_id = ? ORDER BY completed_at DESC LIMIT 1');
$stmt->execute([$case['id']]);
$assessment = $stmt->fetch() ?: null;

if (!$assessment) {
    setFlash('error', 'Please complete the structure assessment first.');
    header('Location: ' . url('/structure-selector.php'));
    exit;
}

$structures    = STRUCTURES;
$recommendedKey = $assessment['recommended_structure'];
$recommended   = $structures[$recommendedKey] ?? null;

// Available docs for this structure
$availableDocs = getAvailableDocuments($recommendedKey);

// Already-generated docs for this case
$stmt = $db->prepare('SELECT * FROM cw_documents WHERE case_id = ? ORDER BY created_at DESC');
$stmt->execute([$case['id']]);
$generatedDocs = $stmt->fetchAll();
$generatedByType = [];
foreach ($generatedDocs as $doc) {
    $generatedByType[$doc['doc_type']] = $doc;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $docType = trim($_POST['doc_type'] ?? '');

        // $availableDocs is key => label. The original checked (and the list below
        // posted) labels, so renderDocument() never matched and every document was
        // "Document type not found."
        if (!array_key_exists($docType, $availableDocs)) {
            $errors[] = 'Invalid document type requested.';
        } else {
            $rendered = renderDocument($docType, $business, $assessment);
            $now      = date('Y-m-d H:i:s');

            // Upsert
            if (isset($generatedByType[$docType])) {
                $stmt = $db->prepare(
                    'UPDATE cw_documents SET rendered_html=?, created_at=? WHERE id=?'
                );
                $stmt->execute([$rendered, $now, $generatedByType[$docType]['id']]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO cw_documents (case_id, doc_type, structure, rendered_html, created_at)
                     VALUES (?,?,?,?,?)'
                );
                $stmt->execute([$case['id'], $docType, $recommendedKey, $rendered, $now]);
            }

            // Advance status
            if ($case['status'] === 'documents') {
                $stmt = $db->prepare('UPDATE cw_cases SET status=\'review\', updated_at=? WHERE id=?');
                $stmt->execute([$now, $case['id']]);
            }

            setFlash('success', 'Document generated successfully.');
            header('Location: ' . url('/documents.php'));
            exit;
        }
    }
}

$flash = getFlash();

// Reload generated docs after potential POST
$stmt = $db->prepare('SELECT * FROM cw_documents WHERE case_id = ? ORDER BY created_at DESC');
$stmt->execute([$case['id']]);
$generatedDocs = $stmt->fetchAll();
$generatedByType = [];
foreach ($generatedDocs as $doc) {
    $generatedByType[$doc['doc_type']] = $doc;
}

// Human-readable doc type labels
function docTypeLabel(string $type): string {
    $labels = [
        'articles_of_incorporation' => 'Articles of Incorporation',
        'bylaws'                    => 'Cooperative Bylaws',
        'operating_agreement'       => 'Operating Agreement',
        'membership_agreement'      => 'Membership Agreement',
        'purchase_agreement'        => 'Business Purchase Agreement',
        'seller_note'               => 'Seller Promissory Note',
        'membership_certificate'    => 'Membership Certificate Template',
        'patronage_policy'          => 'Patronage Dividend Policy',
    ];
    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents — CoopConvert</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-emerald-700 text-white px-6 py-4 flex items-center justify-between">
    <a href="<?= url('/dashboard.php') ?>" class="font-bold text-lg tracking-tight">CoopConvert</a>
    <div class="flex items-center gap-6 text-sm">
        <a href="<?= url('/dashboard.php') ?>" class="hover:text-teal-300">Dashboard</a>
        <span class="text-emerald-300"><?= h($user['name']) ?></span>
        <a href="<?= url('/logout.php') ?>" class="hover:text-teal-300">Sign out</a>
    </div>
</nav>

<div class="max-w-3xl mx-auto px-4 py-10">

    <?php if ($flash): ?>
    <div class="mb-6 px-4 py-3 rounded-md text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-md px-4 py-3">
        <ul class="list-disc list-inside text-red-600 text-sm space-y-0.5">
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Documents</h1>
        <p class="text-gray-500 text-sm">Generate formation and conversion documents for <strong><?= $recommended ? h($recommended['name']) : h($recommendedKey) ?></strong>.</p>
    </div>

    <!-- Disclaimer -->
    <div class="bg-amber-50 border border-amber-300 rounded-xl p-5 mb-8">
        <p class="font-semibold text-amber-800 mb-1 text-sm">Educational use only — not a substitute for legal counsel</p>
        <p class="text-amber-700 text-sm leading-relaxed">These templates are provided for educational purposes. They are not a substitute for advice from a Washington-licensed cooperative attorney. CoopConvert strongly recommends having any documents reviewed by a qualified attorney before filing or executing any agreements.</p>
    </div>

    <!-- Available documents -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-4">Available documents</h2>

        <?php if (empty($availableDocs)): ?>
        <p class="text-gray-500 text-sm">No documents are configured for this structure yet. Contact your coordinator.</p>
        <?php else: ?>
        <ul class="divide-y divide-gray-50">
            <?php foreach ($availableDocs as $docType => $docLabel):
                $alreadyGenerated = isset($generatedByType[$docType]);
            ?>
            <li class="py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800"><?= h(docTypeLabel($docType)) ?></p>
                    <?php if ($alreadyGenerated): ?>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Generated <?= h(date('M j, Y g:ia', strtotime($generatedByType[$docType]['created_at']))) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <?php if ($alreadyGenerated): ?>
                    <a href="<?= url('/doc-view.php?id=' . (int)$generatedByType[$docType]['id']) ?>"
                       target="_blank"
                       class="text-xs text-emerald-600 hover:underline font-medium">View</a>
                    <?php endif; ?>
                    <form method="POST" action="<?= url('/documents.php') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="doc_type" value="<?= h($docType) ?>">
                        <button type="submit"
                                class="<?= $alreadyGenerated ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-emerald-700 text-white hover:bg-emerald-600' ?> text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">
                            <?= $alreadyGenerated ? 'Regenerate' : 'Generate' ?>
                        </button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Generated document links -->
    <?php if ($generatedDocs): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-800 mb-4">Your generated documents</h2>
        <ul class="space-y-2">
            <?php foreach ($generatedDocs as $doc): ?>
            <li class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50 last:border-0">
                <span class="font-medium text-gray-700"><?= h(docTypeLabel($doc['doc_type'])) ?></span>
                <a href="<?= url('/doc-view.php?id=' . (int)$doc['id']) ?>"
                   target="_blank"
                   class="text-emerald-600 hover:underline text-xs font-medium">Open &rarr;</a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="mt-8 flex justify-end">
        <a href="<?= url('/case.php') ?>"
           class="text-sm text-emerald-600 hover:underline">View my case &rarr;</a>
    </div>

</div>
</body>
</html>
