<div class="min-h-[50vh] flex items-center justify-center">
  <div class="max-w-md text-center">
    <p class="text-6xl font-bold text-navy/20"><?= h((string) $code) ?></p>
    <p class="mt-4 text-lg"><?= h($message) ?></p>
    <a href="<?= url('/') ?>" class="mt-6 inline-block text-sm text-navy underline underline-offset-2">
      Back to start
    </a>
  </div>
</div>
