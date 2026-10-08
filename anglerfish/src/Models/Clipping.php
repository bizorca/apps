<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * A saved excerpt from the corpus.
 *
 * The corpus is 38,086 .txt files searched by grep, so a hit is a snippet
 * inside a file rather than a row anywhere — which is why it could not reach
 * the composer before. A clipping promotes one into a real record: the text,
 * where it came from, and the search that surfaced it.
 *
 * `verbatim` defaults to 1 and is not a judgement call. These are transcribed
 * excerpts of other people's copyrighted writing, so prompt assembly always
 * tells the composer to transform rather than reproduce (SPEC §11.2).
 */
final class Clipping
{
    /** Enough to be usable as source material, short enough to stay an excerpt. */
    private const MAX_BODY = 4000;

    public static function save(array $in): int
    {
        $path = trim((string) ($in['path'] ?? ''));
        $body = trim((string) ($in['body'] ?? ''));
        if ($path === '' || $body === '') {
            throw new \InvalidArgumentException('A clipping needs a path and a body.');
        }

        // The index knows the author and collection for this file; look them up
        // rather than trusting anything posted from the browser.
        $src = Database::one(
            'SELECT author, collection FROM af_sources WHERE brain=? AND path_hash=?',
            [$in['brain'], sha1($path)]
        ) ?: [];

        return Database::insert(
            'INSERT INTO af_clippings (brain, path, path_hash, title, body, note,
                                    author, collection, line_ref, query)
             VALUES (?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?, NULLIF(?, ""), NULLIF(?, ""))',
            [
                $in['brain'],
                $path,
                sha1($path),
                mb_substr(trim((string) ($in['title'] ?? '')) ?: basename($path), 0, 500),
                mb_substr($body, 0, self::MAX_BODY),
                mb_substr((string) ($in['note'] ?? ''), 0, 1000),
                $src['author'] ?? null,
                $src['collection'] ?? null,
                mb_substr((string) ($in['line_ref'] ?? ''), 0, 60),
                mb_substr((string) ($in['query'] ?? ''), 0, 255),
            ]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        return Database::all(
            'SELECT id, brain, title, author, collection, note, query, created_at,
                    CHAR_LENGTH(body) AS chars
               FROM af_clippings ORDER BY id DESC LIMIT ' . max(1, $limit)
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM af_clippings WHERE id = ?', [$id]);
    }
}
