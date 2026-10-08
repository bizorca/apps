<?php
require __DIR__ . '/_bootstrap.php';
requireLogin();

if (!isAdmin()) {
    setFlash('error', 'Access denied.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$db = getDB();

// Actuals so far, for grounding the assumptions
$actualUsers    = (int)$db->query("SELECT COUNT(*) FROM as_members")->fetchColumn(); // Astrology members, not every tools account
$actualQuizzes  = (int)$db->query("SELECT COUNT(*) FROM as_starseed_results")->fetchColumn();
$actualLeads    = (int)$db->query("SELECT COUNT(*) FROM as_offer_interest")->fetchColumn();
$actualLeads30  = (int)$db->query("SELECT COUNT(*) FROM as_offer_interest WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$pageTitle = 'Business Model Calculator';
require AS_ROOT . '/templates/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Business Model Calculator</h1>
        <a href="<?= url('/admin-settings.php') ?>" class="text-sm text-gray-500 hover:text-brand-600">&larr; Admin Settings</a>
    </div>
    <p class="text-sm text-gray-500 mb-6">Annual gross revenue model for the offer ladder. Drag the sliders; everything recomputes live. Defaults are the base case that lands at ~$100k.</p>

    <!-- Actuals strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="text-2xl font-extrabold text-gray-900"><?= number_format($actualUsers) ?></div>
            <div class="text-xs text-gray-500 mt-1">Registered accounts (all time)</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="text-2xl font-extrabold text-gray-900"><?= number_format($actualQuizzes) ?></div>
            <div class="text-xs text-gray-500 mt-1">Quiz results saved</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="text-2xl font-extrabold text-gray-900"><?= number_format($actualLeads) ?></div>
            <div class="text-xs text-gray-500 mt-1">Offer leads (all time)</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="text-2xl font-extrabold text-gray-900"><?= number_format($actualLeads30) ?></div>
            <div class="text-xs text-gray-500 mt-1">Offer leads (last 30 days)</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- ── Inputs ── -->
        <div class="space-y-6">

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Funnel</h2>
                <div class="space-y-5" id="funnel-sliders">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="visitors" class="text-gray-700 font-medium">Targeted visitors / month</label>
                            <span class="font-bold text-gray-900" id="visitors-val"></span>
                        </div>
                        <input type="range" id="visitors" min="100" max="20000" step="100" value="3500" class="w-full accent-violet-700">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="startRate" class="text-gray-700 font-medium">Visitor &rarr; quiz start</label>
                            <span class="font-bold text-gray-900" id="startRate-val"></span>
                        </div>
                        <input type="range" id="startRate" min="5" max="50" step="1" value="25" class="w-full accent-violet-700">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="completeRate" class="text-gray-700 font-medium">Quiz start &rarr; completion</label>
                            <span class="font-bold text-gray-900" id="completeRate-val"></span>
                        </div>
                        <input type="range" id="completeRate" min="40" max="95" step="1" value="75" class="w-full accent-violet-700">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="registerRate" class="text-gray-700 font-medium">Reveal gate &rarr; account</label>
                            <span class="font-bold text-gray-900" id="registerRate-val"></span>
                        </div>
                        <input type="range" id="registerRate" min="30" max="90" step="1" value="65" class="w-full accent-violet-700">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="buyRate" class="text-gray-700 font-medium">Lead &rarr; 1:1 client (within a year)</label>
                            <span class="font-bold text-gray-900" id="buyRate-val"></span>
                        </div>
                        <input type="range" id="buyRate" min="0.5" max="10" step="0.25" value="5" class="w-full accent-violet-700">
                        <p class="text-xs text-gray-400 mt-1">5% assumes an email nurture sequence. No follow-up runs 1&ndash;2%.</p>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="costPerLead" class="text-gray-700 font-medium">Cost per lead (paid traffic)</label>
                            <span class="font-bold text-gray-900" id="costPerLead-val"></span>
                        </div>
                        <input type="range" id="costPerLead" min="0" max="5" step="0.25" value="0" class="w-full accent-violet-700">
                        <p class="text-xs text-gray-400 mt-1">$0 = organic only. Meta quiz funnels in this niche run $1&ndash;2/lead.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Ascension</h2>
                <div class="space-y-5">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="ascend1" class="text-gray-700 font-medium">1:1 &rarr; Activation Group</label>
                            <span class="font-bold text-gray-900" id="ascend1-val"></span>
                        </div>
                        <input type="range" id="ascend1" min="5" max="70" step="1" value="36" class="w-full accent-rose-600">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="ascend2" class="text-gray-700 font-medium">Activation &rarr; Timeline Clearing</label>
                            <span class="font-bold text-gray-900" id="ascend2-val"></span>
                        </div>
                        <input type="range" id="ascend2" min="10" max="80" step="1" value="50" class="w-full accent-teal-600">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="ascend3" class="text-gray-700 font-medium">Timeline &rarr; Integration Journey</label>
                            <span class="font-bold text-gray-900" id="ascend3-val"></span>
                        </div>
                        <input type="range" id="ascend3" min="10" max="80" step="1" value="44" class="w-full accent-amber-600">
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Pricing</h2>
                <div class="space-y-5">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="p1" class="text-gray-700 font-medium">Star Seed Reading 1:1</label>
                            <span class="font-bold text-gray-900" id="p1-val"></span>
                        </div>
                        <input type="range" id="p1" min="50" max="500" step="1" value="111" class="w-full accent-violet-700">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="p2" class="text-gray-700 font-medium">Activation Group (per seat)</label>
                            <span class="font-bold text-gray-900" id="p2-val"></span>
                        </div>
                        <input type="range" id="p2" min="100" max="1500" step="1" value="333" class="w-full accent-rose-600">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="p3" class="text-gray-700 font-medium">Timeline Clearing (per seat)</label>
                            <span class="font-bold text-gray-900" id="p3-val"></span>
                        </div>
                        <input type="range" id="p3" min="100" max="1500" step="1" value="444" class="w-full accent-teal-600">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="p4" class="text-gray-700 font-medium">Integration Journey (per seat)</label>
                            <span class="font-bold text-gray-900" id="p4-val"></span>
                        </div>
                        <input type="range" id="p4" min="300" max="5000" step="1" value="1111" class="w-full accent-amber-600">
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Capacity Assumptions</h2>
                <div class="space-y-5">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="hrs1on1" class="text-gray-700 font-medium">Hours per 1:1 (incl. prep)</label>
                            <span class="font-bold text-gray-900" id="hrs1on1-val"></span>
                        </div>
                        <input type="range" id="hrs1on1" min="1" max="3" step="0.25" value="1.5" class="w-full accent-gray-600">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="cohortSize" class="text-gray-700 font-medium">Group cohort size</label>
                            <span class="font-bold text-gray-900" id="cohortSize-val"></span>
                        </div>
                        <input type="range" id="cohortSize" min="4" max="20" step="1" value="11" class="w-full accent-gray-600">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <label for="hrsGroup" class="text-gray-700 font-medium">Hours per group session (incl. prep)</label>
                            <span class="font-bold text-gray-900" id="hrsGroup-val"></span>
                        </div>
                        <input type="range" id="hrsGroup" min="1" max="3" step="0.25" value="1.5" class="w-full accent-gray-600">
                    </div>
                    <p class="text-xs text-gray-400">Program lengths are fixed: Activation 6 sessions, Timeline 4, Integration 12. Model assumes a 50-week working year.</p>
                </div>
            </div>

            <button id="reset" class="w-full bg-gray-100 text-gray-700 py-2.5 rounded-lg hover:bg-gray-200 font-medium text-sm transition">Reset to Base Case</button>
        </div>

        <!-- ── Outputs ── -->
        <div class="space-y-6 lg:sticky lg:top-6 self-start">

            <div class="bg-gradient-to-br from-violet-100 via-rose-50 to-amber-50 border border-violet-200 rounded-xl p-6 text-center">
                <div class="text-xs font-semibold uppercase tracking-widest text-violet-700 mb-1">Annual Gross Revenue</div>
                <div class="text-5xl font-extrabold text-gray-900" id="out-total"></div>
                <div class="mt-3">
                    <div class="w-full bg-white bg-opacity-60 rounded-full h-3">
                        <div id="out-target-bar" class="h-3 rounded-full bg-violet-700 transition-all" style="width: 0%"></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1" id="out-target-label"></div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Annual Funnel</h2>
                <div class="space-y-2 text-sm" id="out-funnel"></div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Revenue by Rung</h2>
                <div class="space-y-3" id="out-rungs"></div>
                <div class="flex justify-between text-sm font-bold text-gray-900 pt-3 mt-3 border-t border-gray-200">
                    <span>Average client value</span><span id="out-acv"></span>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Jillian's Calendar</h2>
                <div class="space-y-2 text-sm" id="out-capacity"></div>
                <p class="text-xs mt-3" id="out-capacity-note"></p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="font-bold text-gray-900 mb-2">Ad Spend</h2>
                <div class="space-y-2 text-sm" id="out-adspend"></div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    var DEFAULTS = {
        visitors: 3500, startRate: 25, completeRate: 75, registerRate: 65,
        buyRate: 5, costPerLead: 0,
        ascend1: 36, ascend2: 50, ascend3: 44,
        p1: 111, p2: 333, p3: 444, p4: 1111,
        hrs1on1: 1.5, cohortSize: 11, hrsGroup: 1.5
    };
    var TARGET = 100000;
    var ids = Object.keys(DEFAULTS);

    function $(id) { return document.getElementById(id); }
    function fmt(n) { return Math.round(n).toLocaleString('en-US'); }
    function usd(n) { return '$' + fmt(n); }
    function pct(n) { return n + '%'; }

    var labelFmt = {
        visitors: fmt, startRate: pct, completeRate: pct, registerRate: pct,
        buyRate: function (n) { return n + '%'; },
        costPerLead: function (n) { return n > 0 ? '$' + Number(n).toFixed(2) : 'Organic ($0)'; },
        ascend1: pct, ascend2: pct, ascend3: pct,
        p1: usd, p2: usd, p3: usd, p4: usd,
        hrs1on1: function (n) { return n + ' hrs'; },
        cohortSize: function (n) { return n + ' people'; },
        hrsGroup: function (n) { return n + ' hrs'; }
    };

    function val(id) { return parseFloat($(id).value); }

    function row(label, value, muted) {
        return '<div class="flex justify-between"><span class="text-gray-' + (muted ? '400' : '600') + '">' + label + '</span>'
             + '<span class="font-semibold text-gray-900">' + value + '</span></div>';
    }

    function rungBar(name, seats, revenue, total, colorClass) {
        var share = total > 0 ? Math.round(revenue / total * 100) : 0;
        return '<div>'
            + '<div class="flex justify-between text-sm mb-1"><span class="text-gray-600">' + name + ' <span class="text-gray-400">(' + fmt(seats) + ')</span></span>'
            + '<span class="font-semibold text-gray-900">' + usd(revenue) + '</span></div>'
            + '<div class="w-full bg-gray-100 rounded-full h-2"><div class="h-2 rounded-full ' + colorClass + '" style="width:' + share + '%"></div></div>'
            + '</div>';
    }

    function compute() {
        ids.forEach(function (id) { $(id + '-val').textContent = labelFmt[id](val(id)); });

        var visitors    = val('visitors') * 12;
        var starts      = visitors * val('startRate') / 100;
        var completions = starts * val('completeRate') / 100;
        var leads       = completions * val('registerRate') / 100;
        var buyers      = leads * val('buyRate') / 100;

        var activation  = buyers * val('ascend1') / 100;
        var timeline    = activation * val('ascend2') / 100;
        var integration = timeline * val('ascend3') / 100;

        var rev1 = buyers * val('p1');
        var rev2 = activation * val('p2');
        var rev3 = timeline * val('p3');
        var rev4 = integration * val('p4');
        var total = rev1 + rev2 + rev3 + rev4;

        $('out-total').textContent = usd(total);
        var share = Math.min(100, total / TARGET * 100);
        $('out-target-bar').style.width = share.toFixed(1) + '%';
        $('out-target-label').textContent = (total / TARGET * 100).toFixed(0) + '% of the $100k target';

        $('out-funnel').innerHTML =
              row('Targeted visitors', fmt(visitors))
            + row('Quiz starts', fmt(starts))
            + row('Quiz completions', fmt(completions))
            + row('Leads (accounts created)', fmt(leads) + ' <span class="text-gray-400 font-normal">(' + (leads / 365).toFixed(1) + '/day)</span>')
            + row('1:1 clients', fmt(buyers) + ' <span class="text-gray-400 font-normal">(' + (buyers / 50).toFixed(1) + '/wk)</span>');

        $('out-rungs').innerHTML =
              rungBar('1:1 Readings', buyers, rev1, total, 'bg-violet-600')
            + rungBar('Activation', activation, rev2, total, 'bg-rose-500')
            + rungBar('Timeline Clearing', timeline, rev3, total, 'bg-teal-500')
            + rungBar('Integration', integration, rev4, total, 'bg-amber-500');
        $('out-acv').textContent = buyers > 0 ? usd(total / buyers) : '$0';

        var cohort = val('cohortSize');
        var groupSessionsYear = (activation / cohort) * 6 + (timeline / cohort) * 4 + (integration / cohort) * 12;
        var hours1on1Week = (buyers / 50) * val('hrs1on1');
        var hoursGroupWeek = (groupSessionsYear / 50) * val('hrsGroup');
        var totalHours = hours1on1Week + hoursGroupWeek;

        $('out-capacity').innerHTML =
              row('1:1 sessions / week', (buyers / 50).toFixed(1))
            + row('Group sessions / week', (groupSessionsYear / 50).toFixed(1))
            + row('Cohorts per year', fmt(activation / cohort) + ' activation &middot; ' + fmt(timeline / cohort) + ' timeline &middot; ' + fmt(integration / cohort) + ' integration')
            + row('Total hours / week', totalHours.toFixed(1) + ' hrs');
        var note = $('out-capacity-note');
        if (totalHours > 25) {
            note.textContent = 'Over 25 hrs/week — this is a full-time practice, not a side offering. Raise prices or cohort size instead of volume.';
            note.className = 'text-xs mt-3 text-red-600 font-medium';
        } else if (totalHours > 15) {
            note.textContent = 'Heavy but doable alongside the clinic. Watch for burnout at this pace.';
            note.className = 'text-xs mt-3 text-amber-600 font-medium';
        } else {
            note.textContent = 'Fits alongside the clinic practice.';
            note.className = 'text-xs mt-3 text-green-600 font-medium';
        }

        var adSpend = leads * val('costPerLead');
        $('out-adspend').innerHTML =
              row('Annual ad budget', usd(adSpend))
            + row('Gross after ad spend', usd(total - adSpend))
            + (adSpend > 0 ? row('Revenue per ad dollar', '$' + (adSpend > 0 ? (total / adSpend).toFixed(2) : '0')) : row('Traffic source', 'Organic', true));
    }

    ids.forEach(function (id) { $(id).addEventListener('input', compute); });
    $('reset').addEventListener('click', function () {
        ids.forEach(function (id) { $(id).value = DEFAULTS[id]; });
        compute();
    });

    compute();
})();
</script>

<?php require AS_ROOT . '/templates/footer.php'; ?>
