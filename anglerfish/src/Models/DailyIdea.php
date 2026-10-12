<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;
use Anglerfish\Services\Anthropic;

/**
 * Daily Idea (migration 002): one random Hypnosis Brain item a day, emailed
 * with a short explainer Claude writes from the item alone.
 *
 * The pool comes from the Mac ("Hypnosis Brain/export_daily.py --push"). Every
 * item is sent once before any repeats, and the pick avoids the topics of the
 * last week so seven mornings are seven different subjects.
 */
final class DailyIdea
{
    public const TO       = 'jassen.bowman@gmail.com';
    public const ZONE     = 'America/Los_Angeles';
    public const SEND_AT  = 6;               // hour, Pacific
    public const MODEL    = 'claude-sonnet-5-5';

    /** Upsert the pushed pool. Items no longer in the export go inactive. */
    public static function import(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new \RuntimeException("Cannot read $path");
        }
        $seen = [];
        $added = 0;
        while (($line = fgets($fh)) !== false) {
            $r = json_decode($line, true);
            if (!is_array($r) || empty($r['key'])) {
                continue;
            }
            $seen[] = $r['key'];
            $n = Database::run(
                'INSERT INTO af_daily_ideas (item_key, topic, section, title, body, citation, active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE topic = VALUES(topic), section = VALUES(section),
                   title = VALUES(title), body = VALUES(body), citation = VALUES(citation), active = 1',
                [$r['key'], mb_substr($r['topic'], 0, 120), mb_substr($r['section'], 0, 60),
                 mb_substr($r['title'], 0, 500), (string) $r['body'], mb_substr((string) $r['citation'], 0, 255)]);
            if ($n === 1) {
                $added++;
            }
        }
        fclose($fh);
        if (!$seen) {
            throw new \RuntimeException('Empty export; nothing changed.');
        }
        $keys = array_flip($seen);
        $retired = 0;
        foreach (Database::all('SELECT id, item_key FROM af_daily_ideas WHERE active = 1') as $row) {
            if (!isset($keys[$row['item_key']])) {
                $retired += Database::run('UPDATE af_daily_ideas SET active = 0 WHERE id = ?', [$row['id']]);
            }
        }
        return ['items' => count($seen), 'added' => $added, 'retired' => $retired];
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::ZONE)))->format('Y-m-d');
    }

    /** Due: past the send hour, Pacific, and nothing sent yet today. */
    public static function due(): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::ZONE));
        if ((int) $now->format('G') < self::SEND_AT) {
            return false;
        }
        // Three failed attempts in a day means SMTP2GO or the API is down;
        // stop spending a Claude call every run until tomorrow.
        $r = Database::one('SELECT SUM(sent) AS ok, COUNT(*) AS tries FROM af_daily_sends WHERE send_date = ?',
            [self::today()]);
        return (int) ($r['ok'] ?? 0) === 0 && (int) ($r['tries'] ?? 0) < 3;
    }

    /** A random never-sent item, off the last week's topics when possible. */
    public static function pick(): ?array
    {
        $recent = array_column(Database::all(
            'SELECT DISTINCT i.topic FROM af_daily_sends s JOIN af_daily_ideas i ON i.id = s.idea_id
             WHERE s.sent = 1 AND s.send_date > ? - INTERVAL 7 DAY', [self::today()]), 'topic');

        $base = 'FROM af_daily_ideas i WHERE i.active = 1 AND i.body <> \'\'
                 AND NOT EXISTS (SELECT 1 FROM af_daily_sends s WHERE s.idea_id = i.id AND s.sent = 1)';
        foreach ([true, false] as $avoidRecent) {
            $sql = $base;
            $params = [];
            if ($avoidRecent && $recent) {
                $sql .= ' AND i.topic NOT IN (' . implode(',', array_fill(0, count($recent), '?')) . ')';
                $params = $recent;
            }
            // Pick a topic first, then an item in it: the Practice Building file
            // alone is an eighth of the pool and would otherwise dominate.
            $topic = Database::value("SELECT i.topic $sql GROUP BY i.topic ORDER BY RAND() LIMIT 1", $params);
            if ($topic !== null && $topic !== false) {
                return Database::one("SELECT i.* $sql AND i.topic = ? ORDER BY RAND() LIMIT 1",
                    [...$params, $topic]);
            }
        }
        return null;
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM af_daily_ideas WHERE id = ?', [$id]);
    }

    public static function sends(int $limit = 60): array
    {
        return Database::all(
            'SELECT s.*, i.title, i.topic, i.section FROM af_daily_sends s
             JOIN af_daily_ideas i ON i.id = s.idea_id ORDER BY s.id DESC LIMIT ' . (int) $limit);
    }

    public static function sendFor(int $ideaId): ?array
    {
        return Database::one('SELECT * FROM af_daily_sends WHERE idea_id = ? ORDER BY id DESC LIMIT 1', [$ideaId]);
    }

    public static function counts(): array
    {
        return Database::one(
            'SELECT COUNT(*) AS pool,
                    SUM(EXISTS (SELECT 1 FROM af_daily_sends s WHERE s.idea_id = i.id AND s.sent = 1)) AS sent
             FROM af_daily_ideas i WHERE i.active = 1') ?? ['pool' => 0, 'sent' => 0];
    }

    /** Title case for an all-caps item title, for the subject line. */
    public static function niceTitle(string $title): string
    {
        $t = mb_convert_case(mb_strtolower($title), MB_CASE_TITLE);
        return preg_replace_callback('/\b(A|An|And|As|At|But|By|For|In|Is|Of|On|Or|The|To|Vs|With)\b/',
            static fn($m) => strtolower($m[1]), ucfirst($t)) ?: $title;
    }

    /** Claude's explainer: what the idea is, why it matters, how to try it. */
    public static function distill(array $idea): array
    {
        $system = <<<TXT
You write the "distilled idea" for a private daily email that teaches one idea from a hypnosis knowledge base. The reader is a working hypnotist and financial coach who wants to learn something new each morning.

Write 180 to 260 words of plain prose, no headings, no bullet points, no emojis. Open with the idea itself in one or two sentences, then explain why it works or matters, then end with one concrete way to notice it or try it this week.

Work only from the item you are given. Do not add facts, studies, numbers or quotations that are not in it. Attribute ideas to their source by name (Mike Mandel, Chris Thompson, Dolores Cannon, or the book's author) when the item makes that clear. Claims about past lives, healing, the Subconscious as an all-knowing entity, or medical outcomes are the source's own: report them as what the source teaches, never as fact. If the item is itself a caution, explain the risk plainly.
TXT;
        $user = "Topic: {$idea['topic']}\nKind: {$idea['section']}\nTitle: {$idea['title']}\n"
              . "Source: {$idea['citation']}\n\n{$idea['body']}";
        $r = Anthropic::complete($system, $user, self::MODEL, 'low');
        return ['text' => trim($r['text']), 'model' => $r['model']];
    }

    /** Distill, email and record today's idea. Returns the send row. */
    public static function send(?array $idea = null, string $to = self::TO, bool $dryRun = false): array
    {
        $idea ??= self::pick();
        if (!$idea) {
            throw new \RuntimeException('The pool is empty. Run export_daily.py --push on the Mac.');
        }
        $d = self::distill($idea);

        $date = new \DateTimeImmutable('now', new \DateTimeZone(self::ZONE));
        $subject = 'Hypnosis Brain — ' . $date->format('F j') . ': ' . self::niceTitle($idea['title']);
        $link = 'https://tools.bizorca.com' . url('/daily/' . (int) $idea['id']);

        $text = self::niceTitle($idea['title']) . "\n"
              . "Hypnosis Brain, {$idea['topic']} · " . self::niceTitle($idea['section']) . "\n\n"
              . "The distilled idea\n\n" . $d['text'] . "\n\n"
              . "From the brain\n\n" . $idea['title'] . '. ' . $idea['body']
              . ($idea['citation'] !== '' ? "\n(" . $idea['citation'] . ')' : '') . "\n\n"
              . "Read it on the idea page: $link\n";

        if ($dryRun) {
            echo "To: $to\nSubject: $subject\n\n$text";
            return [];
        }

        $id = Database::insert(
            'INSERT INTO af_daily_sends (idea_id, send_date, to_email, subject, distilled, model, sent)
             VALUES (?, ?, ?, ?, ?, ?, 0)',
            [(int) $idea['id'], $date->format('Y-m-d'), $to, mb_substr($subject, 0, 500), $d['text'], $d['model']]);
        require_once TL_PRIVATE . '/includes/mailer.php';
        $ok = tl_mail($to, $subject, $text);
        Database::run('UPDATE af_daily_sends SET sent = ? WHERE id = ?', [$ok ? 1 : 0, $id]);
        if (!$ok) {
            throw new \RuntimeException("SMTP2GO did not accept send #$id; it will retry on the next run.");
        }
        return Database::one('SELECT * FROM af_daily_sends WHERE id = ?', [$id]);
    }
}
