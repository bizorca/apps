<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;
use Anglerfish\Services\ImagePrompt;
use Anglerfish\Services\Resize;
use Anglerfish\Services\ServerJobs;

/**
 * Daily Idea (migrations 002-003): one Hypnosis Brain item a morning, emailed
 * with an explainer and an infographic plate.
 *
 * The roster (af_daily_sends) has one row per date. Nothing here calls Claude:
 * the explainer and the art direction are written ahead of time in Claude Code
 * on the subscription, the same economics as weekly posts and video scripts.
 *
 *   daily-reserve   picks ideas for the next open dates      -> reserved
 *   daily-queue     imports the written file for a date      -> queued
 *   daily-send      cron: draws the plate (Gemini, the normal ImagePrompt
 *                   path), then at 6am Pacific emails today's row -> sent
 *
 * Every item is used once before anything repeats, and a week of picks stays
 * on different topics. A morning with nothing queued still gets an email: the
 * raw brain item, with a note that the queue ran dry.
 */
final class DailyIdea
{
    public const TO          = 'jassen.bowman@gmail.com';
    public const ZONE        = 'America/Los_Angeles';
    public const SEND_AT     = 6;     // hour, Pacific
    public const IMAGE_MODEL = 'gemini-3-pro-image';
    public const ATTRIBUTION = 'hypnologue';
    private const MAX_TRIES  = 3;

    public const STATUSES = [
        'reserved' => 'Reserved',
        'queued'   => 'Queued',
        'sent'     => 'Sent',
        'failed'   => 'Failed',
    ];

    // ── the pool ────────────────────────────────────────────────────────────

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
        return self::now()->format('Y-m-d');
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::ZONE));
    }

    /**
     * A random unused item for $date, off the topics within a week either side
     * of it (sent or reserved) when possible.
     */
    public static function pick(string $date): ?array
    {
        $recent = array_column(Database::all(
            'SELECT DISTINCT i.topic FROM af_daily_sends s JOIN af_daily_ideas i ON i.id = s.idea_id
             WHERE s.send_date BETWEEN ? - INTERVAL 6 DAY AND ? + INTERVAL 6 DAY', [$date, $date]), 'topic');

        $base = 'FROM af_daily_ideas i WHERE i.active = 1 AND i.body <> \'\'
                 AND NOT EXISTS (SELECT 1 FROM af_daily_sends s WHERE s.idea_id = i.id)';
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

    // ── the roster ──────────────────────────────────────────────────────────

    /**
     * Reserve ideas for the next $days dates that have no row, starting today
     * if today's has not gone out. Returns every reserved-but-unwritten row in
     * that window (new or left over), with its idea, for the Mac to write.
     */
    public static function reserve(int $days): array
    {
        $start = self::now();
        if ((int) $start->format('G') >= self::SEND_AT) {
            $start = $start->modify('+1 day');   // today's slot has already been used
        }
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $start->modify("+$i day")->format('Y-m-d');
        }
        foreach ($dates as $date) {
            if (Database::value('SELECT 1 FROM af_daily_sends WHERE send_date = ?', [$date])) {
                continue;
            }
            $idea = self::pick($date);
            if (!$idea) {
                break;
            }
            Database::insert(
                "INSERT INTO af_daily_sends (idea_id, send_date, status, to_email) VALUES (?, ?, 'reserved', ?)",
                [(int) $idea['id'], $date, self::TO]);
        }
        return Database::all(
            "SELECT s.id AS send_id, s.send_date, i.id AS idea_id, i.topic, i.section, i.title, i.body, i.citation
               FROM af_daily_sends s JOIN af_daily_ideas i ON i.id = s.idea_id
              WHERE s.status = 'reserved' AND s.send_date BETWEEN ? AND ?
              ORDER BY s.send_date", [$dates[0], end($dates)]);
    }

    /**
     * Import one written day: front matter (send_date, idea_id), the explainer,
     * then ---GEMINI--- and the v3 art direction JSON. Re-importing a queued
     * row replaces its text and clears its plate so it is drawn again.
     */
    public static function queue(string $raw): array
    {
        if (!preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
            throw new \InvalidArgumentException('No front matter.');
        }
        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $kv)) {
                $meta[$kv[1]] = trim($kv[2]);
            }
        }
        [$body, $json] = array_pad(explode('---GEMINI---', $m[2], 2), 2, '');
        // Everything from a SOURCE block down is the brief, not the email.
        $body = trim(preg_split('/^---SOURCE---$/m', $body)[0]);
        $direction = json_decode(trim($json), true);

        $row = Database::one('SELECT * FROM af_daily_sends WHERE send_date = ?', [$meta['send_date'] ?? '']);
        if (!$row) {
            throw new \InvalidArgumentException("No roster row for {$meta['send_date']}; reserve it first.");
        }
        if ((int) $row['idea_id'] !== (int) ($meta['idea_id'] ?? 0)) {
            throw new \InvalidArgumentException("{$meta['send_date']} is reserved for idea {$row['idea_id']}, not {$meta['idea_id']}.");
        }
        if ($row['status'] === 'sent') {
            throw new \InvalidArgumentException("{$meta['send_date']} has already been sent.");
        }
        $words = str_word_count(strip_tags($body));
        if ($words < 120 || $words > 400) {
            throw new \InvalidArgumentException("{$meta['send_date']}: explainer is $words words (120-400).");
        }
        if (!is_array($direction) || empty($direction['headline']) || empty($direction['sections'])) {
            throw new \InvalidArgumentException("{$meta['send_date']}: no usable art direction after ---GEMINI---.");
        }

        Database::run(
            "UPDATE af_daily_sends SET status = 'queued', distilled = ?, direction = ?, model = ?,
                    image_path = NULL, image_prompt = NULL, image_error = NULL, image_tries = 0
              WHERE id = ?",
            [$body, json_encode($direction, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
             mb_substr((string) ($meta['model'] ?? 'claude-code'), 0, 60), (int) $row['id']]);
        return ['date' => $row['send_date'], 'words' => $words];
    }

    public static function roster(string $status = '', int $limit = 200): array
    {
        $where = $status !== '' && isset(self::STATUSES[$status]) ? 'WHERE s.status = ?' : '';
        return Database::all(
            "SELECT s.*, i.title, i.topic, i.section FROM af_daily_sends s
               JOIN af_daily_ideas i ON i.id = s.idea_id $where
              ORDER BY s.send_date DESC LIMIT " . (int) $limit, $where ? [$status] : []);
    }

    public static function counts(): array
    {
        $c = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach (Database::all('SELECT status, COUNT(*) n FROM af_daily_sends GROUP BY status') as $r) {
            $c[$r['status']] = (int) $r['n'];
        }
        $c['pool'] = (int) Database::value('SELECT COUNT(*) FROM af_daily_ideas WHERE active = 1');
        $c['ahead'] = (int) Database::value(
            "SELECT COUNT(*) FROM af_daily_sends WHERE status = 'queued' AND send_date >= ?", [self::today()]);
        return $c;
    }

    public static function sendRow(int $id): ?array
    {
        return Database::one(
            'SELECT s.*, i.title, i.topic, i.section, i.body, i.citation FROM af_daily_sends s
               JOIN af_daily_ideas i ON i.id = s.idea_id WHERE s.id = ?', [$id]);
    }

    public static function sendFor(int $ideaId): ?array
    {
        return Database::one('SELECT * FROM af_daily_sends WHERE idea_id = ? ORDER BY id DESC LIMIT 1', [$ideaId]);
    }

    // ── the plate ───────────────────────────────────────────────────────────

    /**
     * Draw the plate for the nearest queued row that lacks one, at most one per
     * call (~40s on Gemini). Same prompt builder and storage as every other
     * Anglerfish image; the file is kept on the roster row rather than in
     * af_assets, whose review queue is for library chapters.
     */
    public static function drawNext(): ?string
    {
        $row = Database::one(
            "SELECT s.*, i.citation FROM af_daily_sends s JOIN af_daily_ideas i ON i.id = s.idea_id
              WHERE s.status = 'queued' AND s.image_path IS NULL AND s.image_tries < ?
                AND s.send_date BETWEEN ? AND ? + INTERVAL 3 DAY
              ORDER BY s.send_date LIMIT 1", [self::MAX_TRIES, self::today(), self::today()]);
        if (!$row) {
            return null;
        }
        return self::draw($row);
    }

    public static function draw(array $row): string
    {
        $direction = json_decode((string) $row['direction'], true) ?: [];
        $source = 'Hypnosis Brain' . ($row['citation'] !== '' ? ' · ' . $row['citation'] : '');
        $prompt = ImagePrompt::fromDirection($direction, 'infographic', $source,
            ImagePrompt::ATTRIBUTIONS[self::ATTRIBUTION]['line'] ?? null);
        Database::run('UPDATE af_daily_sends SET image_tries = image_tries + 1, image_prompt = ? WHERE id = ?',
            [$prompt, (int) $row['id']]);
        try {
            $r = ServerJobs::run('generate_image', [
                'prompt' => $prompt, 'model' => self::IMAGE_MODEL, 'subject_type' => 'daily',
                'subject_id' => (int) $row['id'],
            ]);
        } catch (\Throwable $e) {
            Database::run('UPDATE af_daily_sends SET image_error = ? WHERE id = ?',
                [mb_substr($e->getMessage(), 0, 500), (int) $row['id']]);
            return "plate for {$row['send_date']} failed: " . $e->getMessage();
        }
        Database::run('UPDATE af_daily_sends SET image_path = ?, image_error = NULL WHERE id = ?',
            [$r['web_path'], (int) $row['id']]);
        return "drew the plate for {$row['send_date']}: {$r['web_path']}";
    }

    // ── the send ────────────────────────────────────────────────────────────

    /** Due: past the send hour, Pacific, and today's row neither sent nor given up on. */
    public static function due(): bool
    {
        if ((int) self::now()->format('G') < self::SEND_AT) {
            return false;
        }
        $row = Database::one('SELECT status, send_tries FROM af_daily_sends WHERE send_date = ?', [self::today()]);
        return !$row || (!in_array($row['status'], ['sent', 'failed'], true) && (int) $row['send_tries'] < self::MAX_TRIES);
    }

    /**
     * Send today's row. A reserved or missing row goes out as the raw item with
     * a nudge to refill the queue; nothing here writes the explainer itself.
     */
    public static function sendToday(bool $dryRun = false, ?string $to = null): string
    {
        $date = self::today();
        $row = Database::one('SELECT * FROM af_daily_sends WHERE send_date = ?', [$date]);
        if (!$row) {
            $idea = self::pick($date);
            if (!$idea) {
                throw new \RuntimeException('The pool is empty. Run export_daily.py --push on the Mac.');
            }
            if ($dryRun) {
                $row = ['id' => 0, 'idea_id' => $idea['id'], 'status' => 'reserved', 'send_date' => $date];
            } else {
                Database::insert(
                    "INSERT INTO af_daily_sends (idea_id, send_date, status, to_email) VALUES (?, ?, 'reserved', ?)",
                    [(int) $idea['id'], $date, self::TO]);
                $row = Database::one('SELECT * FROM af_daily_sends WHERE send_date = ?', [$date]);
            }
        }
        // Last chance at a plate if the cron runs before 6 did not get one.
        if (!$dryRun && $row['status'] === 'queued' && !$row['image_path'] && (int) $row['image_tries'] < self::MAX_TRIES) {
            self::draw(self::sendRow((int) $row['id']));
            $row = Database::one('SELECT * FROM af_daily_sends WHERE id = ?', [(int) $row['id']]);
        }
        return self::deliver($row, $dryRun, $to);
    }

    /** Email one roster row (any status but sent), or print it with $dryRun. */
    public static function deliver(array $row, bool $dryRun = false, ?string $to = null): string
    {
        $to ??= self::TO;
        $idea = self::find((int) $row['idea_id']);
        $queued = ($row['status'] ?? '') === 'queued' && trim((string) ($row['distilled'] ?? '')) !== '';
        $date = new \DateTimeImmutable($row['send_date'], new \DateTimeZone(self::ZONE));
        $title = self::niceTitle($idea['title']);
        $subject = 'Hypnosis Brain — ' . $date->format('F j') . ': ' . $title;
        $link = 'https://tools.bizorca.com' . url('/daily/' . (int) $idea['id']);
        $image = !empty($row['image_path']) ? af_file($row['image_path']) : null;
        if ($image && is_file($image)) {
            $image = Resize::forWeb($image);
        } else {
            $image = null;
        }

        $parts = self::emailParts($idea, $queued ? (string) $row['distilled'] : '', $link, $image !== null);
        if ($dryRun) {
            return "To: $to\nSubject: $subject\nImage: " . ($image ?? 'none') . "\n\n" . $parts['text'];
        }

        Database::run('UPDATE af_daily_sends SET send_tries = send_tries + 1, subject = ?, to_email = ? WHERE id = ?',
            [mb_substr($subject, 0, 500), $to, (int) $row['id']]);
        $ok = self::mail($to, $subject, $parts['text'], $parts['html'], $image);
        if ($ok) {
            Database::run("UPDATE af_daily_sends SET status = 'sent', sent = 1, sent_at = NOW() WHERE id = ?",
                [(int) $row['id']]);
            return "sent {$row['send_date']} (" . ($queued ? 'queued' : 'raw, queue was empty')
                 . ($image ? ', with plate' : ', no plate') . ") to $to: $subject";
        }
        $tries = (int) Database::value('SELECT send_tries FROM af_daily_sends WHERE id = ?', [(int) $row['id']]);
        if ($tries >= self::MAX_TRIES) {
            Database::run("UPDATE af_daily_sends SET status = 'failed' WHERE id = ?", [(int) $row['id']]);
        }
        throw new \RuntimeException("SMTP2GO did not accept {$row['send_date']} (try $tries of " . self::MAX_TRIES . ').');
    }

    private static function emailParts(array $idea, string $distilled, string $link, bool $hasImage): array
    {
        $title = self::niceTitle($idea['title']);
        $kicker = "Hypnosis Brain · {$idea['topic']} · " . self::niceTitle($idea['section']);
        $source = $idea['title'] . '. ' . $idea['body'];
        $cite = $idea['citation'] !== '' ? '(' . $idea['citation'] . ')' : '';
        $dry = 'The queue ran dry, so this one has no explainer or plate. '
             . 'Ask Claude Code to queue more Daily Ideas.';

        $text = "$title\n$kicker\n\n"
              . ($distilled !== '' ? "The distilled idea\n\n$distilled\n\n" : "$dry\n\n")
              . "From the brain\n\n$source\n$cite\n\nThe idea page: $link\n";

        $p = static fn(string $s) => implode('', array_map(
            static fn($para) => '<p style="margin:0 0 14px">' . nl2br(h(trim($para))) . '</p>',
            preg_split('/\n\s*\n/', trim($s)) ?: []));
        $html = '<div style="max-width:680px;margin:0 auto;font:16px/1.55 Georgia,serif;color:#26303B">'
              . ($hasImage ? '<img src="cid:plate.jpg" alt="' . h($title) . '" style="width:100%;height:auto;border:1px solid #e5e5e5;margin-bottom:18px">' : '')
              . '<h1 style="font-size:24px;color:#1B3A5C;margin:0 0 4px">' . h($title) . '</h1>'
              . '<div style="font:12px Helvetica,Arial,sans-serif;color:#58616D;margin-bottom:20px">' . h($kicker) . '</div>'
              . ($distilled !== ''
                  ? '<div style="font:bold 11px Helvetica,Arial,sans-serif;letter-spacing:2px;color:#D4A017;text-transform:uppercase;margin-bottom:8px">The distilled idea</div>' . $p($distilled)
                  : '<p style="background:#F7F3E7;padding:10px 14px">' . h($dry) . '</p>')
              . '<div style="font:bold 11px Helvetica,Arial,sans-serif;letter-spacing:2px;color:#D4A017;text-transform:uppercase;margin:24px 0 8px">From the brain</div>'
              . '<p style="font-size:14px;color:#3a4450">' . h($source) . '</p>'
              . ($cite !== '' ? '<p style="font:12px Helvetica,Arial,sans-serif;color:#58616D">' . h($cite) . '</p>' : '')
              . '<p style="font:14px Helvetica,Arial,sans-serif;margin-top:24px"><a href="' . h($link) . '" style="color:#1B3A5C">Read the full idea page &rarr;</a></p>'
              . '</div>';
        return ['text' => $text, 'html' => $html];
    }

    /**
     * SMTP2GO with an HTML body and the plate inline. tl_mail() is text-only,
     * and the plate cannot be linked: every Anglerfish URL is behind the admin
     * gate, so Gmail could never fetch it.
     */
    private static function mail(string $to, string $subject, string $text, string $html, ?string $image): bool
    {
        $key = (string) tl_env('SMTP2GO_API_KEY', '');
        if ($key === '') {
            return false;
        }
        $payload = [
            'api_key'   => $key,
            'to'        => [$to],
            'sender'    => (string) tl_env('MAIL_FROM', 'Bizorca Tools <tools@bizorca.com>'),
            'subject'   => $subject,
            'text_body' => $text,
            'html_body' => $html,
        ];
        if ($image) {
            $mime = str_ends_with(strtolower($image), '.png') ? 'image/png' : 'image/jpeg';
            $payload['inlines'] = [[
                'filename' => 'plate.jpg',
                'fileblob' => base64_encode((string) file_get_contents($image)),
                'mimetype' => $mime,
            ]];
        }
        $ch = curl_init('https://api.smtp2go.com/v3/email/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = is_string($body) ? json_decode($body, true) : null;
        return $code === 200 && is_array($json) && (int) ($json['data']['succeeded'] ?? 0) > 0;
    }

    /** Title case for an all-caps item title. */
    public static function niceTitle(string $title): string
    {
        $t = mb_convert_case(mb_strtolower($title), MB_CASE_TITLE);
        $t = preg_replace_callback('/\b(A|An|And|As|At|But|By|For|In|Is|Of|On|Or|The|To|Vs|With)\b/',
            static fn($m) => strtolower($m[1]), $t) ?: $title;
        $t = preg_replace_callback("/(?<=\w)'(S|T|Re|Ve|Ll|D|M)\b/",
            static fn($m) => "'" . strtolower($m[1]), $t) ?: $t;
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }
}
