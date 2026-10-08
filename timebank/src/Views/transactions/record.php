<?php $pageTitle = 'Record Hours'; ?>
<?php
$currencyName   = $tenant['currency_name'] ?? 'Hour';
$currentUserId  = \TimeBank\Core\Auth::id();
$prefillOffer   = $offer ?? null;
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Record Hours</h1>
        <a href="<?= url('/transactions') ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">

        <form method="POST" action="<?= url('/transactions/record') ?>" class="space-y-6"
              x-data="{
                  type: '<?= old('type', 'one_to_one') ?>',
                  showReceiver: true,
                  showMultiReceiver: false,
                  updateType(t) {
                      this.type = t;
                      this.showReceiver = (t === 'one_to_one');
                      this.showMultiReceiver = (t === 'one_to_many');
                  }
              }"
              x-init="updateType(type)">
            <?= csrf_field() ?>

            <!-- Transaction type -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">Transaction Type</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <?php
                    $types = [
                        'one_to_one'  => ['label' => 'One-to-One', 'desc'  => 'One provider, one receiver'],
                        'one_to_many' => ['label' => 'Class / Workshop', 'desc' => 'One provider, multiple receivers'],
                    ];
                    ?>
                    <?php foreach ($types as $val => $info): ?>
                        <label class="relative flex flex-col items-start gap-1 p-3.5 border-2 rounded-xl cursor-pointer transition-all"
                               :class="type === '<?= $val ?>' ? 'border-teal-500 bg-teal-50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="type" value="<?= $val ?>"
                                   class="sr-only"
                                   @change="updateType('<?= $val ?>')"
                                   <?= old('type', 'one_to_one') === $val ? 'checked' : '' ?>>
                            <span class="text-sm font-semibold text-gray-800"><?= $info['label'] ?></span>
                            <span class="text-xs text-gray-500"><?= $info['desc'] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Provider -->
            <div>
                <label for="provider_id" class="block text-sm font-medium text-gray-700 mb-1.5">Provider <span class="text-red-500">*</span></label>
                <select id="provider_id" name="provider_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                    <?php foreach ($members ?? [] as $m): ?>
                        <?php
                        $preProvider = old('provider_id', (string)($prefillOffer['member_id'] ?? $currentUserId));
                        $selected = (string)($m['id'] ?? '') === $preProvider;
                        ?>
                        <option value="<?= (int)($m['id'] ?? 0) ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">The person who gave the service or taught the class.</p>
            </div>

            <!-- Receiver (one-to-one) -->
            <div x-show="showReceiver" x-transition>
                <label for="receiver_id" class="block text-sm font-medium text-gray-700 mb-1.5">Receiver <span class="text-red-500">*</span></label>
                <select id="receiver_id" name="receiver_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                    <option value="">-- Select receiver --</option>
                    <?php foreach ($members ?? [] as $m): ?>
                        <?php $selected = old('receiver_id') === (string)($m['id'] ?? ''); ?>
                        <option value="<?= (int)($m['id'] ?? 0) ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">The person who received the service.</p>
            </div>

            <!-- Multi-receiver note (one-to-many) -->
            <div x-show="showMultiReceiver" x-cloak x-transition>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Participants</label>
                <div class="p-4 bg-teal-50 rounded-xl border border-teal-200 text-sm text-teal-700">
                    For classes and workshops with multiple attendees, add each participant below. Each will be charged the specified hours from their balance.
                </div>
                <div id="participants-list" class="mt-3 space-y-2">
                    <div class="flex items-center gap-2">
                        <select name="participants[]"
                                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
                            <option value="">-- Select participant --</option>
                            <?php foreach ($members ?? [] as $m): ?>
                                <option value="<?= (int)($m['id'] ?? 0) ?>">
                                    <?= e($m['display_name'] ?: trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="button"
                        onclick="const l=document.getElementById('participants-list');const div=document.createElement('div');div.className='flex items-center gap-2';div.innerHTML=l.children[0].innerHTML;l.appendChild(div);"
                        class="mt-2 text-sm text-teal-600 hover:text-teal-800 font-medium">
                    + Add another participant
                </button>
            </div>

            <!-- Hours -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="hours" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Hours <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="hours" name="hours"
                           value="<?= old('hours', '1.00') ?>"
                           required min="0.25" max="24" step="0.25"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                    <p class="text-xs text-gray-400 mt-1">Minimum 0.25 (15 minutes), in 15-minute increments.</p>
                </div>
                <div>
                    <label for="prep_hours" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Prep hours <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="number" id="prep_hours" name="prep_hours"
                           value="<?= old('prep_hours', '0.00') ?>"
                           min="0" max="24" step="0.25"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                    <p class="text-xs text-gray-400 mt-1">For classes: preparation time for the provider.</p>
                </div>
            </div>

            <!-- Service date -->
            <div>
                <label for="service_date" class="block text-sm font-medium text-gray-700 mb-1.5">Service Date <span class="text-red-500">*</span></label>
                <input type="date" id="service_date" name="service_date"
                       value="<?= old('service_date', date('Y-m-d')) ?>"
                       required
                       max="<?= date('Y-m-d') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">Description <span class="text-red-500">*</span></label>
                <textarea id="description" name="description" rows="3"
                          required
                          placeholder="Briefly describe what was exchanged..."
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('description', !empty($prefillOffer) ? $prefillOffer['title'] : '') ?></textarea>
            </div>

            <!-- Link to offer -->
            <div>
                <label for="offer_id" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Link to offer/request <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <select id="offer_id" name="offer_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                    <option value="">-- Not linked to a specific offer --</option>
                    <?php foreach ($offer_list ?? [] as $o): ?>
                        <?php
                        $preOffer = old('offer_id', (string)($prefillOffer['id'] ?? ''));
                        $sel = (string)($o['id'] ?? '') === $preOffer;
                        ?>
                        <option value="<?= (int)($o['id'] ?? 0) ?>" <?= $sel ? 'selected' : '' ?>>
                            [<?= e(ucfirst($o['type'] ?? 'offer')) ?>] <?= e(truncate($o['title'] ?? '', 60)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/transactions') ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                    Record Exchange
                </button>
            </div>
        </form>
    </div>
</div>
