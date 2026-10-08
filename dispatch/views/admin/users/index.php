<?php
use Dispatch\Core\View;
use Dispatch\Core\Auth;
$title = 'Manage Users';
// $users: array of all users
$isSysOp = Auth::isSysOp();
?>
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-900">All Users (<?= count($users) ?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">User</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Role</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Joined</th>
                    <?php if ($isSysOp): ?>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-6 py-3">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-indigo-700 font-semibold text-sm"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900 text-sm"><?= View::e($u['name']) ?></p>
                                <p class="text-xs text-slate-400"><?= View::e($u['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $u['role'] === 'sysop' ? 'bg-violet-100 text-violet-700' : ($u['role'] === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600') ?>">
                            <?= strtoupper($u['role']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500"><?= View::date($u['joined_at']) ?></td>
                    <?php if ($isSysOp): ?>
                    <td class="px-6 py-4">
                        <?php if ((string)$u['id'] !== (string)Auth::userId()): ?>
                        <form method="POST" action="<?= $_base ?>/admin/users/<?= $u['id'] ?>/role" class="flex items-center gap-2">
                            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
                            <select name="role" onchange="this.form.submit()"
                                class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white text-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <option value="user"  <?= $u['role'] === 'user'  ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="sysop" <?= $u['role'] === 'sysop' ? 'selected' : '' ?>>SysOp</option>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-slate-400 italic">You</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
