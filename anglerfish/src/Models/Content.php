<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * Bulk import of own content and reference corpora (SPEC §6.3).
 * All upserts, so a re-import corrects rather than duplicates.
 */
final class Content
{
    public static function import(string $kind, array $rows): array
    {
        return match ($kind) {
            'kits'    => self::kits($rows),
            'posts'   => self::posts($rows),
            'sources' => self::sources($rows),
            'kit_tools' => self::kitTools($rows),
            default   => throw new \RuntimeException("Unknown content kind '$kind'."),
        };
    }

    private static function kits(array $rows): array
    {
        $ins = $upd = 0;
        foreach ($rows as $r) {
            $existing = Database::value('SELECT id FROM af_kits WHERE number = ?',
                [(int) $r['number']]);
            if ($existing) {
                Database::run('UPDATE af_kits SET slug=?, title=?, description=? WHERE id=?',
                    [$r['slug'], $r['title'], $r['description'] ?? null, (int) $existing]);
                $upd++;
            } else {
                Database::insert('INSERT INTO af_kits (number, slug, title, description)
                                  VALUES (?, ?, ?, ?)',
                    [(int) $r['number'], $r['slug'], $r['title'], $r['description'] ?? null]);
                $ins++;
            }
        }
        return ['inserted' => $ins, 'updated' => $upd];
    }

    private static function posts(array $rows): array
    {
        $ins = $upd = $directed = 0;
        foreach ($rows as $r) {
            // Kit assignment comes from build_vault.py's mapping, which lives in
            // the Vault files rather than the drafts. Left null until the kit
            // pages are imported; a post can be filed later without re-import.
            //
            // Art direction is written by a separate pass, so most draft files
            // will not carry one. Absence has to leave those columns alone
            // rather than clearing them: the alternative is that one routine
            // re-import of the 410 undirected drafts silently destroys every
            // direction written since. Only a file that supplies a block writes
            // to them.
            $hasDirection = array_key_exists('gemini_direction', $r)
                && $r['gemini_direction'] !== null;
            $json = $hasDirection
                ? json_encode($r['gemini_direction'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : null;

            $existing = Database::value('SELECT id FROM af_posts WHERE slug = ?', [$r['slug']]);
            if ($existing) {
                if ($hasDirection) {
                    Database::run(
                        'UPDATE af_posts SET number=?, format=?, title=?, body=?,
                                          gemini_structure=?, gemini_direction=?,
                                          gemini_direction_at=NOW()
                          WHERE id=?',
                        [(int) $r['number'], $r['format'], $r['title'], $r['body'],
                         $r['gemini_structure'] ?? null, $json, (int) $existing]
                    );
                    $directed++;
                } else {
                    Database::run(
                        'UPDATE af_posts SET number=?, format=?, title=?, body=? WHERE id=?',
                        [(int) $r['number'], $r['format'], $r['title'], $r['body'],
                         (int) $existing]
                    );
                }
                $upd++;
            } else {
                Database::insert(
                    'INSERT INTO af_posts (number, slug, format, title, body, status,
                                        gemini_structure, gemini_direction,
                                        gemini_direction_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, '
                        . ($hasDirection ? 'NOW()' : 'NULL') . ')',
                    [(int) $r['number'], $r['slug'], $r['format'], $r['title'],
                     $r['body'], $r['status'] ?? 'draft',
                     $r['gemini_structure'] ?? null, $json]
                );
                $ins++;
                if ($hasDirection) {
                    $directed++;
                }
            }
        }
        return ['inserted' => $ins, 'updated' => $upd, 'directed' => $directed];
    }

    /** Vault tools land in the same artifacts store as book extractions. */
    private static function kitTools(array $rows): array
    {
        $ins = $upd = 0;
        foreach ($rows as $r) {
            $kitId = Database::value('SELECT id FROM af_kits WHERE number = ?',
                [(int) $r['kit_number']]);
            if (!$kitId) {
                continue;
            }

            // Idempotent on (kit, type): a re-import replaces that tool.
            $existing = Database::value(
                'SELECT id FROM af_artifacts WHERE kit_id = ? AND type = ?',
                [(int) $kitId, $r['type']]);
            if ($existing) {
                Database::run('DELETE FROM af_artifacts WHERE id = ?', [(int) $existing]);
                $upd++;
            } else {
                $ins++;
            }

            $artifactId = Database::insert(
                'INSERT INTO af_artifacts (book_id, kit_id, ord, type, title, intro,
                                        verbatim, review_status)
                 VALUES (NULL, ?, 0, ?, ?, ?, 0, "approved")',
                [(int) $kitId, $r['type'], mb_substr($r['title'], 0, 500), $r['intro'] ?? null]
            );

            $ord = 0;
            foreach ($r['items'] ?? [] as $it) {
                Database::run(
                    'INSERT INTO af_artifact_items (artifact_id, ord, group_label, text)
                     VALUES (?, ?, ?, ?)',
                    [$artifactId, ++$ord, $it['group_label'] ?? null, $it['text']]
                );
            }
        }
        return ['inserted' => $ins, 'updated' => $upd];
    }

    private static function sources(array $rows): array
    {
        // 38k rows across both corpora, so this runs as one prepared statement
        // in a transaction rather than a query per row.
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO af_sources (brain, path, path_hash, author, collection,
                                  content_type, topics, summary)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE author=VALUES(author), collection=VALUES(collection),
                 content_type=VALUES(content_type), topics=VALUES(topics),
                 summary=VALUES(summary)'
        );

        $n = 0;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                $st->execute([
                    $r['brain'], $r['path'], sha1($r['path']),
                    $r['author'] ?? null, $r['collection'] ?? null,
                    $r['content_type'] ?? null,
                    json_encode($r['topics'] ?? []), $r['summary'] ?? null,
                ]);
                $n++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['inserted' => $n, 'updated' => 0];
    }
}
