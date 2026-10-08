<?php

namespace Anglerfish\Services;

use Anglerfish\Core\Database;
use GuzzleHttp\Client as Guzzle;

/**
 * Peer-reviewed citations, retrieved rather than recalled.
 *
 * Hypnologue's whole angle is hypnosis without the mystique, which is worth
 * nothing unless the claims survive checking. Post 001 is the reason this class
 * exists: two of its claims failed verification after drafting — a definition
 * attributed to Hilgard that appears nowhere in his work, and a flat statement
 * about Braid coining "hypnotism" that the literature contradicts. Both were
 * fluent. Both were plausible. Neither was true.
 *
 * A language model asked for a citation will produce something that looks
 * exactly like a row in the `citations` table: real-sounding authors, a
 * plausible journal, a DOI in the right shape. The defence is not a better
 * prompt, because the failure mode is confident fluency and prompts do not
 * cure that. The defence is that `verified_at` can only be written by this
 * class, and this class only writes it after CrossRef has handed back a record
 * for that exact DOI.
 *
 * Nothing here calls a model. It is HTTP and string comparison, and it costs
 * nothing per post.
 */
final class Citations
{
    private const CROSSREF = 'https://api.crossref.org/works/';
    private const RESOLVER = 'https://doi.org/';
    private const TIMEOUT  = 20.0;

    /**
     * CrossRef `type` values this publication accepts as peer reviewed.
     *
     * `journal-article` is the honest core. `proceedings-article` and
     * `book-chapter` are admitted because psychology and hypnosis research
     * genuinely publishes in edited volumes — the Gorassini & Spanos Carleton
     * program description is a book chapter — but they are marked so a reviewer
     * can see what kind of source is carrying the claim.
     *
     * `posted-content` (preprints) is deliberately absent. A preprint has not
     * been reviewed, and this publication's differentiator is that its sources
     * have been.
     */
    private const PEER_REVIEWED = [
        'journal-article'     => true,
        'proceedings-article' => true,
        'book-chapter'        => true,
        'reference-entry'     => false,
        'posted-content'      => false,
        'dissertation'        => false,
        'report'              => false,
        'book'                => false,
    ];

    /**
     * A contact address in the CrossRef User-Agent.
     *
     * CrossRef's polite pool gives better latency and, more to the point,
     * scraping their API anonymously at volume is how an IP gets throttled.
     */
    private const UA = 'AnglerfishPress/1.0 (https://anglerfish.bizorca.com; '
                     . 'mailto:jassen.bowman@gmail.com)';

    private static function http(): Guzzle
    {
        return new Guzzle([
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => 10.0,
            'http_errors'     => false,
            'headers'         => ['User-Agent' => self::UA],
        ]);
    }

    /**
     * Pull DOIs out of free text.
     *
     * Used on a composed body so the model's proposed citations can be checked
     * rather than trusted. The pattern is the CrossRef-recommended one; the
     * trailing-punctuation trim matters because a DOI at the end of a sentence
     * picks up the full stop and then resolves to nothing, which reads as a
     * hallucinated citation when it is really a parsing bug.
     *
     * @return array<int,string> lowercased, de-duplicated, in order of appearance
     */
    public static function extract(string $text): array
    {
        preg_match_all('~\b10\.\d{4,9}/[-._;()/:a-z0-9A-Z]+~', $text, $m);
        $out = [];
        foreach ($m[0] as $doi) {
            $doi = strtolower(rtrim($doi, ".,;:)]}'\"" ));
            if (!isset($out[$doi])) {
                $out[$doi] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Ask CrossRef about a DOI.
     *
     * Returns null when CrossRef has no record — which is the signal that
     * matters. A DOI a model invented is almost always well-formed and almost
     * never in CrossRef.
     *
     * @return array<string,mixed>|null the CrossRef `message` object
     */
    public static function lookup(string $doi): ?array
    {
        $doi = self::normalise($doi);
        if ($doi === '') {
            return null;
        }
        $res = self::http()->get(self::CROSSREF . rawurlencode($doi));
        if ($res->getStatusCode() !== 200) {
            return null;
        }
        $body = json_decode((string) $res->getBody(), true);
        return is_array($body) && isset($body['message']) && is_array($body['message'])
            ? $body['message']
            : null;
    }

    /**
     * Does https://doi.org/<doi> actually resolve?
     *
     * A separate question from being in CrossRef, and worth asking separately:
     * CrossRef can hold a record whose resolver target has rotted. A 403 counts
     * as resolving — APA, Elsevier and Wiley all refuse a bare HEAD from a
     * script, and treating that as a dead link would reject most of psychology.
     * What we are testing is that the handle resolves to a publisher, not that
     * the publisher will serve us the PDF.
     */
    public static function resolves(string $doi): bool
    {
        $doi = self::normalise($doi);
        if ($doi === '') {
            return false;
        }
        $res = self::http()->head(self::RESOLVER . $doi, ['allow_redirects' => true]);
        $code = $res->getStatusCode();
        return $code < 400 || $code === 401 || $code === 403 || $code === 429;
    }

    /**
     * Strip the things people paste around a DOI.
     *
     * "https://doi.org/10.1037/x", "doi:10.1037/x" and "10.1037/x" are the same
     * citation, and a post body will contain all three spellings over time.
     */
    public static function normalise(string $doi): string
    {
        $doi = trim($doi);
        $doi = preg_replace('~^https?://(dx\.)?doi\.org/~i', '', $doi) ?? $doi;
        $doi = preg_replace('~^doi:\s*~i', '', $doi) ?? $doi;
        $doi = rtrim(trim($doi), ".,;:)]}'\"");
        return strtolower($doi);
    }

    /**
     * Flatten a CrossRef record into the columns the table holds.
     *
     * Everything here is copied, never inferred. Where CrossRef has no author —
     * which happens, including on at least one Braid paper in this
     * publication's own reading — the field stays null rather than being filled
     * with a plausible name. That is the entire point.
     *
     * @param  array<string,mixed> $m CrossRef message
     * @return array<string,mixed>
     */
    public static function flatten(array $m): array
    {
        $authors = [];
        foreach (($m['author'] ?? []) as $a) {
            $family = trim((string) ($a['family'] ?? ''));
            $given  = trim((string) ($a['given'] ?? ''));
            if ($family === '' && $given === '') {
                continue;
            }
            $authors[] = $given !== '' ? "$family, $given" : $family;
        }

        $year = null;
        foreach (['published-print', 'published-online', 'issued', 'created'] as $k) {
            $parts = $m[$k]['date-parts'][0][0] ?? null;
            if (is_int($parts) || (is_string($parts) && ctype_digit($parts))) {
                $year = (int) $parts;
                break;
            }
        }

        $type = (string) ($m['type'] ?? '');

        return [
            'doi'           => strtolower((string) ($m['DOI'] ?? '')),
            'title'         => self::first($m['title'] ?? null),
            'authors'       => $authors ? implode('; ', $authors) : null,
            'container'     => self::first($m['container-title'] ?? null),
            'year'          => $year,
            'volume'        => self::str($m['volume'] ?? null),
            'issue'         => self::str($m['issue'] ?? null),
            'pages'         => self::str($m['page'] ?? null),
            'type'          => $type,
            'peer_reviewed' => (self::PEER_REVIEWED[$type] ?? false) ? 1 : 0,
        ];
    }

    /**
     * Verify a DOI and record the result against a post or a composition.
     *
     * The only path to a non-null `verified_at`. Returns the stored row plus an
     * `ok` flag and, when it failed, why — the reason is what a reviewer reads,
     * so "not in CrossRef" and "found, but it is a preprint" have to be
     * different answers.
     *
     * @return array{ok:bool,reason:string,row:array<string,mixed>|null}
     */
    public static function verify(
        string $doi,
        ?int $postId = null,
        ?int $compositionId = null,
        string $supports = 'supports',
        ?string $claim = null
    ): array {
        $doi = self::normalise($doi);
        if ($doi === '') {
            return ['ok' => false, 'reason' => 'empty DOI', 'row' => null];
        }

        $m = self::lookup($doi);
        if ($m === null) {
            // The important failure. Store the attempt with verified_at NULL so
            // a rejected citation is visible rather than silently absent.
            self::store($doi, null, $postId, $compositionId, $supports, $claim, null, false);
            return [
                'ok'     => false,
                'reason' => 'not in CrossRef — this DOI does not exist',
                'row'    => null,
            ];
        }

        $flat = self::flatten($m);

        if (!$flat['peer_reviewed']) {
            self::store($doi, $flat, $postId, $compositionId, $supports, $claim, $m, false);
            return [
                'ok'     => false,
                'reason' => "found, but type is '{$flat['type']}' — not peer reviewed",
                'row'    => $flat,
            ];
        }

        $resolves = self::resolves($doi);
        $row = self::store($doi, $flat, $postId, $compositionId, $supports, $claim, $m, true, $resolves);

        return [
            'ok'     => true,
            'reason' => $resolves ? 'verified' : 'verified in CrossRef, but doi.org did not resolve',
            'row'    => $row,
        ];
    }

    /**
     * @param  array<string,mixed>|null $flat
     * @param  array<string,mixed>|null $raw
     * @return array<string,mixed>
     */
    private static function store(
        string $doi,
        ?array $flat,
        ?int $postId,
        ?int $compositionId,
        string $supports,
        ?string $claim,
        ?array $raw,
        bool $verified,
        ?bool $resolves = null
    ): array {
        $row = [
            'post_id'        => $postId,
            'composition_id' => $compositionId,
            'doi'            => $doi,
            'title'          => $flat['title']     ?? null,
            'authors'        => $flat['authors']   ?? null,
            'container'      => $flat['container'] ?? null,
            'year'           => $flat['year']      ?? null,
            'volume'         => $flat['volume']    ?? null,
            'issue'          => $flat['issue']     ?? null,
            'pages'          => $flat['pages']     ?? null,
            'supports'       => in_array($supports, ['supports', 'contradicts', 'complicates', 'background'], true)
                                  ? $supports : 'supports',
            'claim'          => $claim,
            'verified_at'    => $verified ? date('Y-m-d H:i:s') : null,
            'resolves'       => $resolves === null ? null : ($resolves ? 1 : 0),
            'crossref_json'  => $raw ? json_encode($raw) : null,
            'peer_reviewed'  => $flat['peer_reviewed'] ?? 0,
        ];

        Database::run(
            'INSERT INTO af_citations
                (post_id, composition_id, doi, title, authors, container, year,
                 volume, issue, pages, supports, claim, verified_at, resolves,
                 crossref_json, peer_reviewed)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array_values($row)
        );

        return $row;
    }

    /**
     * Verified, peer-reviewed citations attached to a post or composition.
     *
     * This is what the promotion gate counts. A row with `verified_at` NULL is
     * deliberately not returned — an unverified citation is a claim, not a
     * source.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function verified(?int $postId = null, ?int $compositionId = null): array
    {
        if ($postId !== null) {
            return Database::all(
                'SELECT * FROM af_citations
                  WHERE post_id = ? AND verified_at IS NOT NULL AND peer_reviewed = 1
                  ORDER BY id',
                [$postId]
            );
        }
        if ($compositionId !== null) {
            return Database::all(
                'SELECT * FROM af_citations
                  WHERE composition_id = ? AND verified_at IS NOT NULL AND peer_reviewed = 1
                  ORDER BY id',
                [$compositionId]
            );
        }
        return [];
    }

    /**
     * Sweep a body for DOIs and verify each one.
     *
     * The composer is told to cite, and this is what checks that it did. Run it
     * on every Hypnologue composition the moment it comes back, so a fabricated
     * DOI is caught while the draft is still on screen rather than after it is
     * published.
     *
     * @return array<int,array{doi:string,ok:bool,reason:string}>
     */
    public static function sweep(
        string $body,
        ?int $postId = null,
        ?int $compositionId = null
    ): array {
        $out = [];
        foreach (self::extract($body) as $doi) {
            $r = self::verify($doi, $postId, $compositionId);
            $out[] = ['doi' => $doi, 'ok' => $r['ok'], 'reason' => $r['reason']];
        }
        return $out;
    }

    /** @param mixed $v */
    private static function first($v): ?string
    {
        if (is_array($v)) {
            $v = $v[0] ?? null;
        }
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    /** @param mixed $v */
    private static function str($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }
}
