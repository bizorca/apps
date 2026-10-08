<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * The video production queue: 100 bite-size personal-finance videos.
 *
 * Seeded by migration 019 from the bite-size curriculum. Scripts are written in a
 * Claude Code session from the Personal Finance Brain's trusted sources and filed
 * with `cli.php video-import`; nothing here calls a model. Everything after
 * "scripted" is Jassen moving a video along by hand.
 */
final class Video
{
    /** Pipeline order, with the label shown on screen. */
    public const STATUSES = [
        'topic'     => 'Topic',
        'scripted'  => 'Scripted',
        'approved'  => 'Approved',
        'recorded'  => 'Recorded',
        'edited'    => 'Edited',
        'published' => 'Published',
        'skipped'   => 'Skipped',
    ];

    public static function all(string $status = '', int $part = 0, string $q = ''): array
    {
        $where = ['1'];
        $params = [];
        if (isset(self::STATUSES[$status])) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($part > 0) {
            $where[] = 'part_no = ?';
            $params[] = $part;
        }
        if ($q !== '') {
            $where[] = '(topic LIKE ? OR title LIKE ? OR script LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        return Database::all(
            'SELECT id, number, part_no, part_title, topic, title, status, words, runtime_min,
                    video_url, published_at, scripted_at
               FROM af_video_scripts WHERE ' . implode(' AND ', $where) . ' ORDER BY number',
            $params);
    }

    /** Count per status, in pipeline order, zeros included. */
    public static function counts(): array
    {
        $rows = Database::all('SELECT status, COUNT(*) n FROM af_video_scripts GROUP BY status');
        $out = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    public static function parts(): array
    {
        return Database::all(
            'SELECT part_no, part_title, COUNT(*) n,
                    SUM(status NOT IN ("topic","skipped")) scripted
               FROM af_video_scripts GROUP BY part_no, part_title ORDER BY part_no');
    }

    public static function find(int $number): ?array
    {
        return Database::one('SELECT * FROM af_video_scripts WHERE number = ?', [$number]);
    }

    /** Neighbours for prev/next links. */
    public static function neighbours(int $number): array
    {
        return [
            'prev' => Database::one('SELECT number, title, topic FROM af_video_scripts
                                      WHERE number < ? ORDER BY number DESC LIMIT 1', [$number]),
            'next' => Database::one('SELECT number, title, topic FROM af_video_scripts
                                      WHERE number > ? ORDER BY number LIMIT 1', [$number]),
        ];
    }

    /** The fields Jassen edits on the screen. */
    public static function updateTracking(int $number, array $in): void
    {
        $status = (string) ($in['status'] ?? '');
        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException('Unknown status.');
        }
        $url = trim((string) ($in['video_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('The video link must start with http:// or https://.');
        }
        $date = trim((string) ($in['published_at'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Published date must be YYYY-MM-DD.');
        }
        Database::run(
            'UPDATE af_video_scripts SET status = ?, video_url = ?, published_at = ?, my_notes = ?
              WHERE number = ?',
            [$status, $url ?: null, $date ?: null, trim((string) ($in['my_notes'] ?? '')) ?: null, $number]);
    }

    /**
     * File a script written in Claude Code. Overwrites the script fields; leaves
     * Jassen's tracking (status past "scripted", link, date, his notes) alone, so a
     * rewrite pushed after recording doesn't knock a video back to the start.
     */
    public static function import(int $number, array $f): void
    {
        $row = self::find($number);
        if (!$row) {
            throw new \InvalidArgumentException("No video #{$number} in the queue.");
        }
        $status = $row['status'] === 'topic' ? 'scripted' : $row['status'];
        Database::run(
            'UPDATE af_video_scripts
                SET title = ?, script = ?, on_screen = ?, sources = ?, notes = ?, words = ?,
                    runtime_min = ?, model = ?, scripted_at = NOW(), status = ?
              WHERE number = ?',
            [$f['title'], $f['script'], $f['on_screen'] ?: null, $f['sources'] ?: null,
             $f['notes'] ?: null, $f['words'], $f['runtime'], $f['model'], $status, $number]);
    }

    /** Teleprompter text: the spoken script with stage directions removed. */
    public static function spoken(string $script): string
    {
        $t = preg_replace('/^\s*\[(on screen|cut to|b-roll|pause)[^\]]*\]\s*$/im', '', $script);
        return trim(preg_replace("/\n{3,}/", "\n\n", (string) $t));
    }

    /** Markers that must be dealt with before recording. */
    public static function markers(string $text): array
    {
        preg_match_all('/\[(UPDATE|JASSEN STORY|CHECK)[^\]]*\]/', $text, $m);
        return array_values(array_unique($m[0]));
    }
}
