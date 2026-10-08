<?php
declare(strict_types=1);

/**
 * Industry valuation multiples.
 *
 * sde: [floor, mid, ceiling] — applied to Seller's Discretionary Earnings
 * rev: [floor, mid, ceiling] — applied to trailing twelve-month revenue
 *
 * Sources as cited by the original author: IBBA Market Pulse Q4 2023;
 * BizBuySell Insight Report 2023. Not re-verified against those reports during
 * the 2026-10 port, and not updated since 2023. Update annually.
 */
function getIndustryMultiples(): array
{
    return [
        'retail' => [
            'name' => 'Retail',
            'sde'  => [1.5, 2.2, 2.8],
            'rev'  => [0.3, 0.5, 0.8],
        ],
        'restaurant' => [
            'name' => 'Restaurant / Food & Beverage',
            'sde'  => [1.0, 1.8, 2.5],
            'rev'  => [0.3, 0.5, 0.7],
        ],
        'professional_services' => [
            'name' => 'Professional Services',
            'sde'  => [1.5, 2.2, 3.0],
            'rev'  => [0.4, 0.7, 1.2],
        ],
        'healthcare' => [
            'name' => 'Healthcare / Medical',
            'sde'  => [2.5, 3.5, 4.5],
            'rev'  => [0.6, 1.0, 1.5],
        ],
        'technology' => [
            'name' => 'Technology / Software',
            'sde'  => [3.0, 4.5, 6.0],
            'rev'  => [0.8, 1.5, 3.0],
        ],
        'manufacturing' => [
            'name' => 'Manufacturing',
            'sde'  => [2.0, 2.8, 3.5],
            'rev'  => [0.3, 0.5, 0.8],
        ],
        'construction' => [
            'name' => 'Construction / Trades',
            'sde'  => [1.5, 2.2, 3.0],
            'rev'  => [0.2, 0.4, 0.6],
        ],
        'distribution' => [
            'name' => 'Distribution / Wholesale',
            'sde'  => [1.5, 2.0, 2.8],
            'rev'  => [0.2, 0.4, 0.6],
        ],
        'auto_services' => [
            'name' => 'Auto Services',
            'sde'  => [1.5, 2.2, 2.8],
            'rev'  => [0.3, 0.5, 0.7],
        ],
        'personal_services' => [
            'name' => 'Personal Services',
            'sde'  => [1.2, 1.8, 2.5],
            'rev'  => [0.3, 0.5, 0.8],
        ],
        'other' => [
            'name' => 'Other / General Business',
            'sde'  => [1.5, 2.0, 2.5],
            'rev'  => [0.3, 0.5, 0.7],
        ],
    ];
}

function getIndustryOptions(): array
{
    $out = [];
    foreach (getIndustryMultiples() as $key => $data) {
        $out[$key] = $data['name'];
    }
    return $out;
}

function getMultiplesForIndustry(string $key): array
{
    $all = getIndustryMultiples();
    return $all[$key] ?? $all['other'];
}
