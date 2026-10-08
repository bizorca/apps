<?php

namespace Anglerfish\Services;

use Anglerfish\Core\Database;

/**
 * Supporting detail for a composition (SPEC §11.2).
 *
 * A Big Ideas section is breadth: a themed, attributed synthesis, deliberately
 * compressed. Writing from it alone produces a post that is correct and thin.
 * This pulls the depth back in — related passages from every other book in the
 * library, including the scanned ones and the other brain's take on the same
 * topic.
 *
 * Two things make this cheap. Importing Big Ideas as books put all 580 sections
 * into `pages`, which already carries a FULLTEXT index, so cross-source
 * retrieval is one query over 12,717 pages with no job and no wait. And
 * selection is left to the composer rather than done here: retrieve generously,
 * label everything with its provenance, and let the model ignore what does not
 * fit. It is already reading the prompt — a second model call to pre-filter
 * would double the latency to do a job the first call does for free.
 *
 * The corpus grep (38,086 files) is the deeper tier and is handled separately,
 * because the bodies live on the Mac until the corpus is synced.
 */
final class Expand
{
    /** Retrieved generously — the composer does the filtering. */
    private const LIMIT = 10;

    /** Long enough to carry an argument, short enough that ten still fit. */
    private const EXCERPT = 700;

    /**
     * Words too common in this corpus to discriminate. MySQL's own stopword
     * list does not know that every one of these books is about marketing.
     */
    private const NOISE = ['the','and','for','you','your','with','that','this','from',
        'are','not','but','all','how','why','what','who','when','can','will','has',
        'have','was','were','they','them','their','marketing','business',
        'customer','customers','client','clients','money','people','make','get',
        'more','one','out','about','into','than','then','some','any','also','big',
        'ideas','section',
        // Added after the first run returned OCR noise from finance textbooks:
        // these matched everything and dragged in generic passages.
        'best','ones','come','comes','source','sources','way','ways','need','needs',
        'want','know','think','good','great','time','times','work','works','first',
        'last','much','many','take','takes','give','gives','thing','things','over',
        'only','even','just','very','most','other','others','same','each','every',
        'own','new','old','see','say','says','used','using','use','well','back',
        'down','through','before','after','being','doing','does','done','here',
        'there','where','which','while','would','could','should','must','like'];

    /**
     * Distinctive search terms, taken from the title first.
     *
     * A section heading is already a human-written summary of the passage, so
     * its words discriminate far better than the body's. The body is only a
     * fallback for when the title is too short to search on.
     */
    public static function terms(array $subject): string
    {
        $pick = static function (string $text, array $have): array {
            preg_match_all('/[a-z][a-z\-]{3,}/', mb_strtolower($text), $m);
            foreach ($m[0] as $w) {
                if (!in_array($w, self::NOISE, true) && !in_array($w, $have, true)) {
                    $have[] = $w;
                }
            }
            return $have;
        };

        $terms = $pick((string) ($subject['title'] ?? ''), []);
        if (count($terms) < 4) {
            $terms = $pick(mb_substr((string) ($subject['body'] ?? ''), 0, 400), $terms);
        }
        return implode(' ', array_slice($terms, 0, 8));
    }

    /**
     * Reject OCR sludge.
     *
     * Half the library is scanned, and a page of mis-recognised running heads
     * ("; es e e s 3 The Practice of...") matches keyword search as happily as
     * real prose while being worthless as source material. Real sentences have
     * long words and few orphan characters.
     */
    private static function readable(string $text): bool
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if (count($words) < 25) {
            return false;
        }
        $short = 0;
        $letters = 0;
        foreach ($words as $w) {
            if (mb_strlen($w) <= 2) {
                $short++;
            }
            $letters += mb_strlen((string) preg_replace('/[^a-zA-Z]/', '', $w));
        }
        // More than a third orphan tokens, or fewer than three letters per word
        // on average, means the page did not survive OCR.
        return $short / count($words) < 0.34
            && $letters / count($words) >= 3.0;
    }

    /**
     * Related passages from other books.
     *
     * @return array<int,array{source:string,title:string,text:string}>
     */
    public static function fromLibrary(array $subject, ?int $excludeBookId = null): array
    {
        $terms = self::terms($subject);
        if (mb_strlen($terms) < 4) {
            return [];
        }

        // NATURAL LANGUAGE MODE rather than BOOLEAN: the terms are a bag of
        // words from a heading, and boolean mode would treat a missing term as
        // a rejection rather than a lower score.
        $rows = Database::all(
            'SELECT b.title AS book, b.kind, b.brain, p.pdf_page, p.text,
                    c.title AS chapter,
                    MATCH(p.text) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
               FROM af_pages p
               JOIN af_books b ON b.id = p.book_id
          LEFT JOIN af_chapters c ON c.book_id = p.book_id
                              AND p.pdf_page BETWEEN c.pdf_page_start AND c.pdf_page_end
              WHERE MATCH(p.text) AGAINST (? IN NATURAL LANGUAGE MODE)
                AND b.scope = "press"
                AND (? IS NULL OR b.id <> ?)
                AND CHAR_LENGTH(p.text) > 200
           ORDER BY score DESC
              LIMIT ' . (self::LIMIT * 4),        // over-fetch; most get filtered
            [$terms, $terms, $excludeBookId, $excludeBookId]
        );

        $wanted = explode(' ', $terms);
        $out = [];
        foreach ($rows as $r) {
            $text = (string) $r['text'];
            if (!self::readable($text)) {
                continue;
            }
            // Natural-language mode will happily return a page that shares one
            // common word. Require two distinct terms actually present, so a
            // passage has to be about the subject rather than adjacent to it.
            $hits = 0;
            foreach ($wanted as $w) {
                if ($w !== '' && stripos($text, $w) !== false) {
                    $hits++;
                }
            }
            if ($hits < 2) {
                continue;
            }
            // Chapter titles on scanned books are often OCR sludge ("; es e e s
            // 3"). The body can still be perfectly good, so drop the label
            // rather than the passage — this string is the attribution the
            // model is told to cite.
            $chapter = self::cleanLabel((string) ($r['chapter'] ?? ''));
            $out[] = [
                'source' => trim($r['book'] . ($chapter !== '' ? ' — ' . $chapter : '')),
                'title'  => $chapter ?: (string) $r['book'],
                'text'   => self::excerpt($text),
            ];
            if (count($out) >= self::LIMIT) {
                break;
            }
        }
        return $out;
    }

    /** A chapter label worth showing, or '' if OCR mangled it. */
    private static function cleanLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        $real = 0;
        foreach (preg_split('/\s+/', $label) ?: [] as $w) {
            if (preg_match('/^[A-Za-z]{3,}$/', $w)) {
                $real++;
            }
        }
        // Needs at least two genuine words, and mostly letters overall.
        $letters = mb_strlen((string) preg_replace('/[^A-Za-z ]/', '', $label));
        return ($real >= 2 && $letters / max(1, mb_strlen($label)) > 0.6) ? $label : '';
    }

    /** Trim to a whole sentence near the limit rather than mid-word. */
    private static function excerpt(string $text): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($t) <= self::EXCERPT) {
            return $t;
        }
        $cut = mb_substr($t, 0, self::EXCERPT);
        $stop = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, '? ') ?: 0);
        return ($stop > self::EXCERPT * 0.5 ? mb_substr($cut, 0, $stop + 1) : $cut) . ' …';
    }
}
