<?php

namespace App\Core;

class Helpers
{
    public static function money(float $amount): string
    {
        $prefix = $amount < 0 ? '-$' : '$';
        return $prefix . number_format(abs($amount), 2);
    }

    public static function percent(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals) . '%';
    }

    public static function ratingColor(float $rating): string
    {
        // In Burn Rate, high entourage ratings = more spending = GOOD (for going bankrupt)
        if ($rating < 26) return 'text-gray-400';
        if ($rating > 75) return 'text-red-500';
        return 'text-amber-500';
    }

    public static function ratingBadge(float $rating): string
    {
        $color = self::ratingColor($rating);
        return '<span class="font-bold ' . $color . '">' . number_format($rating, 0) . '%</span>';
    }

    public static function redirect(string $url): never
    {
        // Prepend base path for relative URLs
        if (str_starts_with($url, '/') && function_exists('url')) {
            $url = url($url);
        }
        header("Location: {$url}");
        exit;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function csrfField(string $token): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . self::escape($token) . '">';
    }

    public static function generatePassword(): string
    {
        $colors = ['Red', 'Blue', 'Green', 'Gold', 'Silver', 'Purple', 'Orange', 'Teal', 'Jade', 'Ruby'];
        $animals = ['Tiger', 'Eagle', 'Falcon', 'Lion', 'Bear', 'Wolf', 'Hawk', 'Fox', 'Shark', 'Cobra'];
        $number = rand(100, 999);
        return $colors[array_rand($colors)] . $animals[array_rand($animals)] . $number;
    }

    public static function turnToYearsMonths(int $turn): string
    {
        $years = intdiv($turn, 12);
        $months = $turn % 12;
        $parts = [];
        if ($years > 0) $parts[] = "{$years} yr" . ($years > 1 ? 's' : '');
        if ($months > 0) $parts[] = "{$months} mo" . ($months > 1 ? 's' : '');
        return implode(' ', $parts) ?: '0 mo';
    }

    public static function profitLossColor(float $amount): string
    {
        if ($amount > 0) return 'text-green-600';
        if ($amount < 0) return 'text-red-600';
        return 'text-gray-600';
    }

    /**
     * Burn Rate inverted color: LOW balance = good (green), HIGH = bad (red)
     * Because in this game, running out of money is WINNING.
     */
    public static function burnColor(float $amount): string
    {
        if ($amount <= 0) return 'text-green-500'; // Broke = winning!
        if ($amount < 1000000000) return 'text-amber-500'; // Getting there
        return 'text-red-500'; // Still rich = losing
    }

    /**
     * Format large numbers in a human-readable way.
     * $10,000,000,000 → "$10.0B"
     */
    public static function moneyShort(float $amount): string
    {
        $prefix = $amount < 0 ? '-' : '';
        $abs = abs($amount);
        if ($abs >= 1000000000) return $prefix . '$' . number_format($abs / 1000000000, 1) . 'B';
        if ($abs >= 1000000) return $prefix . '$' . number_format($abs / 1000000, 1) . 'M';
        if ($abs >= 1000) return $prefix . '$' . number_format($abs / 1000, 1) . 'K';
        return $prefix . '$' . number_format($abs, 2);
    }
}
