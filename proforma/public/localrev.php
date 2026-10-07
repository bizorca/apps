<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once PF_ROOT . '/includes/config.php';
require_once PF_ROOT . '/includes/helpers.php';
require_once PF_ROOT . '/includes/auth.php';
require_once PF_ROOT . '/includes/db.php';
require_once PF_ROOT . '/includes/email.php';
require_once PF_ROOT . '/includes/localrev/CensusClient.php';
require_once PF_ROOT . '/includes/localrev/PlacesClient.php';
require_once PF_ROOT . '/includes/localrev/RecommendationEngine.php';

startSession();
requireAuth();

$user   = currentUser();
$userId = (int)$user['id'];

if (empty($_SESSION['pf_business_id'])) redirect('/dashboard.php');
$bid = (int)$_SESSION['pf_business_id'];
$biz = getBusinessById($bid, $userId);
if (!$biz) redirect('/dashboard.php');

// ---------------------------------------------------------------------------
// POST handlers
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = post('action', '');

    // --- Save market profile ---
    if ($action === 'save_profile') {
        $zip   = trim(post('zip_code', ''));
        $state = trim(post('state_code', ''));

        $profileData = [
            'town_name'  => trim(post('town_name',  '')),
            'state_code' => $state,
            'zip_code'   => $zip,
            'population' => max(0, (int)postInt('population', 0)),
        ];

        // Auto-fetch Census demographic data if ZIP provided
        if (preg_match('/^\d{5}$/', $zip)) {
            $censusData = fetchCensusData($zip);
            if ($censusData) {
                if (!empty($censusData['population']) && $profileData['population'] === 0) {
                    $profileData['population'] = $censusData['population'];
                }
                $profileData['median_income']     = $censusData['median_income'] ?? null;
                $profileData['census_fetched_at'] = date('Y-m-d H:i:s');
            }

            // Geocode ZIP to lat/lng (Google if key set, else Census/Nominatim)
            $coords = null;
            if (GOOGLE_PLACES_API_KEY !== '') {
                $address = $profileData['town_name'] . ', ' . $state . ' ' . $zip;
                $coords  = googleGeocode(trim($address, ', '));
            }
            if (!$coords) {
                $coords = geocodeZip($zip, $state);
            }
            if ($coords) {
                $profileData['lat'] = $coords['lat'];
                $profileData['lng'] = $coords['lng'];
            }
        }

        saveMarketProfile($bid, $profileData);

        // Re-load profile (with enriched fields), generate recommendations
        $profile = getMarketProfile($bid);
        $orgs    = getLocalOrgs($bid);
        if ($profile) {
            $recs = generateRecommendations($bid, $profile, $orgs, $biz);
            if (!empty($recs)) {
                sendLocalrevNotification($user['email'], $biz['business_name'], $recs);
            }
        }

        flashSuccess('Market profile saved' . (!empty($censusData) ? ' — Census data pulled automatically.' : '.'));
        redirect('/localrev.php');
    }

    // --- Add local organization ---
    if ($action === 'add_org') {
        $name = trim(post('org_name', ''));
        if ($name !== '') {
            $maxOrder = getDb()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM pf_local_organizations WHERE business_id = ?');
            $maxOrder->execute([$bid]);
            $nextOrder = (int)$maxOrder->fetchColumn() + 1;

            saveLocalOrg($bid, [
                'org_name'       => $name,
                'org_type'       => post('org_type', 'other'),
                'employee_count' => post('employee_count', ''),
                'notes'          => post('org_notes', ''),
                'sort_order'     => $nextOrder,
            ]);

            // Regenerate recommendations with updated org list
            $profile = getMarketProfile($bid);
            $orgs    = getLocalOrgs($bid);
            if ($profile) {
                generateRecommendations($bid, $profile, $orgs, $biz);
            }

            flashSuccess('Organization added.');
        }
        redirect('/localrev.php');
    }

    // --- Delete local organization ---
    if ($action === 'delete_org' && !empty($_POST['org_id'])) {
        deleteLocalOrg((int)$_POST['org_id'], $bid);
        // Regenerate after removal
        $profile = getMarketProfile($bid);
        $orgs    = getLocalOrgs($bid);
        if ($profile) {
            generateRecommendations($bid, $profile, $orgs, $biz);
        }
        flashSuccess('Organization removed.');
        redirect('/localrev.php');
    }

    // --- Save recommendation feedback ---
    if ($action === 'feedback' && !empty($_POST['rec_id'])) {
        $recId = (int)$_POST['rec_id'];
        saveRecommendationFeedback($recId, $bid, [
            'user_id'        => $userId,
            'status'         => post('status', 'considering'),
            'monthly_revenue'=> post('monthly_revenue', ''),
            'notes'          => post('feedback_notes', ''),
        ]);
        flashSuccess('Feedback saved.');
        redirect('/localrev.php');
    }

    // --- Dismiss recommendation ---
    if ($action === 'dismiss' && !empty($_POST['rec_id'])) {
        dismissRecommendation((int)$_POST['rec_id'], $bid);
        redirect('/localrev.php');
    }

    // --- Undismiss recommendation ---
    if ($action === 'undismiss' && !empty($_POST['rec_id'])) {
        undismissRecommendation((int)$_POST['rec_id'], $bid);
        redirect('/localrev.php');
    }

    // --- Scan nearby via Overpass (OpenStreetMap) ---
    if ($action === 'scan_places') {
        $profile = getMarketProfile($bid);
        if ($profile && !empty($profile['lat']) && !empty($profile['lng'])) {
            $candidates = fetchNearbyOrgs((float)$profile['lat'], (float)$profile['lng']);
            if ($candidates !== null) {
                $existingIds = array_column(getLocalOrgs($bid), 'place_id');
                $maxOrderStmt = getDb()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM pf_local_organizations WHERE business_id = ?');
                $maxOrderStmt->execute([$bid]);
                $nextOrder = (int)$maxOrderStmt->fetchColumn() + 1;
                $added = 0;
                foreach ($candidates as $c) {
                    if (in_array($c['place_id'], $existingIds, true)) continue;
                    saveLocalOrg($bid, [
                        'org_name'    => $c['name'],
                        'org_type'    => $c['org_type'],
                        'place_id'    => $c['place_id'],
                        'source'      => 'osm',
                        'notes'       => $c['vicinity'] ?? '',
                        'sort_order'  => $nextOrder,
                    ]);
                    $nextOrder++;
                    $added++;
                }
                saveMarketProfile($bid, array_merge((array)$profile, ['places_fetched_at' => date('Y-m-d H:i:s')]));
                $orgs    = getLocalOrgs($bid);
                $newRecs = generateRecommendations($bid, $profile, $orgs, $biz);
                flashSuccess("Scan complete — {$added} organizations added, recommendations updated.");
            } else {
                flashError('OpenStreetMap scan failed — check your connection or try again in a moment.');
            }
        } else {
            flashError('Save your ZIP code first so we can geocode your location before scanning.');
        }
        redirect('/localrev.php');
    }
}

// ---------------------------------------------------------------------------
// Load page data
// ---------------------------------------------------------------------------
$profile = getMarketProfile($bid);
$orgs    = getLocalOrgs($bid);
$recs      = getRecommendations($bid, false); // active (non-dismissed)
$dismissed = getDismissedRecommendations($bid);

$hasLatLng = $profile && !empty($profile['lat']) && !empty($profile['lng']);
$orgTypeLabels = [
    'school'       => 'School / District',
    'corporate'    => 'Corporate Employer',
    'medical'      => 'Medical / PT Clinic',
    'senior_living'=> 'Senior Living',
    'church'       => 'Church / Community',
    'other'        => 'Other',
];
$categoryColors = [
    'b2b'         => 'bg-indigo-50 text-indigo-700 border-indigo-100',
    'community'   => 'bg-green-50 text-green-700 border-green-100',
    'pricing'     => 'bg-amber-50 text-amber-700 border-amber-100',
    'partnership' => 'bg-purple-50 text-purple-700 border-purple-100',
];

$pageTitle = 'Market Intel — ProForma';
include PF_ROOT . '/templates/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Local Revenue Intelligence</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= h($biz['business_name']) ?> &mdash; <?= h(BUSINESS_TYPES[$biz['business_type']]['label'] ?? $biz['business_type']) ?></p>
    </div>
    <?php if (!empty($recs)): ?>
    <span class="text-sm bg-green-50 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg font-medium">
        <?= count($recs) ?> <?= count($recs) === 1 ? 'opportunity' : 'opportunities' ?> identified
    </span>
    <?php endif; ?>
</div>

<!-- ============================================================
     SECTION 1: MARKET PROFILE
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">Market Profile</h2>
            <p class="text-xs text-gray-500 mt-0.5">Your geographic footprint. Census population and income data are pulled automatically from the ZIP code.</p>
        </div>
        <?php if ($hasLatLng): ?>
        <span class="text-xs text-green-600 bg-green-50 border border-green-100 px-2 py-1 rounded">Geocoded</span>
        <?php endif; ?>
    </div>

    <form method="post" class="space-y-4">
        <?= csrf() ?>
        <input type="hidden" name="action" value="save_profile">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Town / City name</label>
                <input type="text" name="town_name" value="<?= h($profile['town_name'] ?? '') ?>" placeholder="e.g. Bozeman"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">State</label>
                <select name="state_code" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">— State —</option>
                    <?php
                    $states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
                    $selectedState = $profile['state_code'] ?? '';
                    foreach ($states as $s): ?>
                    <option value="<?= $s ?>" <?= $s === $selectedState ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">ZIP code</label>
                <input type="text" name="zip_code" value="<?= h($profile['zip_code'] ?? '') ?>" placeholder="59715"
                       maxlength="5" pattern="\d{5}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Population <span class="font-normal text-gray-400">(auto from ZIP)</span></label>
                <input type="number" name="population" value="<?= (int)($profile['population'] ?? 0) ?>" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
        </div>

        <?php if ($profile && !empty($profile['median_income'])): ?>
        <div class="flex items-center gap-4 text-xs text-gray-500 mt-1">
            <span>Census median household income: <strong class="text-gray-700"><?= '$' . number_format((float)$profile['median_income']) ?></strong></span>
            <?php if (!empty($profile['census_fetched_at'])): ?>
            <span class="text-gray-400">Pulled <?= date('M j, Y', strtotime($profile['census_fetched_at'])) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <button type="submit"
                class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
            Save &amp; generate recommendations
        </button>
    </form>
    <?php if ($profile && $hasLatLng): ?>
    <form method="post" class="mt-3">
        <?= csrf() ?>
        <input type="hidden" name="action" value="scan_places">
        <button type="submit"
                class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 transition-colors">
            Scan nearby organizations
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- ============================================================
     SECTION 2: LOCAL ORGANIZATIONS
============================================================ -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">Nearby Organizations</h2>
            <p class="text-xs text-gray-500 mt-0.5">Schools, employers, medical facilities, and community anchors near your business. These inform which opportunities get recommended.</p>
        </div>
        <span class="text-xs text-gray-400"><?= count($orgs) ?> added</span>
    </div>

    <?php if (!empty($orgs)): ?>
    <div class="mb-4 divide-y divide-gray-100">
        <?php foreach ($orgs as $org): ?>
        <div class="py-2.5 flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <span class="font-medium text-sm text-gray-800"><?= h($org['org_name']) ?></span>
                <span class="ml-2 text-xs text-gray-400"><?= h($orgTypeLabels[$org['org_type']] ?? $org['org_type']) ?></span>
                <?php if (!empty($org['employee_count'])): ?>
                <span class="ml-1 text-xs text-gray-400">&middot; ~<?= number_format((int)$org['employee_count']) ?> employees</span>
                <?php endif; ?>
                <?php if (!empty($org['notes'])): ?>
                <span class="ml-1 text-xs text-gray-400">&middot; <?= h($org['notes']) ?></span>
                <?php endif; ?>
                <?php if (($org['source'] ?? 'manual') === 'osm'): ?>
                <span class="ml-1 text-xs text-blue-400">via OSM</span>
                <?php endif; ?>
            </div>
            <form method="post" class="inline flex-shrink-0">
                <?= csrf() ?>
                <input type="hidden" name="action" value="delete_org">
                <input type="hidden" name="org_id" value="<?= (int)$org['id'] ?>">
                <button type="submit" class="text-xs text-red-400 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50">Remove</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <details class="mt-2" <?= empty($orgs) ? 'open' : '' ?>>
        <summary class="text-sm text-indigo-600 cursor-pointer select-none hover:text-indigo-800">+ Add organization</summary>
        <form method="post" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <?= csrf() ?>
            <input type="hidden" name="action" value="add_org">
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Organization name</label>
                <input type="text" name="org_name" placeholder="e.g. Bozeman School District 7"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                <select name="org_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <?php foreach ($orgTypeLabels as $val => $label): ?>
                    <option value="<?= $val ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Employees <span class="font-normal text-gray-400">(optional)</span></label>
                <input type="number" name="employee_count" min="1" placeholder="e.g. 250"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                <input type="text" name="org_notes" placeholder="e.g. nearest school is 0.8 miles away"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="col-span-2 sm:col-span-4 flex justify-end">
                <button type="submit"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
                    Add organization
                </button>
            </div>
        </form>
    </details>
</div>

<!-- ============================================================
     SECTION 3: RECOMMENDATIONS
============================================================ -->
<?php if (!$profile): ?>
<div class="bg-white border border-dashed border-gray-300 rounded-xl p-12 text-center mb-6">
    <p class="text-gray-500 text-sm mb-1">Save your market profile above to generate revenue opportunities.</p>
    <p class="text-xs text-gray-400">Recommendations are tailored to your town size, business type, and nearby organizations.</p>
</div>
<?php elseif (empty($recs)): ?>
<div class="bg-white border border-gray-200 rounded-xl p-8 text-center mb-6">
    <p class="text-gray-500 text-sm mb-1">No opportunities matched yet.</p>
    <p class="text-xs text-gray-400">Add nearby organizations above — schools, employers, medical facilities — to unlock specific recommendations.</p>
</div>
<?php else: ?>
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-lg font-bold text-gray-900">Revenue Opportunities</h2>
    <p class="text-xs text-gray-400">Sorted by relevance to your market. Each includes an actionable playbook.</p>
</div>
<div class="space-y-4 mb-6">
    <?php foreach ($recs as $rec):
        $catClass = $categoryColors[$rec['category']] ?? 'bg-gray-50 text-gray-600 border-gray-100';
        $playbook = !empty($rec['playbook_json']) ? json_decode($rec['playbook_json'], true) : null;
        $feedbackStatus = $rec['feedback_status'] ?? null;
        $pbId = 'pb-' . (int)$rec['id'];
    ?>
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <!-- Header row -->
        <div class="flex items-start justify-between gap-4 mb-3">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <h3 class="font-bold text-gray-900"><?= h($rec['title']) ?></h3>
                    <span class="text-xs px-2 py-0.5 rounded-full border font-medium <?= $catClass ?>">
                        <?= h(ucfirst($rec['category'])) ?>
                    </span>
                    <?php if ($feedbackStatus === 'working'): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full border bg-green-50 text-green-700 border-green-200 font-medium">Working</span>
                    <?php elseif ($feedbackStatus === 'tried'): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full border bg-blue-50 text-blue-700 border-blue-200 font-medium">Tried</span>
                    <?php elseif ($feedbackStatus === 'considering'): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full border bg-gray-50 text-gray-500 border-gray-200 font-medium">Considering</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($rec['estimated_monthly_revenue'])): ?>
                <p class="text-sm text-green-700 font-medium mb-1">Est. <?= h($rec['estimated_monthly_revenue']) ?>/mo</p>
                <?php endif; ?>
                <p class="text-sm text-gray-600 leading-relaxed"><?= h($rec['description']) ?></p>
            </div>
            <form method="post" class="flex-shrink-0">
                <?= csrf() ?>
                <input type="hidden" name="action" value="dismiss">
                <input type="hidden" name="rec_id" value="<?= (int)$rec['id'] ?>">
                <button type="submit" class="text-xs text-gray-300 hover:text-gray-500 px-2 py-1" title="Dismiss">✕</button>
            </form>
        </div>

        <?php if ($playbook): ?>
        <!-- Playbook (collapsible) -->
        <div class="mt-3">
            <button type="button" onclick="togglePlaybook('<?= $pbId ?>')"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                <span id="<?= $pbId ?>-arrow">▶</span> View full playbook
            </button>
            <div id="<?= $pbId ?>" class="hidden mt-4 border border-dashed border-indigo-200 rounded-lg p-4 space-y-3 bg-indigo-50/30">
                <?php if (!empty($playbook['who_to_contact'])): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Who to contact</p>
                    <p class="text-sm text-gray-800"><?= nl2br(h($playbook['who_to_contact'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($playbook['what_to_offer'])): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">What to offer</p>
                    <p class="text-sm text-gray-800"><?= nl2br(h($playbook['what_to_offer'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($playbook['sample_pricing'])): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Sample pricing</p>
                    <p class="text-sm text-gray-800"><?= nl2br(h($playbook['sample_pricing'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($playbook['outreach_template'])): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Outreach template</p>
                    <pre class="text-sm text-gray-800 whitespace-pre-wrap font-sans bg-white border border-gray-200 rounded p-3 leading-relaxed"><?= h($playbook['outreach_template']) ?></pre>
                </div>
                <?php endif; ?>
                <?php if (!empty($playbook['timeline'])): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Timeline</p>
                    <p class="text-sm text-gray-800"><?= nl2br(h($playbook['timeline'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($playbook['success_signals']) && is_array($playbook['success_signals'])): ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Success signals</p>
                    <ul class="text-sm text-gray-800 list-disc list-inside space-y-0.5">
                        <?php foreach ($playbook['success_signals'] as $signal): ?>
                        <li><?= h((string)$signal) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <p class="text-xs text-gray-400 mt-2 italic">Playbook generating — refresh in a moment.</p>
        <?php endif; ?>

        <!-- Feedback bar -->
        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center gap-2 flex-wrap">
            <span class="text-xs text-gray-400 mr-1">Status:</span>
            <?php
            $statuses = [
                'considering'  => ['label' => 'Considering',  'class' => 'border-gray-300 text-gray-600 hover:bg-gray-50'],
                'tried'        => ['label' => 'Tried it',      'class' => 'border-blue-300 text-blue-600 hover:bg-blue-50'],
                'working'      => ['label' => 'It\'s working', 'class' => 'border-green-300 text-green-600 hover:bg-green-50'],
                'not_relevant' => ['label' => 'Not relevant',  'class' => 'border-red-200 text-red-500 hover:bg-red-50'],
            ];
            foreach ($statuses as $statusKey => $statusInfo):
                $isActive = $feedbackStatus === $statusKey;
            ?>
            <form method="post" class="inline">
                <?= csrf() ?>
                <input type="hidden" name="action" value="feedback">
                <input type="hidden" name="rec_id" value="<?= (int)$rec['id'] ?>">
                <input type="hidden" name="status" value="<?= $statusKey ?>">
                <button type="submit"
                        class="text-xs px-2.5 py-1 rounded-full border font-medium transition-colors <?= $isActive ? 'bg-indigo-600 text-white border-indigo-600' : $statusInfo['class'] ?>">
                    <?= $statusInfo['label'] ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($dismissed)): ?>
<div class="mt-2 mb-6">
    <details>
        <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600"><?= count($dismissed) ?> dismissed <?= count($dismissed) === 1 ? 'opportunity' : 'opportunities' ?></summary>
        <div class="mt-3 space-y-2">
            <?php foreach ($dismissed as $rec): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 flex items-center justify-between gap-4 opacity-60">
                <span class="text-sm text-gray-600"><?= h($rec['title']) ?></span>
                <form method="post" class="inline">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="undismiss">
                    <input type="hidden" name="rec_id" value="<?= (int)$rec['id'] ?>">
                    <button type="submit" class="text-xs text-indigo-500 hover:text-indigo-700 px-2 py-1 rounded hover:bg-indigo-50">Restore</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
</div>
<?php endif; ?>

<script>
function togglePlaybook(id) {
    const el    = document.getElementById(id);
    const arrow = document.getElementById(id + '-arrow');
    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        arrow.textContent = '▼';
    } else {
        el.classList.add('hidden');
        arrow.textContent = '▶';
    }
}
</script>

<?php include PF_ROOT . '/templates/footer.php'; ?>
