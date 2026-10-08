<?php
if (!defined('LMS_LOADED')) {
    require_once __DIR__ . '/_bootstrap.php';
}
http_response_code(404);
$page_title = '404 — ' . APP_NAME;
include LT_ROOT . '/includes/header.php';
?>
<div class="max-w-6xl mx-auto px-6 py-24 text-center">
  <div class="text-6xl font-black text-slate-200 mb-4">404</div>
  <p class="text-slate-500 mb-6">That page doesn't exist.</p>
  <a href="<?= APP_URL ?>/home.php" class="inline-block border border-slate-300 text-slate-700 text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-slate-50">Go home</a>
</div>
<?php include LT_ROOT . '/includes/footer.php'; ?>
