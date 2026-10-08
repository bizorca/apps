<?php

namespace Anglerfish\Services;

/**
 * Prompt assembly for the composer (SPEC §11.2).
 *
 * The voice and format specs live on disk in worker/prompts/ so the Mac worker
 * and the server build byte-identical prompts from one source.
 */
final class Compose
{
    private const LENGTHS = ['short' => '150-250 words',
                             'medium' => '400-600 words',
                             'long' => '800-1200 words'];

    /**
     * The standing instructions: voice, formats, output contract.
     *
     * Byte-identical across every compose with the same voice preset, which is
     * what makes it worth caching as a system prompt. Anything that varies by
     * post belongs in user() instead — putting it here would change the prefix
     * and cost a full cache write on every single call.
     */
    public static function system(array $p = []): string
    {
        $dir = dirname(__DIR__, 2) . '/worker/prompts';
        $preset = str_replace('_', '-', (string) ($p['voice_preset'] ?? 'bizorca_press'));
        $voice = "$dir/voice-$preset.md";
        if (!is_file($voice)) {
            $voice = "$dir/voice-bizorca-press.md";
        }

        // A publication with its own format spec gets that spec and nothing
        // else. Hypnologue does not write Gut Checks or weekly deliverables,
        // and handing it 400 lines of Bizorca format grammar would not just
        // waste a cached prefix — it would leak the wrong register into a
        // publication whose whole value is a different one. Bizorca keeps the
        // existing behaviour exactly: both specs, unconditionally, because the
        // user turn names the format and the prefix must stay byte-identical
        // across all seven of them.
        $spec = trim((string) ($p['format_spec'] ?? ''));
        $specs = [];
        if ($spec !== '' && is_file("$dir/format-$spec.md")) {
            $specs[] = file_get_contents("$dir/format-$spec.md");
        } else {
            $specs[] = file_get_contents("$dir/formats.md");
            $specs[] = file_get_contents("$dir/format-weekly.md");
        }

        return implode("\n", [
            file_get_contents($voice),
            ...$specs,
            "\n## Output\nReturn exactly this, nothing else:\n"
            . "TITLE: <the title>\nSUBTITLE: <one line>\nBODY:\n<the post>\n\n"
            . "The body is plain text: blank line between paragraphs, `- ` for bullets, "
            . "`1. ` for numbered lists, and a line reading exactly `Go!` before the "
            . "closing section. No markdown headings, no HTML.\n\n"
            . "Hit the requested length. Do not pad to reach it and do not run past "
            . "it — a post that says the thing in fewer words is finished, not short. "
            . "No preamble, no commentary on your own draft, no alternatives.",
        ]);
    }

    /** Everything specific to this one post. */
    public static function user(array $p): string
    {
        $parts = ["# This post\n"];
        $parts[] = 'Format: **' . ($p['format'] ?? 'before_noon') . '**';
        $parts[] = 'Audience: ' . ($p['audience'] ?? 'practitioners');
        // The weekly post has one working band and it is not one of the three
        // stored on compositions.length. Rather than add a fourth enum value
        // that only one format can use, the format decides its own length.
        $parts[] = 'Length: ' . (($p['format'] ?? '') === 'weekly'
            ? '900-1400 words of body, excluding the deliverable'
            : (self::LENGTHS[$p['length'] ?? 'medium'] ?? self::LENGTHS['medium']));

        // The curriculum slot. Present only on weekly posts, and the reason the
        // module table is worth having: the composer gets the module's own
        // premise rather than a number it has to guess the meaning of.
        if (!empty($p['module'])) {
            $m = $p['module'];
            $parts[] = "\n## Curriculum slot — Module {$m['number']}: {$m['title']}\n"
                . "Premise: {$m['premise']}\n"
                . "What the module leaves the reader holding: {$m['artifact']}\n\n"
                . "Write to stand alone. A new subscriber landing on this post must get "
                . "full value from it with no reference to any earlier post, so never "
                . "assume a later module's work is done and never refer back to last week.";
            if (!empty($m['stalls'])) {
                $parts[] = "\n**This is a stall point.** Assume the reader already knows what "
                    . "to do and has not done it. The trap is avoidance, the diagnostic "
                    . "surfaces the avoidance, and the protocol removes the decision rather "
                    . "than explaining the concept again. Keep it to business behaviour — "
                    . "resistance to marketing, to raising prices, to installing systems. "
                    . "Not therapeutic content.";
            }
        }

        if (!empty($p['deliverable'])) {
            $parts[] = "\n## Deliverable container\n"
                . 'This post ships with a **' . str_replace('_', ' ', (string) $p['deliverable'])
                . "**. Write the protocol so it maps cleanly onto that container, and "
                . "write it out in full in the post body — the PDF is the working copy of "
                . "what the reader just read, never the continuation of it. Anything that "
                . "renders as a list appears in both places. Mention the download once, "
                . "plainly, in the Go! section.";
        }

        $angle = trim((string) ($p['angle'] ?? ''));
        $parts[] = $angle !== ''
            ? "\n## The operator's angle — build the post around this\n$angle"
            : "\n## No angle supplied\nWrite from the source material alone. Invent nothing "
              . "personal: no clients, no anecdotes, no numbers that are not in the source.";

        if (!empty($p['source'])) {
            $s = $p['source'];
            $parts[] = "\n## Source material\n";
            if (!empty($s['attribution'])) {
                $parts[] = 'Attributed to: ' . $s['attribution']
                    . ". Name the source in the post where it earns the point — "
                    . "\"Kennedy calls this...\" — and never imply the idea is yours.\n";
            }
            if (!empty($s['book_context'])) {
                $parts[] = "\n### How to handle this book\n"
                    . "Background on the source, not material for the post. Do not write "
                    . "from it or quote it; let it decide what to claim, what to attribute "
                    . "rather than assert, and what to leave alone.\n\n"
                    . $s['book_context'] . "\n";
            }
            foreach (['title', 'intro', 'body', 'summary'] as $k) {
                if (!empty($s[$k])) {
                    $parts[] = (string) $s[$k];
                }
            }
            foreach (array_slice($s['items'] ?? [], 0, 30) as $it) {
                $parts[] = '- ' . (is_array($it) ? ($it['text'] ?? '') : $it);
            }
            if (!empty($s['verbatim'])) {
                $parts[] = "\n**This material is transcribed verbatim from a copyrighted book. "
                    . "Transform it — restate the idea in Jassen's own words and framing. "
                    . "Do not reproduce the list as written.**";
            }
        }

        // Supporting detail retrieved from the rest of the library. Deliberately
        // over-retrieved — say plainly that most of it will not fit, so an
        // irrelevant passage gets dropped instead of shoehorned in.
        if (!empty($p['support'])) {
            $parts[] = "\n## Supporting detail from other sources\n"
                . "Passages retrieved by keyword, so some will not be relevant. Use only "
                . "what genuinely sharpens the post and ignore the rest — do not force a "
                . "passage in to justify its being here. Attribute anything you do use.\n";
            foreach ($p['support'] as $i => $sup) {
                $parts[] = sprintf("\n%d. **%s**\n%s", $i + 1, $sup['source'], $sup['text']);
            }
        }

        // The evidence rule, for publications that carry one.
        //
        // This block does not make citations true. Nothing written in a prompt
        // can — a model asked for a DOI will produce a well-formed, plausible,
        // non-existent one, and post 001 shipped two such claims before anyone
        // checked. Citations::sweep() is what actually decides, by fetching
        // every DOI in the body from CrossRef.
        //
        // What this block is for is the shape of the post: a citation has to be
        // load-bearing rather than decorative, and the model has to know that
        // "no such study exists" is an acceptable answer. Told to cite and
        // given no way out, a model invents. Given the escape hatch, it uses it.
        if (!empty($p['citation_required'])) {
            $min = max(1, (int) ($p['citation_min'] ?? 1));
            $parts[] = "\n## Evidence — this publication's whole differentiator\n"
                . "This post must rest on at least {$min} peer-reviewed source, named in "
                . "the body where the claim is made and listed under a `References` heading "
                . "at the end with a DOI.\n\n"
                . "**Every DOI you write will be fetched from CrossRef before this post can "
                . "be promoted.** A DOI that does not resolve to a real record fails the "
                . "post — not silently, and not as a note for later. So:\n\n"
                . "- Cite only work you can state with confidence actually exists, by "
                . "authors who actually wrote it.\n"
                . "- If you are not certain of the DOI, write the citation without one and "
                . "mark it `[DOI: unverified]`. That is a flag for a human to resolve, and "
                . "it is always the right move over a guess.\n"
                . "- **If you cannot support the claim with real published work, say so in "
                . "the draft.** Write `[NO SOURCE FOUND: <what would be needed>]` and carry "
                . "on. An honest gap is publishable after an editor looks at it. A "
                . "fabricated citation is not, and it is the one failure this publication "
                . "cannot absorb.\n\n"
                . "A source that **disagrees** with the argument is welcome and often the "
                . "better post. The standing objection, the failed replication, the "
                . "methodological challenge — these belong in the body rather than in a "
                . "footnote, because a claim presented with its live objection attached is "
                . "the honest version. Do not hunt only for agreement.\n\n"
                . "Do not cite Wikipedia, a blog, a training school, a podcast or a "
                . "practitioner site as evidence for a factual claim. They can be named as "
                . "the origin of an idea; they cannot be the support for it.";
        }

        if (!empty($p['extra'])) {
            $parts[] = "\n## One-off steering\n" . $p['extra'];
        }

        return implode("\n", $parts);
    }

    /**
     * Single-string form, for providers with no separate system channel.
     *
     * Same bytes as system() + user(), so the two paths cannot drift apart.
     */
    public static function prompt(array $p): string
    {
        return self::system($p) . "\n\n" . self::user($p);
    }

    /** @return array{title:string,subtitle:string,body:string} */
    public static function parse(string $text): array
    {
        $title = $subtitle = '';
        $body = trim($text);

        if (preg_match('/^\s*TITLE:\s*(.+)$/mi', $text, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/^\s*SUBTITLE:\s*(.+)$/mi', $text, $m)) {
            $subtitle = trim($m[1]);
        }
        if (preg_match('/^\s*BODY:\s*$(.*)/msi', $text, $m)) {
            $body = trim($m[1]);
        }

        // Models sometimes emit literal backslash-n rather than real newlines.
        // Left alone this reaches the handoff screen as one giant paragraph,
        // because the HTML converter splits on actual line breaks.
        if (!str_contains($body, "\n") && str_contains($body, '\\n')) {
            $body = str_replace(['\\r\\n', '\\n', '\\t'], ["\n", "\n", "\t"], $body);
        }

        // A model that ignored the format still produced usable prose; keep it
        // rather than discarding a paid call.
        if ($title === '') {
            $lines = preg_split('/\R/', trim($body));
            $title = mb_substr(trim($lines[0] ?? 'Untitled'), 0, 500);
        }

        return ['title' => mb_substr($title, 0, 500),
                'subtitle' => mb_substr($subtitle, 0, 500),
                'body' => $body];
    }
}
