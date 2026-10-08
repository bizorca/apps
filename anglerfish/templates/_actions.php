<?php
/**
 * Compose / Illustrate actions for one piece of source material.
 *
 * Expects $aType (subject_type) and $aId (subject_id) to be set by the caller,
 * same convention as _triage.php. Both are unset afterwards so a later include
 * in the same loop cannot inherit a stale subject.
 */
$aType = $aType ?? '';
$aId = (int) ($aId ?? 0);
?>
<?php if ($aType && $aId): ?>
  <a class="text-[11px] font-bold uppercase tracking-wide text-navy hover:text-gold"
     title="Write a post from this"
     href="<?= url('/compose?subject_type=' . urlencode($aType) . '&subject_id=' . $aId) ?>">Compose</a>
  <a class="text-[11px] font-bold uppercase tracking-wide text-navy hover:text-gold"
     title="Generate a standalone image from this"
     href="<?= url('/generate?subject_type=' . urlencode($aType) . '&subject_id=' . $aId) ?>">Image</a>
<?php endif; ?>
<?php $aType = null; $aId = 0; ?>
