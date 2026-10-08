<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * The two Brain corpora (SPEC §6.3). Split deliberately:
 *
 *   index  — 38,086 rows of metadata, on the server, searched instantly here
 *   bodies — 1.2GB of text, only on the Mac, searched by a worker job
 *
 * Most questions are answered by the index, so deep search is opt-in rather
 * than the default path.
 */
final class Corpus
{
    /** Instant metadata search. @return array<int,array<string,mixed>> */
    public static function search(string $q, string $brain = 'both',
                                  ?string $type = null, int $limit = 60): array
    {
        $where = ['1'];
        $params = [];

        if ($brain !== 'both') {
            $where[] = 's.brain = ?';
            $params[] = $brain;
        }
        if ($type) {
            $where[] = 's.content_type = ?';
            $params[] = $type;
        }

        if ($q !== '') {
            // Dan Kennedy rows carry no summary at all, so a summary-only
            // fulltext search would silently return nothing for that half of
            // the corpus. Match path, collection and author as well.
            $where[] = '(MATCH(s.summary) AGAINST (? IN NATURAL LANGUAGE MODE)
                         OR s.path LIKE ? OR s.collection LIKE ? OR s.author LIKE ?)';
            $params[] = $q;
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }

        $sql = implode(' AND ', $where);
        $scoreQ = $q !== '' ? $q : '';

        return Database::all("
            SELECT s.*,
                   " . ($q !== ''
                        ? "(MATCH(s.summary) AGAINST (? IN NATURAL LANGUAGE MODE)
                            + IF(s.collection LIKE ?, 2, 0)
                            + IF(s.author LIKE ?, 2, 0))"
                        : '0') . " AS score
              FROM af_sources s
             WHERE $sql
             ORDER BY score DESC, s.collection, s.path
             LIMIT $limit",
            $q !== ''
                ? array_merge([$scoreQ, '%' . $q . '%', '%' . $q . '%'], $params)
                : $params
        );
    }

    /** @return array<int,array{content_type:string,n:int}> */
    public static function contentTypes(): array
    {
        return Database::all(
            'SELECT content_type, COUNT(*) n FROM af_sources
              WHERE content_type IS NOT NULL
              GROUP BY content_type ORDER BY n DESC LIMIT 14');
    }

    public static function counts(): array
    {
        return Database::one(
            "SELECT COUNT(*) total,
                    SUM(brain='mb') mb,
                    0 dk,   -- one corpus since the fold; kept so callers don't break
                    COUNT(DISTINCT collection) collections
               FROM af_sources") ?: [];
    }

    /** Queue a body search, reusing a recent identical one. */
    public static function queueDeep(string $q, string $brain): int
    {
        $recent = Database::one(
            "SELECT id FROM af_corpus_searches
              WHERE query = ? AND brain = ?
                AND (status IN ('queued','running')
                     OR (status='done' AND finished_at > DATE_SUB(NOW(), INTERVAL 1 DAY)))
              ORDER BY id DESC LIMIT 1",
            [$q, $brain]);
        if ($recent) {
            return (int) $recent['id'];
        }

        $id = Database::insert(
            'INSERT INTO af_corpus_searches (query, brain) VALUES (?, ?)', [$q, $brain]);
        Job::enqueueUnique('search_corpus', 'corpus_search', $id,
            ['search_id' => $id, 'query' => $q, 'brain' => $brain], 1);
        return $id;
    }

    public static function deep(int $id): ?array
    {
        $row = Database::one('SELECT * FROM af_corpus_searches WHERE id = ?', [$id]);
        if ($row && $row['results']) {
            $row['results'] = json_decode((string) $row['results'], true) ?: [];
        }
        return $row;
    }

    public static function applyDeep(int $id, array $data): array
    {
        Database::run(
            "UPDATE af_corpus_searches
                SET status='done', hits=?, files=?, results=?, finished_at=NOW()
              WHERE id=?",
            [(int) ($data['hits'] ?? 0), (int) ($data['files'] ?? 0),
             json_encode($data['results'] ?? []), $id]
        );
        return ['hits' => (int) ($data['hits'] ?? 0), 'files' => (int) ($data['files'] ?? 0)];
    }
}
