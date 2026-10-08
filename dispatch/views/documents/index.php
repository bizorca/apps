<?php
use Dispatch\Core\View;
$title = 'Document Library';
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 space-y-8">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Document Library</h1>
            <p class="text-sm text-slate-500 mt-1">Text copy, flyers, and submission assets. Mark any document as a template to share it with your team.</p>
        </div>
    </div>

    <!-- Upload form -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-5 pb-4 border-b border-slate-100">Add Document</h2>
        <form method="POST" action="<?= $_base ?>/documents" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= View::e($_csrf) ?>">
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                        placeholder="e.g. March Newsletter Copy, Event Flyer v2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" name="description"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                        placeholder="Brief note about what this is for">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Text Content <span class="text-slate-400 font-normal">(for copy, event listings, press releases)</span></label>
                    <textarea name="content" rows="5"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm resize-y"
                        placeholder="Paste your event copy, newsletter text, or press release here..."></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">File Upload <span class="text-slate-400 font-normal">(images, PDF, Word — max 10 MB)</span></label>
                    <input type="file" name="document_file"
                        accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.txt,.rtf"
                        class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_template" value="1"
                            class="w-4 h-4 text-indigo-600 border-slate-300 rounded">
                        <span class="text-sm text-slate-700">Add to shared template library</span>
                    </label>
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors">Save Document</button>
            </div>
        </form>
    </div>

    <!-- Own documents -->
    <div class="bg-white rounded-2xl border border-slate-200">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">My Documents</h2>
        </div>
        <?php if (empty($own)): ?>
        <p class="px-6 py-8 text-sm text-slate-400 text-center">No documents yet. Add one above.</p>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach ($own as $doc): ?>
            <?php include __DIR__ . '/_row.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Shared templates from others -->
    <?php if (!empty($templates)): ?>
    <div class="bg-white rounded-2xl border border-slate-200">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">Shared Templates</h2>
            <p class="text-xs text-slate-400 mt-0.5">Documents your teammates have shared for reuse.</p>
        </div>
        <div class="divide-y divide-slate-50">
            <?php foreach ($templates as $doc): ?>
            <?php $isOwn = false; include __DIR__ . '/_row.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
