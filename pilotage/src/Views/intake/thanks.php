<?php /** @var array $tenant */ ?>
<div class="bg-white rounded-lg border border-slate-200 p-8 shadow-sm text-center">
  <h1 class="text-xl font-semibold mb-3">Thank you — it arrived.</h1>
  <p class="text-sm text-slate-600 mb-2">
    A real person at <?= h($tenant['name']) ?> will read what you wrote and come back to you.
  </p>
  <p class="text-sm text-slate-600">
    There is a confirmation on its way to the address you gave. If it does not show up
    in a few minutes, it is worth a look in your spam folder.
  </p>
</div>
