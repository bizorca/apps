# Numbrella — Project Context for Claude

## What this is
Self-serve business valuation reports for first-time business buyers. A three-step wizard (business basics → up to three years of financials → market position and risk) produces a valuation range from SDE multiples, revenue multiples and an illustrative DCF, an asking-price verdict, risk flags, a 3-year projection, scenario analysis, industry benchmark context and a PDF.

**Lives at** https://tools.bizorca.com/numbrella/ as one tool on the shared tools platform. Read `../CLAUDE.md` (the repo root) first: shared account, shared MySQL, deploy.
**Ported 2026-10-08** from `~/Dropbox/Archipelago/numbrella`, which was never deployed (no DNS, nothing on SiteGround, not a git repo, empty `vendor/`). No data to import.

## Assessment of the original (2026-10-08)
**Verdict: mostly works, but was never runnable as shipped, and the numbers could mislead a buyer.** With dependencies installed and the Stripe webhook emulated, the wizard, valuation, report page and PDF all worked end to end for three fixtures, and the arithmetic matched a hand calculation to the dollar. What stood in the way, most serious first:
1. **Owner pay was double-counted.** The wizard told users to enter net profit *before* owner salary or draws, and then SDE added the owner salary on top. A sole proprietor following the instructions got their draws counted twice. (The CLAUDE.md sanity check assumed the opposite, net *after* salary.)
2. **Loss-making businesses got a negative "value".** Negative SDE drove the DCF negative, so the consensus range came out as -$10,619 to $5,946 with the midpoint below the low.
3. **Missing years counted as zero profit.** A two-year-old business had its weighted SDE halved (a $90k-SDE business valued on $45k). The growth rate also assumed a two-year span regardless.
4. **The DCF inflates the consensus.** It discounts SDE (owner's pay still in it, no capex or working capital) at 25% with a 3× terminal value, which works out to about 3.1–4.3× SDE in every industry, above most industries' SDE ceilings. It carried 25% of the midpoint (restaurant sanity case: DCF $514k against an SDE midpoint of $252k).
5. **Inconsistent consensus weights**: the midpoint used 50/25/25, the low and high 60/25/15.
6. **Couldn't install**: `composer.json` pinned dompdf ^2.0, which Composer now refuses (ten security advisories); `src/` autoload pointed at a folder that doesn't exist.
7. **Payment flow untestable and racy**: Stripe's success URL landed on the report before the webhook, which bounced the buyer back to checkout; the pending payment row was patched by "latest pending row for this report"; raw Stripe errors were shown to users.
8. **Wizard bug**: re-saving step 1 of an existing draft created a duplicate report (the form posted the id in a hidden field, the page read only the query string).
9. **Premium promised two things that were never built**: "Industry benchmark context" and "Detailed methodology footnotes" (both tiers got identical methodology notes).
10. Smaller: open redirect via `?return=` on the SSO callback; `session_start()` on every request (cookie for every visitor); a declining business's premium projection started from the 3-year average, so Year 1 showed a rebound; "$-12,000" money formatting; multiples hardcoded from 2023 and never re-verified.

## What the port changed
- **Platform**: shared tools account (Bizorca SSO and its client dropped), `nb_` tables keyed to the shared `users`, base path `/numbrella`, session keys `nb_`, lazy session (anonymous visitors get no cookie), CSRF via `tl_csrf_*`, security headers sent from PHP (nginx ignores `.htaccess`), PHP 8.2 syntax.
- **Math** (all in `includes/valuation.php`, header comment lists each): owner-pay inputs relabelled so the formula is right; blank years are NULL and drop out; growth uses the real span; no positive weighted SDE → consensus $0 with an explanation; DCF floored at zero; one set of weights (60/25/15) for low, mid and high; projection starts from the latest year and holds a loss flat; a warning when the revenue multiple carries most of the range; new risk flags for "not making money" and "incomplete financial history".
- **Wizard**: id read from POST or GET (no duplicates); most recent year's revenue required; editing a finished report sends it back to draft until step 3 re-runs it (the report page reads some figures from the input columns, so they must not drift from the stored valuation); delete from the dashboard.
- **Built the missing premium item**: Industry benchmark context (SDE margin, industry ranges, the multiples the asking price implies and where they fall).

## Payments: off (every report is free and complete)
`NB_PAYMENTS_ENABLED` in `private_html/.env.php`, default **false**. While false, saving wizard step 3 finalizes the report as `premium`: no tier picker, no upsell, no locked sections; `checkout.php` redirects back into the wizard and `webhook.php` answers 404.

Premium-only content now unlocked for everyone: **3-year cash flow projection**, **scenario analysis at five price points**, **industry benchmark context**. ("Detailed methodology footnotes" never existed; both tiers always had the same methodology notes.)

The old tiers, for when pricing returns: **Standard $29** = SDE, revenue and DCF methods, consensus range, asking-price verdict, risk flags, methodology, PDF, share link. **Premium $99** = Standard + the three sections above.

To turn payments on: set `NB_PAYMENTS_ENABLED => true`, optionally `NB_PRICE_STANDARD_CENTS` / `NB_PRICE_PREMIUM_CENTS` (default 2900 / 9900), register a Stripe webhook endpoint `https://tools.bizorca.com/numbrella/webhook.php` for `checkout.session.completed` and put its secret in `NB_STRIPE_WEBHOOK_SECRET`. Stripe is REST over curl through the shared `tl_stripe()` (same account and `STRIPE_SECRET_KEY` as the membership); signatures are verified with a 5-minute window; handling is idempotent on the session id and the tier is set from `amount_total`, not from metadata. Bought reports are final (no editing). **Not exercised against Stripe**: checkout session creation and the checkout page with payments on. The webhook handler and signature check were tested locally with signed fake events.

Separately, `tl_has_access('numbrella')` is not wired in; if Numbrella becomes a membership tool instead of per-report, that is the seam (`report.php` / `wizard.php` step 3).

## Layout
```
public/              -> public_html/numbrella/
  _bootstrap.php     finds NB_ROOT, loads config
  index.php          landing (signed-in users go to dashboard)
  dashboard.php      own reports; delete (POST)
  wizard.php         ?step=1|2|3&id=N; step 3 finalizes (or goes to checkout when payments are on)
  report.php         ?id=N owner/admin, or ?token=<64 hex> read-only share link (no login, no cookie, no PDF)
  report-pdf.php     ?id=N owner/admin only; 404 for anyone else
  admin.php          site admins: all reports, search, counts
  checkout.php       DORMANT tier picker -> Stripe Checkout
  webhook.php        DORMANT Stripe webhook
includes/            -> private_html/numbrella/includes/
  config.php         loads shared core; constants, helpers, findOwnReport(), finalizeReport()
  valuation.php      the engine (pure functions)
  multiples.php      industry SDE / revenue multiple ranges (2023, unverified)
  risk.php           risk flags (pure)
  pdf.php            render + cache PDFs
  payments.php       DORMANT Stripe checkout + webhook handling
  vendor/            dompdf 3.1.6 + deps, hand autoload (see vendor/autoload.php)
templates/           header, footer, pdf/report.php (inline CSS for dompdf)
migrations/001_numbrella.sql   nb_reports, nb_payments
```
Valuation and risk flags are computed once at finalize and stored as JSON; viewing never recomputes. Changing the engine does not change old reports until they are re-run.

## PDFs
Rendered on first download, cached at `private_html/data/numbrella/<user_id>/report-<id>.pdf` (never under the web root; `data/` is gitignored and never deployed over), deleted whenever the report is re-run, edited or deleted. dompdf's font metric cache goes to `private_html/data/numbrella/dompdf/` because the vendored `lib/fonts` is owned by the deploy user. Remote fetches, PHP and JS are off in dompdf. Deleting a user account cascades their DB rows but leaves their PDF folder behind.

## Vendored libraries
No Composer on the server. `includes/vendor/autoload.php` maps dompdf/dompdf 3.1.6 (LGPL-2.1), dompdf/php-font-lib 1.0.2 (LGPL-2.1+), dompdf/php-svg-lib 1.0.2 (LGPL-3.0+), masterminds/html5 2.11.0 (MIT), sabberworm/php-css-parser 9.5.0 (MIT, needs ext-iconv). Each folder keeps its license; only `src/` (plus dompdf's `lib/` fonts and resources) was copied. About 10 MB, mostly DejaVu fonts. To upgrade: `composer require dompdf/dompdf:^3.1` in a scratch folder, copy the same folders over, keep the autoload map in step.

## Local dev
From the repo root: `php -S 127.0.0.1:8100 dev-router.php`, then http://127.0.0.1:8100/numbrella/. Needs local MySQL `bizorca_tools` and `php private_html/bin/migrate.php`. Register an account at /account/register.php.

## Known limitations (not fixed)
- The DCF still discounts SDE with nothing deducted for a replacement manager, capex or working capital; it is labelled illustrative and carries 15%.
- With thin but positive earnings the revenue multiple can carry most of the range (a warning says so).
- Industry multiples are 2023 figures as cited by the original author, not re-verified.
- No asset-based value for loss-making businesses; the report says so instead of guessing.
