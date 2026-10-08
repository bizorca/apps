<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Core;

/**
 * Content-Security-Policy, with a per-request nonce (SPEC §6).
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS ACTUALLY BUYS
 *
 * A nonce-based script-src is the one CSP directive that reliably turns a
 * successful HTML injection into a non-event. An attacker who gets `<script>`
 * into a page cannot guess the nonce, so the browser refuses to run it. That is
 * the whole point; everything else here is secondary.
 *
 * WHAT IS DELIBERATELY NOT LOCKED DOWN, AND WHY
 *
 * `style-src` keeps 'unsafe-inline'. The Tailwind Play CDN compiles at runtime
 * and injects a <style> element it creates itself, which cannot carry our
 * nonce. Removing it would mean adding a build step, and no build step is a
 * stated constraint of this project (SPEC §7). The trade is worth naming rather
 * than hiding: injected CSS can restyle a page and, with enough effort, help
 * exfiltrate the contents of form fields. It cannot execute script. Script is
 * where the damage is, and script is locked.
 *
 * There is NO 'unsafe-eval'. There was going to be — Alpine evaluates its
 * expressions with the Function constructor — until it turned out Alpine was
 * loaded on every page to power exactly one accordion, which the browser
 * implements natively as <details>. Dropping it removed a CDN request from
 * every page load and an entire CSP exemption. Do not add Alpine back without
 * reckoning with that.
 *
 * WHEN YOU ADD A SCRIPT
 *
 * Every <script> tag this application emits, inline or external, must carry
 * `<?= Csp::attr() ?>`. A script without it will silently not run, which is the
 * correct failure — noticing it in development is the system working.
 * ---------------------------------------------------------------------------
 */
final class Csp
{
    private static ?string $nonce = null;

    /**
     * The nonce for this request, generated once.
     *
     * 16 bytes. The spec asks for at least 128 bits of entropy, and the whole
     * mechanism rests on it being unguessable within the life of one response.
     */
    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    /** Ready to drop into a <script> tag. */
    public static function attr(): string
    {
        return 'nonce="' . h(self::nonce()) . '"';
    }

    /**
     * The policy.
     *
     * Assembled here rather than in .htaccess because the nonce changes every
     * request and Apache cannot generate one. The .htaccess headers use
     * `setifempty` without `always` precisely so PHP-set headers win — see the
     * note in CLAUDE.md about duplicate headers.
     */
    public static function policy(): string
    {
        $nonce = "'nonce-" . self::nonce() . "'";

        $directives = [
            "default-src 'self'",

            // Nonce plus the two CDNs we actually load from. Note that once a
            // nonce is present browsers IGNORE 'unsafe-inline' for this
            // directive, so there is no point listing it and no way for an
            // injected inline script to run.
            "script-src 'self' {$nonce} https://cdn.tailwindcss.com https://cdn.jsdelivr.net",

            // See the class comment. Tailwind's runtime injects style elements
            // it creates itself; they cannot carry a nonce.
            "style-src 'self' 'unsafe-inline'",

            // data: for the TOTP QR, which is generated in the browser and must
            // never be sent to a third-party chart service — the secret would
            // go with it.
            "img-src 'self' data: https:",

            "font-src 'self' data:",

            // Nothing is posted anywhere but here.
            "connect-src 'self'",

            // No plugins, no embedding, no <base> games.
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",

            /**
             * A form that posts off-site is either a bug or an exfiltration.
             *
             * 'self' covers everything since the move to tools.bizorca.com:
             * signup, every firm (a path, no longer a subdomain) and the shared
             * /account pages are all on one host.
             */
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Send it, unless something already has.
     *
     * Skipped for non-HTML responses. A CSP on a CSV download or an .ics file
     * is noise, and headers_sent() means a response is already streaming and it
     * is too late to be useful.
     */
    public static function send(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Content-Security-Policy: ' . self::policy());
    }

    /** Test seam: forget the nonce so a fresh one is generated. */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
