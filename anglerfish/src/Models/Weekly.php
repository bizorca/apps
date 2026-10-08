<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * The weekly post format and the twelve-module curriculum behind it.
 *
 * One shape every week — trap, diagnostic, protocol, Go! — slotted against a
 * curriculum module and shipping one freely downloadable PDF deliverable. This
 * sits alongside the six rotating formats rather than replacing them: 410
 * drafts carry those values and the compose flow still offers them.
 *
 * What this class owns is the part the six formats never had — a module, a
 * running sequence number, and a deliverable chosen from four fixed containers.
 */
final class Weekly
{
    /**
     * The four containers, reused relentlessly.
     *
     * Deliberately a closed set. The weekly deliverable's central discipline is
     * that a container is chosen, never designed: if a post seems to need a new
     * page layout, the content gets reshaped to fit one of these instead. A
     * free-text field here would quietly reintroduce a bespoke handout a week.
     */
    public const CONTAINERS = [
        'test' => [
            'label' => 'The test',
            'hint'  => 'A scored self-assessment producing a number. Over-weight this one — '
                     . 'a reader holding a number about their own practice comes back to '
                     . 'measure it again.',
        ],
        'checklist' => [
            'label' => 'The checklist',
            'hint'  => 'Sequential, for a task done repeatedly.',
        ],
        'worksheet' => [
            'label' => 'The worksheet',
            'hint'  => 'Prompts with blanks, for ideation or decisions.',
        ],
        'role_document' => [
            'label' => 'The role document',
            'hint'  => 'E-Myth position contract: role name, accountabilities, results '
                     . 'expected, how performance is measured. The most differentiating '
                     . 'of the four.',
        ],
    ];

    /** The three movements, in order, as the compose form describes them. */
    public const MOVEMENTS = [
        'The Trap'       => 'What the reader is doing wrong. Cold open on the claim, one vivid '
                          . 'scenario, then the turn.',
        'The Diagnostic' => 'The load-bearing movement. The reader applies something to their own '
                          . 'practice and arrives at the diagnosis themselves.',
        'The Protocol'   => 'Numbered, sequential, literal. Written out in full in the post body, '
                          . 'not held back for the PDF.',
        'Go!'            => 'Warmer and more playful than the body. Repeat the one action, name '
                          . 'the technique, mention the download once.',
    ];

    /** @return array<int,array> The twelve, in dependency order. */
    public static function modules(): array
    {
        return Database::all('SELECT * FROM af_modules ORDER BY id');
    }

    public static function module(int $id): ?array
    {
        return Database::one('SELECT * FROM af_modules WHERE id = ?', [$id]);
    }

    /**
     * The next deliverable number.
     *
     * Counts weekly posts only. posts.number counts every draft ever made,
     * including the 410 daily-format drafts that predate this and will never
     * carry a deliverable, so printing that on a worksheet would start the
     * series at 411.
     */
    public static function nextSequence(): int
    {
        return 1 + (int) Database::value(
            "SELECT COALESCE(MAX(sequence_number), 0) FROM af_posts WHERE format = 'weekly'"
        );
    }

    /**
     * Weekly posts in publish order.
     *
     * Module order, then sequence — the order they compile into the workbook,
     * which is not the order they were written in.
     */
    public static function posts(): array
    {
        return Database::all(
            "SELECT p.id, p.slug, p.title, p.status, p.module_id, p.sequence_number,
                    p.deliverable, p.substack_url, p.published_at,
                    m.title AS module_title, m.cohort,
                    (SELECT s.id FROM af_assets s
                      WHERE s.subject_type = 'post' AND s.subject_id = p.id
                        AND s.target = 'weekly_deliverable' AND s.status = 'ready'
                      ORDER BY s.version DESC LIMIT 1) AS asset_id
               FROM af_posts p
          LEFT JOIN af_modules m ON m.id = p.module_id
              WHERE p.format = 'weekly'
           ORDER BY p.module_id, p.sequence_number, p.id"
        );
    }

    /** How many weekly posts sit against each module, for the coverage strip. */
    public static function coverage(): array
    {
        $rows = Database::all(
            "SELECT module_id, COUNT(*) n FROM af_posts
              WHERE format = 'weekly' AND module_id IS NOT NULL
              GROUP BY module_id"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['module_id']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Library material already mapped to a module, as compose candidates.
     *
     * Returns nothing until the mapping pass has run, which is the honest
     * state — an empty list says "map the library first", where a silent
     * fallback to unmapped material would look like the mapping was done.
     */
    public static function sourcesForModule(int $moduleId, int $limit = 40): array
    {
        return Database::all(
            "SELECT mm.subject_type, mm.subject_id, mm.ord, mm.confidence, mm.rationale,
                    COALESCE(c.title, ch.title, a.title, cl.title) AS title,
                    COALESCE(cb.title, chb.title, ab.title, k.title, cl.collection, '—') AS meta
               FROM af_module_map mm
          LEFT JOIN af_concepts  c  ON mm.subject_type = 'concept'  AND c.id  = mm.subject_id
          LEFT JOIN af_books     cb ON cb.id = c.book_id
          LEFT JOIN af_chapters  ch ON mm.subject_type = 'chapter'  AND ch.id = mm.subject_id
          LEFT JOIN af_books     chb ON chb.id = ch.book_id
          LEFT JOIN af_artifacts a  ON mm.subject_type = 'artifact' AND a.id  = mm.subject_id
          LEFT JOIN af_books     ab ON ab.id = a.book_id
          LEFT JOIN af_kits      k  ON k.id  = a.kit_id
          LEFT JOIN af_clippings cl ON mm.subject_type = 'clipping' AND cl.id = mm.subject_id
              WHERE mm.module_id = ? AND mm.subject_type <> 'post'
             HAVING title IS NOT NULL
           ORDER BY mm.ord, mm.confidence DESC
              LIMIT " . (int) $limit,
            [$moduleId]
        );
    }

    /** Has the mapping pass run at all? Drives the empty state on the page. */
    public static function mappedCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM af_module_map');
    }
}
