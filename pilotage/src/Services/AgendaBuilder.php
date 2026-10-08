<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Core\Database;

/**
 * Auto-generated agenda blocks (FR-5.3).
 *
 * When a session opens, the runner injects current state into the agenda —
 * commitments due or overdue since last time, metrics off target, goals off
 * track, open issues, playbook steps in flight. The coach never assembles this
 * by hand, which is the difference between a meeting that reviews reality and
 * a meeting that reviews whatever anyone remembers.
 *
 * Blocks are supplied by whichever module owns the data, registered here the
 * same way permission qualifiers are registered with Policy. A block with no
 * provider renders as an empty section with an honest note rather than
 * disappearing — a coach should be able to see that the scorecard block exists
 * and simply has nothing in it yet.
 *
 * Providers owed: commitments (M6), metrics and goals and issues (M9).
 * 'steps' ships now because M4 exists.
 */
final class AgendaBuilder
{
    /** @var array<string, callable(int, array): array> */
    private static array $providers = [];

    /**
     * @param callable(int $tenantId, array $session): array{items: array<int,string>, note: ?string} $provider
     */
    public static function registerProvider(string $block, callable $provider): void
    {
        self::$providers[$block] = $provider;
    }

    public static function resetProviders(): void
    {
        self::$providers = [];
    }

    /** @return string[] Blocks referenced by templates that have no provider yet. */
    public static function unresolvedBlocks(int $tenantId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT DISTINCT auto_block FROM pl_session_template_items
             WHERE tenant_id = :tid AND auto_block IS NOT NULL'
        );
        $stmt->execute(['tid' => $tenantId]);

        $referenced = array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);

        return array_values(array_diff($referenced, array_keys(self::$providers)));
    }

    /**
     * Fill one agenda item's auto block.
     *
     * @param array<string,mixed> $session
     * @return array{items: array<int,string>, note: ?string}
     */
    public static function build(string $block, int $tenantId, array $session): array
    {
        if (!isset(self::$providers[$block])) {
            return [
                'items' => [],
                'note'  => 'Nothing here yet — this part of Pilotage has not shipped.',
            ];
        }

        $result = (self::$providers[$block])($tenantId, $session);

        return [
            'items' => $result['items'] ?? [],
            'note'  => $result['note'] ?? null,
        ];
    }

    /**
     * Register what M4 can already answer: which playbook steps are in flight
     * for this engagement. The natural first agenda item in most working
     * sessions is "where are we in the process".
     */
    public static function registerPlaybookProvider(): void
    {
        self::registerProvider('steps', static function (int $tenantId, array $session): array {
            $stmt = Database::conn()->prepare(
                "SELECT s.title, s.status
                 FROM pl_engagement_steps s
                 JOIN pl_engagement_playbooks ep ON ep.id = s.engagement_playbook_id
                 WHERE s.tenant_id = :tid AND ep.engagement_id = :eid
                   AND s.status IN ('available','in_progress')
                 ORDER BY s.position ASC LIMIT 10"
            );
            $stmt->execute(['tid' => $tenantId, 'eid' => (int) $session['engagement_id']]);

            $items = [];
            foreach ($stmt->fetchAll() as $row) {
                $items[] = $row['title'] . ($row['status'] === 'in_progress' ? ' (in progress)' : '');
            }

            return [
                'items' => $items,
                'note'  => $items === [] ? 'No steps open right now.' : null,
            ];
        });
    }
}
