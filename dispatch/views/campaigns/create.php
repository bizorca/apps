<?php
use Dispatch\Core\View;
$title = 'New Campaign';
// $venues: array of all active venues
?>
<div class="max-w-3xl">
    <div class="mb-6">
        <a href="<?= $_base ?>/campaigns" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Campaigns
        </a>
    </div>

    <form method="POST" action="<?= $_base ?>/campaigns" class="space-y-8">
        <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">

        <!-- Event Details -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-semibold text-slate-900 mb-5 pb-4 border-b border-slate-100">Event Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Campaign / Event Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required autofocus
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm"
                        placeholder="Summer Solstice Market">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Event Date <span class="text-red-500">*</span></label>
                    <input type="date" name="event_date" required min="<?= date('Y-m-d') ?>"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Event Time <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="time" name="event_time"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Location <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" name="location"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm"
                        placeholder="Fort Worden State Park, Port Townsend WA">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                    <textarea name="description" rows="4"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm resize-none"
                        placeholder="Event description for use in submissions. This is your base copy."></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Asset Link <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="url" name="asset_link"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm"
                        placeholder="https://drive.google.com/... (shared folder with flyers, photos, etc.)">
                </div>
            </div>
        </div>

        <!-- Recurrence -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-semibold text-slate-900 mb-5 pb-4 border-b border-slate-100">Recurrence <span class="text-slate-400 font-normal text-sm">— optional</span></h2>
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Repeat</label>
                        <select name="recurrence_type" id="recurrence_type" onchange="toggleRecurrence(this.value)"
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            <option value="">No recurrence</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly — by date</option>
                            <option value="monthly_weekday">Monthly — by weekday</option>
                        </select>
                    </div>
                    <div id="recurrence_interval_field" class="hidden">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Every N periods</label>
                        <input type="number" name="recurrence_interval" min="1" max="52" value="1"
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div id="recurrence_end_date_field" class="hidden">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">End Date</label>
                        <input type="date" name="recurrence_end_date"
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Monthly by weekday options -->
                <div id="recurrence_weekday_fields" class="hidden border border-slate-100 bg-slate-50 rounded-xl p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Which week(s)</label>
                        <div class="flex flex-wrap gap-4">
                            <?php foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => 'Last'] as $n => $label): ?>
                            <label class="flex items-center gap-1.5 cursor-pointer select-none">
                                <input type="checkbox" name="recurrence_weekday_weeks[]" value="<?= $n ?>"
                                    class="w-4 h-4 text-indigo-600 border-slate-300 rounded">
                                <span class="text-sm text-slate-700"><?= $label ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="max-w-xs">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Day of week</label>
                        <select name="recurrence_weekday_day"
                            class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Venue Selection -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-100">
                <h2 class="text-base font-semibold text-slate-900">Select Venues</h2>
                <span class="text-xs text-slate-400">Choose which outlets to target</span>
            </div>

            <?php if (empty($venues)): ?>
            <p class="text-slate-500 text-sm">No venues in the library yet. Check back soon!</p>
            <?php else: ?>

            <!-- Quick select all -->
            <div class="flex gap-3 mb-5">
                <button type="button" onclick="selectAllVenues(true)" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-lg">Select All</button>
                <button type="button" onclick="selectAllVenues(false)" class="text-xs font-medium text-slate-600 hover:text-slate-700 bg-slate-100 px-3 py-1.5 rounded-lg">Deselect All</button>
            </div>

            <?php
            // Group venues by type
            $typeLabels = [
                'digital_social'   => '📱 Social Media',
                'digital_calendar' => '🗓 Online Calendars',
                'print'            => '📰 Print',
                'radio'            => '📻 Radio',
                'physical'         => '📌 Physical / Bulletin Boards',
                'email_newsletter' => '📧 Email Newsletters',
                'other'            => '📋 Other',
            ];
            $grouped = [];
            foreach ($venues as $v) { $grouped[$v['type']][] = $v; }
            ?>

            <div class="space-y-6">
                <?php foreach ($typeLabels as $type => $label):
                    if (empty($grouped[$type])) continue;
                ?>
                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><?= $label ?></h3>
                    <div class="grid sm:grid-cols-2 gap-2">
                        <?php foreach ($grouped[$type] as $venue): ?>
                        <label class="flex items-start gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:border-indigo-300 hover:bg-indigo-50/50 transition-all has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="venue_ids[]" value="<?= $venue['id'] ?>" class="mt-0.5 w-4 h-4 text-indigo-600 border-slate-300 rounded">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 leading-tight"><?= View::e($venue['name']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    <?= $venue['lead_time_days'] + $venue['buffer_days'] ?> days before event
                                    · <?= View::e(Dispatch\Models\Venue::submissionMethods()[$venue['submission_method']] ?? $venue['submission_method']) ?>
                                </p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-between">
            <a href="<?= $_base ?>/campaigns" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
            <button type="submit" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm">
                Create Campaign & Generate Plan
            </button>
        </div>
    </form>
</div>

<script>
function toggleRecurrence(val) {
    const isWeekday   = val === 'monthly_weekday';
    const hasRecur    = val !== '';
    document.getElementById('recurrence_interval_field').classList.toggle('hidden', !hasRecur || isWeekday);
    document.getElementById('recurrence_end_date_field').classList.toggle('hidden', !hasRecur);
    document.getElementById('recurrence_weekday_fields').classList.toggle('hidden', !isWeekday);
}
function selectAllVenues(select) {
    document.querySelectorAll('input[name="venue_ids[]"]').forEach(cb => cb.checked = select);
}
</script>
