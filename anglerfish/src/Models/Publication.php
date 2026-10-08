<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * A masthead, and everything that differs between one and another.
 *
 * This app served exactly one publication until Hypnologue, and the cost of
 * that assumption showed up as migration 016: a Hypnologue graphic rendered
 * with bizorca's copyright in the footer, because `rightsLine()` took no
 * argument and there was nowhere for the answer to live. That fixed the footer.
 * This class is the rest of the answer — voice, model, source material, and
 * evidence standard all belong to the publication rather than to the app.
 *
 * What is deliberately NOT here: a second install. The two publications share
 * the library, the extraction pipeline, the image pipeline and the job queue.
 * Splitting them would mean ingesting every book twice.
 */
final class Publication
{
    public const BIZORCA    = 'bizorca';
    public const HYPNOLOGUE = 'hypnologue';

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string,mixed>|null */
    public static function find(string $pubkey): ?array
    {
        return self::all()[$pubkey] ?? null;
    }

    /** @return array<string,mixed>|null */
    public static function byId(int $id): ?array
    {
        foreach (self::all() as $p) {
            if ((int) $p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    /**
     * All publications, keyed by pubkey.
     *
     * Cached per request. There are two rows and every compose, render and
     * screen wants one of them; re-querying is pure noise.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = Database::all(
            'SELECT * FROM af_publications WHERE active = 1 ORDER BY id'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['pubkey']] = $r;
        }
        return self::$cache = $out;
    }

    /**
     * Resolve whatever the caller has into a publication row.
     *
     * Accepts a pubkey, a numeric id, a row, or nothing. Nothing means Bizorca,
     * because every pre-existing caller predates publications and means Bizorca
     * — that is the same reasoning behind `publication_id NOT NULL DEFAULT 1`.
     *
     * @param  string|int|array<string,mixed>|null $ref
     * @return array<string,mixed>
     */
    public static function resolve(string|int|array|null $ref): array
    {
        if (is_array($ref) && isset($ref['pubkey'])) {
            return $ref;
        }
        if (is_int($ref) || (is_string($ref) && ctype_digit($ref))) {
            $row = self::byId((int) $ref);
            if ($row) {
                return $row;
            }
        }
        if (is_string($ref) && $ref !== '') {
            $row = self::find($ref);
            if ($row) {
                return $row;
            }
        }
        $default = self::find(self::BIZORCA);
        if (!$default) {
            throw new \RuntimeException(
                'No bizorca publication row — migration 017 has not run.'
            );
        }
        return $default;
    }

    /**
     * The model this publication composes with, or null for the app default.
     *
     * Exact ids only. A family name ("opus", "fable") is not a model and the
     * API rejects it, so a typo here fails loudly on the first call rather than
     * quietly composing on the wrong thing.
     */
    public static function model(array $pub): ?string
    {
        $m = trim((string) ($pub['model'] ?? ''));
        return $m === '' ? null : $m;
    }

    /** output_config.effort, or null to send none (the API reads that as high). */
    public static function effort(array $pub): ?string
    {
        $e = trim((string) ($pub['effort'] ?? ''));
        return in_array($e, ['low', 'medium', 'high', 'xhigh', 'max'], true) ? $e : null;
    }

    public static function requiresCitation(array $pub): bool
    {
        return (int) ($pub['citation_required'] ?? 0) === 1;
    }

    public static function citationMin(array $pub): int
    {
        return max(1, (int) ($pub['citation_min'] ?? 1));
    }

    /**
     * Counts for the dashboard: how much of each thing belongs to each masthead.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function summary(): array
    {
        return Database::all(
            'SELECT p.id, p.pubkey, p.name, p.model, p.effort, p.citation_required,
                    (SELECT COUNT(*) FROM af_posts        x WHERE x.publication_id = p.id) AS posts,
                    (SELECT COUNT(*) FROM af_compositions c WHERE c.publication_id = p.id) AS compositions,
                    (SELECT COUNT(*) FROM af_publication_scope s
                       WHERE s.publication_id = p.id AND s.subject_type = "book")    AS books,
                    (SELECT COUNT(*) FROM af_publication_scope s
                       WHERE s.publication_id = p.id AND s.subject_type = "concept") AS concepts
               FROM af_publications p
              WHERE p.active = 1
              ORDER BY p.id'
        );
    }

    /**
     * The books in scope for a publication, most relevant first.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function books(int $publicationId, int $limit = 100): array
    {
        return Database::all(
            'SELECT b.id, b.slug, b.title, s.relevance, s.source, s.note
               FROM af_publication_scope s
               JOIN af_books b ON b.id = s.subject_id
              WHERE s.publication_id = ? AND s.subject_type = "book"
              ORDER BY s.relevance DESC, b.title
              LIMIT ' . (int) $limit,
            [$publicationId]
        );
    }

    /**
     * Put a subject in (or out of) a publication's scope.
     *
     * Manual rows win: the classifier writes `auto` and deletes only its own
     * rows on a re-run, so an operator decision survives every reclassification.
     * Same contract module_map uses.
     */
    public static function scope(
        int $publicationId,
        string $subjectType,
        int $subjectId,
        float $relevance = 0.5,
        string $source = 'auto',
        ?string $note = null
    ): void {
        Database::run(
            'INSERT INTO af_publication_scope
                 (publication_id, subject_type, subject_id, relevance, source, note)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 relevance = IF(source = "manual" AND VALUES(source) = "auto",
                                relevance, VALUES(relevance)),
                 note      = IF(source = "manual" AND VALUES(source) = "auto",
                                note, VALUES(note)),
                 source    = IF(source = "manual", "manual", VALUES(source))',
            [$publicationId, $subjectType, $subjectId, $relevance, $source, $note]
        );
    }

    /** Clear this classifier's own rows for a publication. Never touches manual. */
    public static function clearAuto(int $publicationId, ?string $subjectType = null): int
    {
        $sql = 'DELETE FROM af_publication_scope
                 WHERE publication_id = ? AND source = "auto"';
        $params = [$publicationId];
        if ($subjectType !== null) {
            $sql .= ' AND subject_type = ?';
            $params[] = $subjectType;
        }
        return Database::run($sql, $params);
    }

    /** Is this subject in scope for this publication? */
    public static function inScope(int $publicationId, string $subjectType, int $subjectId): bool
    {
        return (bool) Database::value(
            'SELECT 1 FROM af_publication_scope
              WHERE publication_id = ? AND subject_type = ? AND subject_id = ?',
            [$publicationId, $subjectType, $subjectId]
        );
    }
}
