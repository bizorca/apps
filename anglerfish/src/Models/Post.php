<?php

namespace Anglerfish\Models;

use Anglerfish\Core\Database;

/**
 * Posts and the handoff to Substack (SPEC §11.4).
 *
 * Substack's publishing API is deprecated, so everything here exists to make a
 * manual copy-paste clean: convert the stored plain text into HTML that
 * Substack's ProseMirror editor will accept without mangling, and surface the
 * things you must not paste by accident.
 */
final class Post
{
    /**
     * Tags ProseMirror accepts. Anything outside this list is silently dropped
     * or rewritten by Substack's editor on paste, so the serializer targets the
     * whitelist rather than emitting general-purpose markup.
     */
    public const SAFE_TAGS = ['h2', 'h3', 'p', 'strong', 'em', 'ul', 'ol', 'li', 'blockquote', 'a'];

    /** Placeholders that must never reach a published post. */
    private const PLACEHOLDERS = [
        '/\[shoutout:\s*_*\s*\]/i'  => 'unfilled reader shoutout',
        '/\[[A-Z_ ]{3,}\]/'         => 'unreplaced ALL-CAPS placeholder',
        '/_{4,}/'                   => 'blank line left in the body',
    ];

    /** Plain-text draft -> ProseMirror-safe HTML. */
    public static function toHtml(string $body): string
    {
        $lines = preg_split('/\R/', trim($body));
        $out = [];
        $list = null;              // 'ul' | 'ol' | null
        $para = [];

        $flushPara = static function () use (&$para, &$out): void {
            if ($para) {
                $out[] = '<p>' . self::inline(implode(' ', $para)) . '</p>';
                $para = [];
            }
        };
        $closeList = static function () use (&$list, &$out): void {
            if ($list) {
                $out[] = "</$list>";
                $list = null;
            }
        };

        foreach ($lines as $line) {
            $t = trim($line);

            if ($t === '') {
                $flushPara();
                $closeList();
                continue;
            }

            // "Go!" is the standing call-to-action heading in every draft.
            if (preg_match('/^(Go!|Go\.|Your move\.?)$/i', $t)) {
                $flushPara();
                $closeList();
                $out[] = '<h2>' . self::inline($t) . '</h2>';
                continue;
            }

            if (preg_match('/^[-*•]\s+(.+)$/u', $t, $m)) {
                $flushPara();
                if ($list !== 'ul') {
                    $closeList();
                    $out[] = '<ul>';
                    $list = 'ul';
                }
                $out[] = '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.+)$/', $t, $m)) {
                $flushPara();
                if ($list !== 'ol') {
                    $closeList();
                    $out[] = '<ol>';
                    $list = 'ol';
                }
                $out[] = '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }

            // A short standalone line between blanks reads as a sub-heading.
            if ($para === [] && $list === null && mb_strlen($t) < 60
                && !preg_match('/[.!?,;:]$/u', $t) && str_word_count($t) <= 8) {
                $out[] = '<h3>' . self::inline($t) . '</h3>';
                continue;
            }

            $closeList();
            $para[] = $t;
        }

        $flushPara();
        $closeList();

        return implode("\n", $out);
    }

    /** Escape, then allow only bold and italic. No inline styles, no classes. */
    private static function inline(string $s): string
    {
        // ENT_NOQUOTES: this is text-node content, never an attribute value, so
        // < > & still get escaped while apostrophes and quotes stay literal.
        // Escaping them produced &#039; noise all through the pasted body.
        $s = htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/u', '<em>$1</em>', $s);
        return $s;
    }

    /** Things that would embarrass you if pasted. @return string[] */
    public static function warnings(string $body): array
    {
        $found = [];
        foreach (self::PLACEHOLDERS as $pattern => $label) {
            if (preg_match($pattern, $body)) {
                $found[] = $label;
            }
        }
        return $found;
    }

    /** First sentence of the body, as a starting point for the subtitle. */
    public static function suggestSubtitle(string $body): string
    {
        $first = '';
        foreach (preg_split('/\R/', trim($body)) as $line) {
            if (trim($line) !== '') {
                $first = trim($line);
                break;
            }
        }
        if (preg_match('/^(.{20,160}?[.!?])(\s|$)/u', $first, $m)) {
            return trim($m[1]);
        }
        return mb_substr($first, 0, 160);
    }

    public static function markPublished(int $id, ?string $url, ?string $when): void
    {
        Database::run(
            "UPDATE af_posts
                SET status='published',
                    substack_url = NULLIF(?, ''),
                    published_at = COALESCE(NULLIF(?, ''), NOW())
              WHERE id = ?",
            [$url ?? '', $when ?? '', $id]
        );

        // Clear the write-about mark so the queue stays honest. Without an API
        // there is no confirmation from Substack, so this is the only signal.
        Database::run(
            "DELETE FROM af_triage WHERE subject_type='post' AND subject_id=? AND mark='write_about'",
            [$id]
        );
    }
}
