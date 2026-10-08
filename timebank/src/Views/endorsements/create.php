<?php $pageTitle = 'Endorse ' . ($member['display_name'] ?: trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))); ?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
            Endorse <?= e($member['display_name'] ?: trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
        </h1>
        <a href="<?= url('/members/' . ($member['id'] ?? '')) ?>"
           class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Back to profile</a>
    </div>

    <!-- Member info -->
    <div class="flex items-center gap-4 bg-teal-50 border border-teal-100 rounded-2xl p-4 mb-6">
        <?php if (!empty($member['avatar_path'])): ?>
            <img src="<?= e(media_url($member['avatar_path'])) ?>" alt="" class="w-14 h-14 rounded-full object-cover flex-shrink-0">
        <?php else: ?>
            <div class="w-14 h-14 rounded-full bg-teal-200 flex items-center justify-center text-teal-700 font-bold text-xl flex-shrink-0">
                <?= e(mb_strtoupper(mb_substr($member['first_name'] ?? 'M', 0, 1))) ?>
            </div>
        <?php endif; ?>
        <div>
            <p class="font-semibold text-gray-900">
                <?= e($member['display_name'] ?: trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
            </p>
            <?php if (!empty($member['city'])): ?>
                <p class="text-sm text-gray-500"><?= e($member['city']) ?><?= !empty($member['state']) ? ', ' . e($member['state']) : '' ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="space-y-1">
                <?php foreach (array_merge(...array_map(fn($x) => (array) $x, array_values($errors))) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="<?= url('/endorsements/create') ?>" class="space-y-6"
              x-data="{ rating: <?= (int)(old('rating') ?: 5) ?> }">
            <?= csrf_field() ?>
            <input type="hidden" name="to_member_id" value="<?= (int)($member['id'] ?? 0) ?>">

            <!-- Star rating -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Rating <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-2">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="rating" value="<?= $s ?>" class="sr-only"
                                   @change="rating = <?= $s ?>"
                                   <?= (int)(old('rating') ?: 5) === $s ? 'checked' : '' ?>>
                            <svg class="w-8 h-8 transition-colors"
                                 :class="rating >= <?= $s ?> ? 'text-amber-400' : 'text-gray-200'"
                                 @mouseenter="rating = <?= $s ?>"
                                 fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </label>
                    <?php endfor; ?>
                    <span class="ml-2 text-sm text-gray-500" x-text="['', 'Poor', 'Fair', 'Good', 'Great', 'Outstanding'][rating]"></span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Click a star to set your rating.</p>
            </div>

            <!-- Comment -->
            <div>
                <label for="comment" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Comment <span class="text-gray-400 font-normal">(optional but appreciated)</span>
                </label>
                <textarea id="comment" name="comment" rows="5"
                          placeholder="Share what it was like working with this person. What did they do well? What made it a good exchange?"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"><?= old('comment') ?></textarea>
            </div>

            <!-- Link to transaction -->
            <?php if (!empty($transactions)): ?>
                <div>
                    <label for="transaction_id" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Link to a transaction <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <select id="transaction_id" name="transaction_id"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
                        <option value="">-- Not linked to a specific transaction --</option>
                        <?php foreach ($transactions as $tx): ?>
                            <option value="<?= (int)($tx['id'] ?? 0) ?>" <?= old('transaction_id') == ($tx['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e(date('M j, Y', strtotime($tx['service_date'] ?? $tx['created_at'] ?? ''))) ?> &mdash;
                                <?= e(truncate($tx['description'] ?? 'Exchange', 50)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-3 pt-2">
                <a href="<?= url('/members/' . ($member['id'] ?? '')) ?>"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                    Submit Endorsement
                </button>
            </div>
        </form>
    </div>
</div>
