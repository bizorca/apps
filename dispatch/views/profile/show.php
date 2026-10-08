<?php
use Dispatch\Core\View;
$title = 'My Profile';
// $user: user row
?>
<div class="max-w-2xl space-y-6">

    <!-- Profile Info -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <div class="flex items-center gap-4 mb-6 pb-5 border-b border-slate-100">
            <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center text-indigo-700 font-bold text-2xl">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="text-xl font-bold text-slate-900"><?= View::e($user['name']) ?></h2>
                <p class="text-slate-500 text-sm"><?= View::e($user['email']) ?></p>
                <span class="inline-flex mt-1 items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $user['role'] === 'sysop' ? 'bg-violet-100 text-violet-700' : ($user['role'] === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600') ?>">
                    <?= strtoupper($user['role']) ?>
                </span>
            </div>
        </div>

        <form method="POST" action="<?= $_base ?>/profile" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <p class="text-sm text-slate-500">Your name, email and password belong to your Bizorca Tools account and are shared by every tool. <a href="/account/settings.php" class="text-indigo-600 hover:text-indigo-700 font-medium">Change them in account settings &rarr;</a></p>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Digest</label>
                <select name="notification_preference"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="daily"  <?= $user['notification_preference'] === 'daily'  ? 'selected' : '' ?>>Daily digest</option>
                    <option value="weekly" <?= $user['notification_preference'] === 'weekly' ? 'selected' : '' ?>>Weekly digest (Mondays)</option>
                    <option value="none"   <?= $user['notification_preference'] === 'none'   ? 'selected' : '' ?>>No email notifications</option>
                </select>
                <p class="mt-1 text-xs text-slate-400">How often to receive a summary of upcoming deadlines.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Deadline Reminder Window</label>
                <select name="remind_days_before"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ([1 => '1 day before due date', 2 => '2 days before', 3 => '3 days before (default)', 5 => '5 days before', 7 => '7 days before', 14 => '14 days before'] as $days => $label): ?>
                    <option value="<?= $days ?>" <?= (int) ($user['remind_days_before'] ?? 3) === $days ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-400">Include tasks in your digest when they fall within this window. Disable entirely by setting Email Digest to "None."</p>
            </div>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors">
                Save Changes
            </button>
        </form>
    </div>

    <p class="text-xs text-slate-400 text-center">Using Dispatch since <?= View::date($user['joined_at'], 'F Y') ?></p>
</div>
