<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

final class Composition
{
    /**
     * The six rotating formats, as offered by the compose form.
     *
     * `weekly` is deliberately absent. It is a seventh format in the database
     * and in Compose, but it needs a module and a deliverable container that
     * this form has nowhere to ask for, so it is created from /weekly instead.
     * Listing it here would produce a weekly post with no module tag.
     */
    public const FORMATS = ['before_noon' => 'Before Noon', 'gut_check' => 'Gut Check',
                            'steal_this' => 'Steal This', 'the_upgrade' => 'The Upgrade',
                            'the_protocol' => 'The Protocol', 'one_number' => 'One Number'];
    public const AUDIENCES = ['practitioners' => 'Practitioners (health & wellness)',
                              'tax_professionals' => 'Tax professionals',
                              'coaches' => 'Coaches & consultants',
                              'real_estate' => 'Real estate investors'];
    public const LENGTHS = ['short' => 'Short — 150-250 words',
                            'medium' => 'Medium — 400-600 words',
                            'long' => 'Long — 800-1200 words'];

    /** What the compose picker can browse, and the subject_type each yields. */
    public const PICK_TYPES = [
        'big_idea' => ['label' => 'Big Ideas', 'subject' => 'concept'],
        'chapter'  => ['label' => 'Book chapters', 'subject' => 'chapter'],
        'tool'     => ['label' => 'Kit tools', 'subject' => 'artifact'],
        'clipping' => ['label' => 'Clippings', 'subject' => 'clipping'],
        'post'     => ['label' => 'Past posts', 'subject' => 'post'],
    ];

    /**
     * Browsable source material for the picker.
     *
     * @return array<int,array{subject:string,id:int,title:string,meta:string}>
     */
    public static function candidates(string $pickType, string $q = '', int $limit = 150): array
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $has = $q !== '';

        [$sql, $args] = match ($pickType) {
            'big_idea' => [
                // The duplicate-title problem this label solved disappeared with
                // the 2026-08-09 fold: the two corpora merged, so no topic has
                // two Big Ideas books any more.
                'SELECT c.id, c.title, CONCAT(b.title, " · §", c.ord) AS meta
                   FROM af_concepts c JOIN af_books b ON b.id = c.book_id
                  WHERE b.kind = "big_ideas"' . ($has ? ' AND (c.title LIKE ? OR b.title LIKE ?)' : '')
                . ' ORDER BY b.title, b.brain, c.ord LIMIT ' . $limit,
                $has ? [$like, $like] : []],
            'chapter' => [
                'SELECT c.id, c.title, CONCAT(b.title, " · ch ", COALESCE(c.number_label, c.ord)) AS meta
                   FROM af_chapters c JOIN af_books b ON b.id = c.book_id
                  WHERE b.kind = "scan" AND c.status <> "proposed"'
                . ($has ? ' AND (c.title LIKE ? OR b.title LIKE ?)' : '')
                . ' ORDER BY b.title, c.ord LIMIT ' . $limit,
                $has ? [$like, $like] : []],
            'tool' => [
                'SELECT a.id, a.title, COALESCE(k.title, b.title, "—") AS meta
                   FROM af_artifacts a
              LEFT JOIN af_kits k ON k.id = a.kit_id
              LEFT JOIN af_books b ON b.id = a.book_id'
                . ($has ? ' WHERE a.title LIKE ?' : '')
                . ' ORDER BY a.id DESC LIMIT ' . $limit,
                $has ? [$like] : []],
            'clipping' => [
                'SELECT id, title, CONCAT(UPPER(brain), COALESCE(CONCAT(" · ", author), "")) AS meta
                   FROM af_clippings' . ($has ? ' WHERE title LIKE ? OR body LIKE ?' : '')
                . ' ORDER BY id DESC LIMIT ' . $limit,
                $has ? [$like, $like] : []],
            default => [
                'SELECT id, title, CONCAT("#", number, " · ", status) AS meta
                   FROM af_posts' . ($has ? ' WHERE title LIKE ?' : '')
                . ' ORDER BY number DESC LIMIT ' . $limit,
                $has ? [$like] : []],
        };

        $subject = self::PICK_TYPES[$pickType]['subject'];
        $rows = array_map(static fn(array $r) => [
            'subject' => $subject,
            'id'      => (int) $r['id'],
            'title'   => (string) $r['title'],
            'meta'    => (string) $r['meta'],
        ], Database::all($sql, $args));

        return ['rows' => $rows, 'total' => self::candidateCount($pickType, $q)];
    }

    /**
     * How many candidates exist, as opposed to how many are shown.
     *
     * The picker is capped, and a capped list that does not say so reads as
     * the whole set — which is how 540 of 580 big ideas were silently absent.
     */
    public static function candidateCount(string $pickType, string $q = ''): int
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $has = $q !== '';

        [$sql, $args] = match ($pickType) {
            'big_idea' => [
                'SELECT COUNT(*) FROM af_concepts c JOIN af_books b ON b.id = c.book_id
                  WHERE b.kind = "big_ideas"' . ($has ? ' AND (c.title LIKE ? OR b.title LIKE ?)' : ''),
                $has ? [$like, $like] : []],
            'chapter' => [
                'SELECT COUNT(*) FROM af_chapters c JOIN af_books b ON b.id = c.book_id
                  WHERE b.kind = "scan" AND c.status <> "proposed"'
                . ($has ? ' AND (c.title LIKE ? OR b.title LIKE ?)' : ''),
                $has ? [$like, $like] : []],
            'tool' => [
                'SELECT COUNT(*) FROM af_artifacts a' . ($has ? ' WHERE a.title LIKE ?' : ''),
                $has ? [$like] : []],
            'clipping' => [
                'SELECT COUNT(*) FROM af_clippings' . ($has ? ' WHERE title LIKE ? OR body LIKE ?' : ''),
                $has ? [$like, $like] : []],
            default => [
                'SELECT COUNT(*) FROM af_posts' . ($has ? ' WHERE title LIKE ?' : ''),
                $has ? [$like] : []],
        };
        return (int) Database::value($sql, $args);
    }

    /** Source material for the prompt, and whether an angle is compulsory. */
    public static function subject(string $type, int $id): ?array
    {
        return match ($type) {
            'artifact' => Database::one(
                'SELECT a.id, a.title, a.intro, a.verbatim, a.type, a.book_id,
                        b.title AS book_title, k.title AS kit_title
                   FROM af_artifacts a
                   LEFT JOIN af_books b ON b.id=a.book_id
                   LEFT JOIN af_kits  k ON k.id=a.kit_id
                  WHERE a.id=?', [$id]),
            'concept'  => Database::one(
                'SELECT c.id, c.title, c.body, c.kind, c.book_id, b.title AS book_title,
                        b.kind AS book_kind,
                        (SELECT GROUP_CONCAT(a.name ORDER BY ba.ord SEPARATOR "; ")
                           FROM af_book_authors ba JOIN af_authors a ON a.id = ba.author_id
                          WHERE ba.book_id = b.id) AS authors
                   FROM af_concepts c LEFT JOIN af_books b ON b.id=c.book_id
                  WHERE c.id=?', [$id]),
            'post'     => Database::one('SELECT id, title, body FROM af_posts WHERE id=?', [$id]),
            // A chapter carries no text of its own; the words live in pages.
            // Big Ideas sections are one page per chapter, so this reads back
            // exactly one section. A scanned book returns its whole page range.
            'chapter'  => Database::one(
                'SELECT c.id, c.title, c.number_label, c.book_id, b.title AS book_title, b.kind,
                        (SELECT GROUP_CONCAT(a.name ORDER BY ba.ord SEPARATOR "; ")
                           FROM af_book_authors ba JOIN af_authors a ON a.id = ba.author_id
                          WHERE ba.book_id = b.id) AS authors,
                        (SELECT GROUP_CONCAT(p.text ORDER BY p.pdf_page SEPARATOR "\n\n")
                           FROM af_pages p
                          WHERE p.book_id = c.book_id
                            AND p.pdf_page BETWEEN COALESCE(c.pdf_page_start, 0)
                                               AND COALESCE(c.pdf_page_end, 0)) AS body
                   FROM af_chapters c JOIN af_books b ON b.id = c.book_id
                  WHERE c.id = ?', [$id]),
            'clipping' => Database::one(
                'SELECT id, title, body, note, author, collection, brain, path,
                        verbatim
                   FROM af_clippings WHERE id = ?', [$id]),
            default    => null,
        };
    }

    public static function create(array $in, ?array $subject): int
    {
        // Weekly-only. Null for the six rotating formats, which have neither.
        $moduleId    = !empty($in['module_id']) ? (int) $in['module_id'] : null;
        $deliverable = $in['deliverable'] ?? null;
        $module      = $moduleId ? Weekly::module($moduleId) : null;

        // Which masthead this is for. Everything the publication decides —
        // voice, format spec, model, effort, whether a citation is mandatory —
        // is read here and travels in the payload, so a later edit to the
        // publications table cannot rewrite what an old composition was built
        // from. Same reasoning as the module snapshot below.
        //
        // An explicit voice_preset on the request still wins, because the
        // compose screen offers one and an operator override should mean what
        // it says. Absent that, the publication's preset is the answer.
        $pub = \Anglerfish\Models\Publication::resolve(
            $in['publication'] ?? $in['publication_id'] ?? null
        );
        $voice = trim((string) ($in['voice_preset'] ?? '')) !== ''
            ? $in['voice_preset']
            : $pub['voice_preset'];

        $id = Database::insert(
            'INSERT INTO af_compositions (publication_id, subject_type, subject_id,
                                       format, module_id,
                                       deliverable, angle, audience, length,
                                       voice_preset, extra)
             VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?, ?, NULLIF(?, ""))',
            [(int) $pub['id'], $in['subject_type'], $in['subject_id'] ?: null,
             $in['format'], $moduleId, $deliverable, $in['angle'],
             $in['audience'], $in['length'], $voice, $in['extra']]
        );

        // The source travels in the payload so the runner needs no extra lookup.
        Job::enqueue('compose', 'composition', $id, [
            'composition_id' => $id,
            'format'         => $in['format'],
            'audience'       => $in['audience'],
            'length'         => $in['length'],
            'voice_preset'   => $voice,
            'angle'          => $in['angle'],
            'extra'          => $in['extra'],
            // The publication's settings, snapshotted. `format_spec` swaps the
            // whole format grammar in the system prompt; `model` and `effort`
            // pick what writes it; `citation_required` puts the evidence rule
            // in the user turn and is what Citations::sweep() then enforces.
            'publication'       => $pub['pubkey'],
            'format_spec'       => $pub['format_spec'],
            'model'             => \Anglerfish\Models\Publication::model($pub),
            'effort'            => \Anglerfish\Models\Publication::effort($pub),
            'citation_required' => (int) $pub['citation_required'],
            'citation_min'      => (int) $pub['citation_min'],
            // Resolved here rather than in the runner, for the same reason the
            // source and the support passages are: the payload is the record of
            // what the model was shown, and a later edit to the modules table
            // must not silently rewrite what an old composition was built from.
            'module'         => $module ? [
                'number'   => (int) $module['id'],
                'title'    => $module['title'],
                'premise'  => $module['premise'],
                'artifact' => $module['artifact'],
                'stalls'   => (bool) $module['stalls'],
            ] : null,
            'deliverable'    => $deliverable,
            'source'         => self::sourceForPrompt($subject),
            // Retrieved at enqueue time, not at run time, so the payload is a
            // complete record of what the model was actually shown.
            'support'        => !empty($in['expand']) && $subject
                ? \Anglerfish\Services\Expand::fromLibrary($subject, self::bookIdOf($subject))
                : [],
        ], 1);

        return $id;
    }

    /**
     * The book a subject came from, so expansion can exclude it.
     *
     * Without this the top hits are the source's own neighbouring pages, which
     * is the one thing the composer already has.
     */
    private static function bookIdOf(?array $s): ?int
    {
        return !empty($s['book_id']) ? (int) $s['book_id'] : null;
    }

    private static function sourceForPrompt(?array $s): ?array
    {
        if (!$s) {
            return null;
        }
        $out = array_intersect_key($s, array_flip(['title', 'intro', 'body', 'verbatim']));

        // Who said it. Attribution is half the value of this corpus — "Kennedy
        // calls this..." only works if the prompt knows whose idea it was.
        $who = array_filter([$s['author'] ?? null, $s['collection'] ?? null,
                             $s['book_title'] ?? null]);
        if ($who) {
            $out['attribution'] = implode(' · ', array_unique($who));
        }

        // How to handle the book, from its pass 1 CONTEXT: the author's
        // vantage, who it was written for, what generalises past their field
        // and what does not, and what needs attributing rather than asserting.
        // Framing rather than material — see Compose::user(), which labels it
        // as such so it is never mistaken for something to write from.
        if ($id = self::bookIdOf($s)) {
            $ctx = Database::value('SELECT context FROM af_books WHERE id = ?', [$id]);
            if (is_string($ctx) && trim($ctx) !== '') {
                $out['book_context'] = $ctx;
            }
        }
        if (!empty($s['id']) && isset($s['type'])) {
            $out['items'] = array_column(Database::all(
                'SELECT text FROM af_artifact_items WHERE artifact_id=? ORDER BY ord LIMIT 30',
                [(int) $s['id']]), 'text');
        }
        return $out;
    }

    /**
     * Can this composition be promoted?
     *
     * Publications with citation_required cannot promote a post that has no
     * verified peer-reviewed source. The check is here rather than in the
     * controller because there are three ways to promote — the compose screen,
     * the CLI, and weekly-import — and an evidence rule enforced in two of
     * three is not a rule.
     *
     * "Verified" means Citations::verify() fetched the DOI from CrossRef and
     * got a record back. A citation the model wrote and nobody checked does not
     * count, which is the entire point: post 001 shipped two fluent, plausible,
     * false attributions and no prompt would have caught either.
     *
     * @return array{ok:bool,reason:string}
     */
    public static function canPromote(array $c): array
    {
        $pub = \Anglerfish\Models\Publication::byId((int) ($c['publication_id'] ?? 1));
        if (!$pub || !\Anglerfish\Models\Publication::requiresCitation($pub)) {
            return ['ok' => true, 'reason' => ''];
        }

        $min = \Anglerfish\Models\Publication::citationMin($pub);
        $have = count(\Anglerfish\Services\Citations::verified(null, (int) $c['id']));
        if ($have >= $min) {
            return ['ok' => true, 'reason' => ''];
        }

        // Say what is actually in the body, because "0 verified" reads very
        // differently depending on whether the model cited nothing at all or
        // cited three DOIs that turned out not to exist.
        $found = \Anglerfish\Services\Citations::extract((string) ($c['body'] ?? ''));
        $detail = $found
            ? count($found) . ' DOI(s) in the body, none verified — run `php cli.php cite --check='
                . (int) $c['id'] . '` to see why'
            : 'no DOI in the body at all';

        return [
            'ok'     => false,
            'reason' => "{$pub['name']} requires $min verified peer-reviewed citation(s); "
                      . "this composition has $have ($detail)",
        ];
    }

    /** Turn a finished composition into an editable draft post. */
    public static function promote(int $id): ?string
    {
        $c = Database::one('SELECT * FROM af_compositions WHERE id=? AND status="done"', [$id]);
        if (!$c) {
            return null;
        }
        if ($c['post_id']) {
            return Database::value('SELECT slug FROM af_posts WHERE id=?', [(int) $c['post_id']]);
        }

        $gate = self::canPromote($c);
        if (!$gate['ok']) {
            throw new \RuntimeException($gate['reason']);
        }

        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-',
            mb_substr((string) $c['title'], 0, 60)), '-')) ?: 'draft';
        $slug = $base;
        $n = 2;
        while (Database::value('SELECT id FROM af_posts WHERE slug=?', [$slug])) {
            $slug = "$base-" . $n++;
        }

        $next = 1 + (int) Database::value('SELECT COALESCE(MAX(number),0) FROM af_posts');

        // The deliverable's printed number is assigned at promotion, not at
        // compose time. A composition that is generated and never promoted must
        // not burn a sequence number, or the printed series develops gaps that
        // a reader collecting the workbook can see.
        $sequence = $c['format'] === 'weekly' ? Weekly::nextSequence() : null;

        // The deliverable's authored content rides along (migration 015). Without
        // it the PDF renders an empty container, which is what every weekly post
        // promoted before this did.
        // The publication travels to the post, and so does its attribution key
        // — that is the footer migration 016 exists to get right, and defaulting
        // it here rather than at render time means a Hypnologue post cannot
        // reach the generate screen already pointing at bizorca's copyright.
        $pubId = (int) ($c['publication_id'] ?? 1);
        $pub = \Anglerfish\Models\Publication::byId($pubId);

        $postId = Database::insert(
            'INSERT INTO af_posts (publication_id, attribution, number, slug, format,
                                title, subtitle, body, status,
                                module_id, sequence_number, deliverable, deliverable_spec,
                                angle, voice_preset, model_used)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "draft", ?, ?, ?, ?, ?, ?, ?)',
            [$pubId, $pub['attribution'] ?? 'bizorca',
             $next, $slug, $c['format'], $c['title'], $c['subtitle'], $c['body'],
             $c['module_id'], $sequence, $c['deliverable'], $c['deliverable_spec'] ?? null,
             $c['angle'], $c['voice_preset'], $c['model']]
        );

        // Carry the verified citations across. Without this the post arrives
        // with its evidence still attached to the composition, and anything
        // that later asks "is this post sourced?" answers no about a post that
        // passed the gate two lines ago.
        Database::run(
            'UPDATE af_citations SET post_id = ? WHERE composition_id = ?',
            [$postId, $id]
        );

        Database::run('UPDATE af_compositions SET post_id=? WHERE id=?', [$postId, $id]);
        return $slug;
    }
}
