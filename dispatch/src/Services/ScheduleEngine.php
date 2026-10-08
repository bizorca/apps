<?php

declare(strict_types=1);

namespace Dispatch\Services;

use Dispatch\Core\Database;
use Dispatch\Models\Venue;
use Dispatch\Models\Campaign;
use Dispatch\Models\ActionItem;

class ScheduleEngine
{
    /**
     * Generate action items for a campaign based on selected venues.
     * Deletes existing action items for the campaign first.
     *
     * @param int   $campaignId
     * @param array $venueIds    Array of venue IDs to generate tasks for
     * @return int  Number of action items created
     */
    public static function generate(int $campaignId, array $venueIds): int
    {
        $campaign = Campaign::findById($campaignId);
        if (!$campaign) return 0;

        // Only remove pending/overdue items — complete and skipped are preserved as history
        ActionItem::deletePendingByCampaign($campaignId);

        // Don't recreate items for venues that already have a complete/skipped record
        $preservedVenueIds = ActionItem::getCompletedVenueIds($campaignId);

        $created = 0;
        $eventDate = $campaign['event_date'];

        foreach ($venueIds as $venueId) {
            $venue = Venue::findById((int) $venueId);
            if (!$venue || !$venue['is_active']) continue;

            if (in_array((int) $venueId, $preservedVenueIds, true)) continue;

            $dueDate = self::calculateDueDate($eventDate, $venue['lead_time_days'], $venue['buffer_days']);

            ActionItem::create([
                'campaign_id' => $campaignId,
                'venue_id'    => (int) $venueId,
                'user_id'     => $campaign['user_id'],
                'due_date'    => $dueDate,
                'event_date'  => $eventDate,
                'status'      => 'pending',
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Calculate the submission due date.
     * Due date = Event date - lead_time_days - buffer_days
     *
     * @param string $eventDate    YYYY-MM-DD
     * @param int    $leadTimeDays Venue's required lead time
     * @param int    $bufferDays   Safety buffer days to add
     * @return string Due date in YYYY-MM-DD format
     */
    public static function calculateDueDate(string $eventDate, int $leadTimeDays, int $bufferDays = 1): string
    {
        $totalDays = $leadTimeDays + $bufferDays;
        $date = new \DateTime($eventDate);
        $date->modify("-{$totalDays} days");
        return $date->format('Y-m-d');
    }

    /**
     * Generate recurrence instances of a campaign.
     * Creates child campaigns for recurring events.
     *
     * @param array $campaign       The parent campaign
     * @param int   $maxOccurrences Maximum number of occurrences to generate
     * @return array Array of created campaign IDs
     */
    public static function generateRecurrences(array $campaign, int $maxOccurrences = 24): array
    {
        if (!$campaign['recurrence_type']) return [];

        $startDate = new \DateTime($campaign['event_date']);
        $endDate   = $campaign['recurrence_end_date']
            ? new \DateTime($campaign['recurrence_end_date'])
            : (clone $startDate)->modify('+1 year');
        $interval  = (int) ($campaign['recurrence_interval'] ?? 1);

        $targetDates = ($campaign['recurrence_type'] === 'monthly_weekday')
            ? self::monthlyWeekdayDates($startDate, $endDate, $campaign['recurrence_day_pattern'] ?? '', $maxOccurrences)
            : self::intervalDates($startDate, $endDate, $campaign['recurrence_type'], $interval, $maxOccurrences);

        $venueIds   = Campaign::getVenueIds($campaign['id']);
        $createdIds = [];

        foreach ($targetDates as $date) {
            $newCampaignId = Campaign::create([
                'user_id'         => $campaign['user_id'],
                'name'            => $campaign['name'],
                'description'     => $campaign['description'],
                'event_date'      => $date->format('Y-m-d'),
                'event_time'      => $campaign['event_time'],
                'location'        => $campaign['location'],
                'recurrence_type' => null, // child campaigns don't recurse
                'base_assets'     => $campaign['base_assets'],
            ]);

            if (!empty($venueIds)) {
                Campaign::syncVenues($newCampaignId, $venueIds);
                self::generate($newCampaignId, $venueIds);
            }

            $createdIds[] = $newCampaignId;
        }

        return $createdIds;
    }

    /**
     * Collect dates for interval-based recurrence (weekly, monthly, daily).
     * Advances past $start before collecting.
     */
    private static function intervalDates(\DateTime $start, \DateTime $end, string $type, int $interval, int $max): array
    {
        $dates   = [];
        $current = self::nextOccurrence($start, $type, $interval);
        while ($current <= $end && count($dates) < $max) {
            $dates[] = clone $current;
            $current = self::nextOccurrence($current, $type, $interval);
        }
        return $dates;
    }

    /**
     * Collect dates for monthly-by-weekday recurrence.
     * Pattern JSON: {"day":3,"weeks":[1,3]}  (day = PHP N: 1=Mon … 7=Sun)
     * Example: {"day":3,"weeks":[1,3]} = 1st and 3rd Wednesday every month.
     */
    private static function monthlyWeekdayDates(\DateTime $start, \DateTime $end, string $patternJson, int $max): array
    {
        $pattern = json_decode($patternJson, true);
        if (empty($pattern['day']) || empty($pattern['weeks'])) return [];

        $targetDay   = (int) $pattern['day'];
        $targetWeeks = $pattern['weeks'];
        sort($targetWeeks); // ascending so dates within each month come out in order

        $dates = [];
        $year  = (int) $start->format('Y');
        $month = (int) $start->format('m');

        // Advance past the parent event's month
        if (++$month > 12) { $month = 1; $year++; }

        while (count($dates) < $max) {
            if (new \DateTime(sprintf('%04d-%02d-01', $year, $month)) > $end) break;

            foreach ($targetWeeks as $week) {
                $date = self::nthWeekdayOfMonth($year, $month, (int) $week, $targetDay);
                if ($date && $date > $start && $date <= $end) {
                    $dates[] = clone $date;
                    if (count($dates) >= $max) break 2;
                }
            }

            if (++$month > 12) { $month = 1; $year++; }
        }

        return $dates;
    }

    /**
     * Return the date of the Nth occurrence of a weekday in a month.
     * $week = 1–4, $weekday = PHP N format (1=Mon … 7=Sun).
     * $week = 5 means the *last* occurrence of that weekday in the month.
     * Returns null when the occurrence doesn't exist (e.g. 5th Friday in a short month).
     */
    private static function nthWeekdayOfMonth(int $year, int $month, int $week, int $weekday): ?\DateTime
    {
        $first = new \DateTime(sprintf('%04d-%02d-01', $year, $month));

        if ($week === 5) {
            // Last occurrence: start from the last day and walk back to the target weekday.
            $lastDay  = (int) $first->format('t');
            $lastDate = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $lastDay));
            $lastN    = (int) $lastDate->format('N');
            $diff     = ($lastN - $weekday + 7) % 7;
            return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $lastDay - $diff));
        }

        $firstN = (int) $first->format('N'); // 1=Mon … 7=Sun
        $diff   = ($weekday - $firstN + 7) % 7;
        $day    = 1 + $diff + ($week - 1) * 7;

        if ($day > (int) $first->format('t')) return null;

        return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Advance a date by one recurrence interval.
     */
    private static function nextOccurrence(\DateTime $date, string $type, int $interval): \DateTime
    {
        $next = clone $date;
        match ($type) {
            'weekly'  => $next->modify("+{$interval} weeks"),
            'monthly' => $next->modify("+{$interval} months"),
            'daily'   => $next->modify("+{$interval} days"),
            default   => $next->modify("+{$interval} weeks"),
        };
        return $next;
    }

    /**
     * Get a summary of action item stats for a user.
     */
    public static function getUserStats(int $userId): array
    {
        $db = Database::getInstance();

        $overdue = $db->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_action_items WHERE (user_id = ? OR assigned_to_user_id = ?) AND status = \'pending\' AND due_date < CURDATE()',
            [$userId, $userId]
        )['cnt'] ?? 0;

        $dueToday = $db->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_action_items WHERE (user_id = ? OR assigned_to_user_id = ?) AND status = \'pending\' AND due_date = CURDATE()',
            [$userId, $userId]
        )['cnt'] ?? 0;

        $dueThisWeek = $db->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_action_items WHERE (user_id = ? OR assigned_to_user_id = ?) AND status = \'pending\' AND due_date > CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            [$userId, $userId]
        )['cnt'] ?? 0;

        $completedThisMonth = $db->fetchOne(
            'SELECT COUNT(*) as cnt FROM dp_action_items WHERE (user_id = ? OR assigned_to_user_id = ?) AND status = \'complete\' AND completed_at >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')',
            [$userId, $userId]
        )['cnt'] ?? 0;

        return [
            'overdue'             => (int) $overdue,
            'due_today'           => (int) $dueToday,
            'due_this_week'       => (int) $dueThisWeek,
            'completed_this_month'=> (int) $completedThisMonth,
        ];
    }
}
