<?php
/**
 * Triage row. Expects $tType, $tId, and optionally $tMarks (array of set marks).
 * Keys apply to whichever row has focus, so a list can be walked with Tab.
 */
$tMarks = $tMarks ?? [];
$keys = [
  'favorite'    => ['F', 'Favorite', '&#9733;'],
  'read'        => ['R', 'Read',     '&#10003;'],
  'write_about' => ['W', 'Write',    '&#9998;'],
  'meh'         => ['M', 'Meh',      '&#8722;'],
];
?>
<span class="triage inline-flex gap-1" tabindex="0"
      data-type="<?= h($tType) ?>" data-id="<?= (int) $tId ?>"
      x-data="triageRow(<?= htmlspecialchars(json_encode($tMarks), ENT_QUOTES) ?>)"
      @keydown.window="maybeKey($event)">
<?php foreach ($keys as $mark => [$key, $label, $glyph]): ?>
  <button type="button" title="<?= $label ?> (<?= $key ?>)"
          @click="toggle('<?= $mark ?>')"
          :class="on['<?= $mark ?>'] ? 'bg-navy text-cream border-navy' : 'text-muted border-black/15 hover:border-navy'"
          class="rounded border px-1.5 py-0.5 text-[11px] leading-none transition">
    <?= $glyph ?><span class="ml-0.5 opacity-60"><?= $key ?></span>
  </button>
<?php endforeach; ?>
</span>
