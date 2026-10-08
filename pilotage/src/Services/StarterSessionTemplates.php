<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Seeded session templates (FR-5.2).
 *
 * The working-session shape is the seven-part weekly leadership meeting that
 * every serious operating system converges on: settle, look at the numbers,
 * look at the priorities, share news, review what was promised, solve the
 * real problems, and agree what happens next.
 *
 * Written in plain language on purpose — same trademark posture as the
 * starter playbooks (SPEC.md §12.2 item 5). The shape is common practice; the
 * branded vocabulary belongs to its authors.
 */
final class StarterSessionTemplates
{
    /** @return int The template id. */
    public static function installWorkingSession(int $tenantId): int
    {
        return self::install($tenantId, 'Working session', 'working', 90, 'The standard weekly or fortnightly meeting.', [
            ['Settle in',            5,  null,          'Everyone says one thing, personal and professional. It is not filler — it is how you find out the CFO has been up since four.'],
            ['The numbers',          5,  'metrics',     'Read them, do not discuss them. Anything off target becomes an issue, not a conversation right now.'],
            ['Priorities',           5,  'goals',       'On track or off track. One word each. Off track becomes an issue.'],
            ['Where we are',         5,  'steps',       'Current position in the process.'],
            ['Headlines',            5,  null,          'Customer and staff news, good and bad. Anything needing action becomes an issue.'],
            ['What we promised',     5,  'commitments', 'Done or not done. No explanations yet — a miss twice running becomes an issue.'],
            ['Solve the real ones', 55, 'issues',      'Pick the most important, not the easiest. Identify the actual problem, discuss it once, decide. Solving three properly beats touching ten.'],
            ['Agree what happens next', 5, null,        'Who does what by when, and what the client hears about this meeting.'],
        ]);
    }

    /** @return int The template id. */
    public static function installDiscovery(int $tenantId): int
    {
        return self::install($tenantId, 'Discovery conversation', 'discovery', 90, 'The first real meeting.', [
            ['Their story',        25, null, 'How the business got here. Let it run long — you learn more in the tangents.'],
            ['How it makes money', 20, null, 'Revenue, margin, and where cash actually goes. Ask what they wish they knew.'],
            ['What hurts',         20, null, 'The thing keeping them up. Ask twice; the second answer is usually the real one.'],
            ['How decisions get made', 10, null, 'Who really decides. Not the org chart — the truth.'],
            ['What good looks like',  10, null, 'Twelve months out, what has changed for this to have been worth it?'],
            ['Next steps',          5, null, 'What you will each do before you meet again.'],
        ]);
    }

    /**
     * @param array<int,array{0:string,1:?int,2:?string,3:?string}> $items
     */
    private static function install(int $tenantId, string $name, string $type, int $timeBox, string $description, array $items): int
    {
        $db = Database::conn();

        $db->prepare(
            'INSERT INTO pl_session_templates (tenant_id, name, description, session_type, time_box_minutes, is_system)
             VALUES (:tid, :name, :desc, :type, :box, 1)'
        )->execute(['tid' => $tenantId, 'name' => $name, 'desc' => $description, 'type' => $type, 'box' => $timeBox]);

        $templateId = (int) $db->lastInsertId();

        $insert = $db->prepare(
            'INSERT INTO pl_session_template_items (tenant_id, template_id, position, title, minutes, auto_block, prompt)
             VALUES (:tid, :tpl, :pos, :title, :mins, :block, :prompt)'
        );

        foreach ($items as $i => [$title, $minutes, $block, $prompt]) {
            $insert->execute([
                'tid'    => $tenantId,
                'tpl'    => $templateId,
                'pos'    => $i,
                'title'  => $title,
                'mins'   => $minutes,
                'block'  => $block,
                'prompt' => $prompt,
            ]);
        }

        return $templateId;
    }
}
