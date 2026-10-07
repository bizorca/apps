<?php
// Required before including: $currentStep (int), $stepStatus (array), $biz (array)
$steps = getWizardSteps($biz['business_type'] ?? 'yoga');
$total = count($steps);
?>
<div class="mb-8">
    <ol class="flex items-center gap-0">
        <?php foreach ($steps as $n => $step):
            $done   = ($stepStatus[$n] ?? false) && $n < $currentStep;
            $active = $n === $currentStep;
            $last   = $n === $total;
        ?>
        <li class="flex items-center <?= $last ? '' : 'flex-1' ?>">
            <a href="<?= h($step['url']) ?>"
               class="flex flex-col items-center group <?= $active ? 'pointer-events-none' : '' ?>">
                <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold border-2 transition-colors
                    <?php if ($active): ?>bg-indigo-600 border-indigo-600 text-white
                    <?php elseif ($done): ?>bg-green-500 border-green-500 text-white
                    <?php else: ?>bg-white border-gray-300 text-gray-400 group-hover:border-indigo-400<?php endif; ?>">
                    <?php if ($done): ?>&#10003;<?php else: ?><?= $n ?><?php endif; ?>
                </span>
                <span class="mt-1 text-xs font-medium
                    <?= $active ? 'text-indigo-700' : ($done ? 'text-green-600' : 'text-gray-400') ?>">
                    <?= h($step['label']) ?>
                </span>
            </a>
            <?php if (!$last): ?>
            <div class="flex-1 h-0.5 mx-2 <?= $done ? 'bg-green-400' : 'bg-gray-200' ?>"></div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
</div>
