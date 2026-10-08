# Claude Instructions — Anglerfish Press

Content production desk. Ingests books, extracts structured knowledge, generates
standalone visual assets, and turns all of it into finished work.

**Read `SPEC.md` before making changes here.** It is the authority; this file is the
quick reference.

- **Domain**: anglerfish.bizorca.com
- **SSO slug**: `anglerfish`, `full` entitlement required on every route
- **Mascot**: male deep-sea anglerfish. Hosted app is the female (large, stationary,
  carries the lure); the local worker is the male (small, attached, does the work).

## Architecture in one paragraph

Hosted PHP app holds the database, UI, job queue, and finished assets. A local Python
worker on the Mac polls it outward over HTTPS, leases jobs, and posts results back —
because SiteGround kills background processes and OCR, image generation, and rendering
all take minutes. Extraction does not run in either one; it happens in Claude Code CLI
and arrives as files (see below).

## In flux — do not treat as settled

**Jassen intends to keep tweaking the image-generation workflow and the prompt
that drives it.** Treat everything under "Gemini image generation" — the art
direction call, `ImagePrompt`, the structure list, the generate page — as a
current position rather than a finished design. Before optimising, hardening or
building on top of any of it, ask; the shape is likely to move.

The same applies to the surrounding workflow: where image generation sits in the
post-draft flow, what happens on the generate page, and how a chosen direction
gets reused.

Known open threads on that path, as of 2026-09-17:

- **Opus direction ran for real on 2026-09-17** once the self-set spend limit
  was raised to $50/month: chapter 2365 directed in 53s, five stages with
  minute counts as figures, rendered clean with the footer. A direction is
  closer to 5¢ than the 1¢ older notes claim (~3K in, ~1.5K out on Opus 5), so
  the 96-chapter backfill is about $5 of Claude plus $6.43 of Gemini.
- **The footer is `© <year> bizorca.com. All rights reserved.`** on the right,
  built by `ImagePrompt::rightsLine()` with the render year, and the source
  line on the left. Nothing else may appear on the bottom strip.
- **Art direction is not persisted** outside a fired asset's `prompt_snapshot`
  and `direction` on the job payload. You cannot hold two directions side by
  side and choose before spending $0.134.
- Text rendering is trusted to Gemini. SPEC §9.4 says exact text belongs in a
  deterministic render; both the brand URL and now the source line ride on
  the model getting it right. Verified clean on every test so far, and the
  fix if it ever slips is a PIL overlay after generation, not a longer prompt.
- The 400 v2 images in `/review` are all still the old register. Redraw
  re-directs, so R on any of them produces a v3 image; nothing bulk-redraws.

## Hard rules

1. **PHP 8.2 only.** SiteGround runs 8.2 — never 8.3+ syntax.
2. **PNG and JPEG only. Never WebP.** Anywhere. PNG for flat color, text, transparency;
   JPEG q88 for photographic and illustrated output. No exceptions, no "just for
   thumbnails."
3. **No Anthropic API in the worker, and never for extraction.** Extraction runs in
   Claude Code against the subscription — no `anthropic` package on the Mac, no
   extraction API cost. The *server* uses the Anthropic API for the composer only.
4. **Verbatim artifacts never get republished.** Stored intact for internal reference;
   the composer transforms rather than reproduces. Enforced in prompt assembly.
5. **The angle is optional; the no-invention rule is not.** The angle was mandatory
   for book-sourced material and no longer is — every post is hand-edited, and an
   editor catches book-report register better than a required field. But when the
   angle is empty the prompt must keep telling the model to invent nothing personal:
   no clients, no anecdotes, no numbers absent from the source. Flat register is
   obvious on the first read; a fabricated client is not.
6. **Tailwind + Alpine via CDN.** No build step, no npm.
7. **No public surface.** No landing page, marketing copy, signup flow, or pricing page.
   `public/index.php` is the login prompt: unauthenticated redirects to SSO, authenticated
   goes to the dashboard. Single operator — do not build roles, invitations, or user admin.

## Who writes what

| Job | Runs on | Why |
|---|---|---|
| Composer (posts) | **Claude `claude-opus-5`**, server-side | Voice matching against a long style guide |
| Image generation | Gemini, server-side | Claude does not generate images |
| Extraction (4 passes) | Claude Code CLI, by hand | Subscription, not per-token; verbatim needs human judgment |
| Corpus search, render | Neither | grep and headless HTML — no model, no cost |

## One corpus (the 2026-08-09 fold)

Dan Kennedy Brain was folded into Marketing Brain. There is now **one** corpus:
`Marketing Brain/Text/` with 38,067 files (38,064 at the fold; three recovered
transcript parts were added 2026-10-03), one index (`text-index.json`), and
`brain` is `mb` everywhere. The `dk` value survives only as an accepted input
that resolves to `mb`, so old links and payloads do not break.

Why: DK Brain was never all Kennedy — only ~2,100 of its 10,131 files carried a
Kennedy prefix, the rest being GKIC, Information Marketing Association, Magnetic
Marketing, Bill Glazer, Rory Fatt, Halbert and Kern material — and Marketing
Brain already held a separate `Gurus/Dan Kennedy` folder. Two corpora meant one
author split across both.

Routing was rule-based (`fold_dk_into_mb.py`, ordered, first match wins) with
Kennedy as the default, since the corpus was assembled as Kennedy material. GKIC
and Magnetic Marketing count as Kennedy per operator instruction. The
Information Marketing Association went to `Topics/Info Marketing` rather than
Kennedy — it is Kennedy-affiliated but not Kennedy-authored, and filing 798 files
under his name would produce wrong attributions in published posts.

Result: `Gurus/Dan Kennedy` 3,032 → 11,787, plus Bill Glazer (309), Rory Fatt
(219), Swipe Files (+36), Frank Kern (+14).

Three traps, all hit:

- **Underscores are word characters.** `\bdan kennedy\b` and `\bgkic\b` never
  matched `Dan_Kennedy_-_...` or `_GKIC ...`. 4,400 files reached Kennedy by
  default rather than by rule, which would have hidden any misfiled Glazer or
  Fatt file. Normalise `[_+]` to spaces before matching.
- **`rglob` swept the whole tree**, including the 13 Big Ideas syntheses at the
  DK root, which are not corpus files. Recovered from the manifest.
- **Restoring by filename over-corrected** — ten real corpus files are *called*
  "8 Big Ideas - CD1" and similar. The discriminator is whether the source sat at
  the DK root or under `Text/`.

`fold_dk_manifest.csv` records every move. **The script is retired and `--undo`
is disabled** (2026-10-03): `writer/Dan Kennedy Brain/` has been removed, and the
index, the `sources` table and later additions all depend on the files staying
put. Script and manifest now live in
`Marketing Brain/Dan Kennedy sources/fold-2026-08-09/`.

**The folder itself was removed on 2026-10-03.** What was left in it (16 PDFs,
15 transcript ZIPs, the retired transcription job, the old index) is in
`Marketing Brain/Dan Kennedy sources/`. Checking those against the corpus found
three parts of a four-part bootcamp transcript that a same-named-member ZIP had
dropped, and two scanned PDFs with empty text; all were added or OCR'd, and
`import_sources` was re-run (job 894), taking `sources` to 38,067 rows.
`import_big_ideas.php` no longer carries a `dk` label: a `dk` record resolves
to `mb` there as it does everywhere else.

**Big Ideas: 33 files → 22, 580 sections unchanged.** The 11 same-named Kennedy
syntheses were appended to their Marketing Brain counterparts under a
`FROM THE DAN KENNEDY CORPUS` divider; Consulting and Speaking had no
same-named counterpart and were copied across. The 13 `big-ideas-dk-*` books
were deleted after re-import, since their content now lives in the merged files.

## Big Ideas are books

The 33 `Big Ideas - *.txt` syntheses (20 Marketing Brain, 13 Dan Kennedy) are
imported as **books**, not as a parallel structure: section → page (text +
FULLTEXT), → chapter (`confirmed`, since these are the author's own headings),
→ concept (`kind=big_idea`, the composable unit). 580 sections total.

Mapping them onto `books` rather than new tables means the library view, the
reader, extraction, compose-from and image generation all worked on day one —
and `requiresAngle()` fires automatically, because a concept with a `book_title`
already demanded an angle. That is correct: this is other people's material.

```
python3 press/worker/parse_big_ideas.py > bi.json    # local; the .txt files live in writer/
scp bi.json bizorca-sg:.../anglerfish/ && php import_big_ideas.php bi.json
```

**Do not reorganise the corpus folders by topic.** The Big Ideas files already
*are* the topic index, and topic is a query dimension rather than a location:
`sources.topics` is a JSON array because a file belongs to several topics at
once, which a folder cannot express. Moving files would also collapse the
existing three-bucket taxonomy (Gurus/Topics/Library), break the guru
attribution the posts depend on ("Kennedy calls this…"), and invalidate all
38,086 `sources.path_hash` rows.

**The parser is not naive, deliberately.** These 33 files were synthesised at
different times and drifted: bullet indents of 0/2/3/4/8 spaces, five different
heading conventions (numbered CAPS, `SECTION n:`, Roman numerals, bare CAPS,
none), and seven DK files with no bullets at all. A bullet-level parser silently
produced garbage for most of them; sections are the unit that every file agrees
on. Bare-CAPS headings need blank lines on both sides and no trailing stop, or
emphasised sentences get promoted to headings.

## Context expansion

`Services\Expand` pulls supporting detail from the *rest* of the library when
composing — the other brain's take on the same topic, plus any scanned book that
covers it. One FULLTEXT query over 12,717 pages; no job, no wait. Enabled by a
checkbox on the compose form, retrieved at enqueue time so the payload records
exactly what the model saw.

Selection is left to the composer: retrieve generously, label with provenance,
and tell it to ignore what does not fit. A second model call to pre-filter would
double latency to do what the first call does for free.

Two filters exist because the first run returned sludge:

- **`readable()`** rejects OCR wreckage. Half the library is scanned, and a page
  of mangled running heads matches keyword search as happily as real prose.
- **Two-term overlap** — natural-language mode returns pages sharing one common
  word. The stopword list is aggressive for the same reason (`best`, `ones`,
  `come`, `source`… all dragged in finance textbooks).
- **`cleanLabel()`** drops OCR-mangled chapter titles from the attribution
  string while keeping the passage, since the body is often fine.

## Claude — the composer

Official PHP SDK, vendored. `composer require` needs `guzzlehttp/guzzle` too: the SDK
ships no PSR-18 transport and fails at *construction* without one.

```
composer install            # after any pull that touches composer.lock
./deploy.sh                 # rsyncs vendor/ — do not hand-rsync, see below
```

- **`composer.json` pins `platform.php` to `8.2.33`.** Local PHP is 8.5; without the
  pin, Composer resolves packages the server cannot parse and it fails in production.
- **`maxRetries: 0`, timeout 90s.** The SDK default of 2 retries would turn one
  timeout into 270s and get the PHP process killed past its 120s cap, leaving the
  job's lease dangling for the full 600s. The job queue does the retrying.
- **A refusal is an HTTP 200**, not an exception — `stop_reason: "refusal"` with empty
  content. Checked before reading blocks.
- **Thinking blocks come first and carry no text.** Only `type === 'text'` is the answer.
- The system prompt (voice + formats + output contract) is cached — 1,485 tokens read
  back at ~0.1×. Anything per-post goes in the user turn or the cache write is wasted.
- Escape hatch: `provider=gemini` in the job payload. No UI, by design.

`./deploy.sh` now syncs `vendor/`, `cli.php`, `smoke.php`, `composer.json` and
`composer.lock` as well as the app dirs. It did not before, which is how a migration
once shipped without being run — deploy.sh runs `migrate.php`; a hand-rsync doesn't.

## Gemini image generation

Access is the **Gemini Developer API with an AI Studio key** — not Vertex AI, not a
Cloud Run gateway. A Google Cloud project is needed only for billing.

- **No image model has a free tier.** The project must be on the paid tier.
- Key lives in `worker/.env` at mode 600. Server never holds it.
- Paid tier also means prompts are not used to train Google's models, which matters
  because prompts carry text from in-copyright books.
- A Cloud Run "gateway" from AI Studio's app builder will **not** work headlessly — it
  is gated behind AI Studio browser-session cookies and 302s any non-browser request.

Models and standard pricing:

- `gemini-3-pro-image` — Nano Banana Pro. $0.134 at 1K–2K, $0.24 at 4K. **The default.**
- `gemini-3.1-flash-image` — Nano Banana 2. $0.067 / $0.101 / $0.151 at 1K / 2K / 4K.
- `gemini-3.1-flash-lite-image` — Nano Banana 2 Lite. $0.034 at 1K.
- `gemini-2.5-flash-image` — legacy, unused.

Pro is the default because two attempts on Nano Banana 2 at 2K ($0.202) cost more than
one on Pro ($0.134). Pro pays for itself if it cuts the reroll rate to two-thirds.

Current API shape — the **Interactions API**, not the older `generate_content`:

```python
from google import genai
client = genai.Client()                       # reads GEMINI_API_KEY
interaction = client.interactions.create(
    model="gemini-3-pro-image",
    input=prompt,
    response_format={"type": "image", "aspect_ratio": "16:9", "image_size": "2K"},
)
image_bytes = base64.b64decode(interaction.output_image.data)
```

Generate at 2K, downscale to 1456px wide for Substack. 16:9 at 2K returns 2752×1536.

**Gemini picks the container, so sniff it.** It returns JPEG for illustrated output even when the request says nothing about format; the old code hard-coded `.png` and wrote JPEG bytes into a `.png` name with `format='png'` in the DB. `generateImage()` now reads the magic bytes, names the file accordingly, records the real format, and throws on WebP rather than storing it.

**Claude art-directs; Gemini draws.** A fixed prompt template produces fixed
compositions — every concept got the same title bar over the same left-to-right
flow, because that is what the template said. `ArtDirection` (Opus 5, structured
output, ~1¢, ~14s) reads the concept and decides the metaphor, composition, exact
labels, takeaway and accent colours; `ImagePrompt::fromDirection()` wraps that in
the parts that must never vary — brand anchors, attribution, text discipline, the
avoid list. Claude cannot drift the brand because it is never asked to decide it.

**Stylization loosened 2026-09-04, then reversed 2026-09-17.** For six weeks
the wrapper let Gemini choose the rendering — any illustration style, any
canvas, the two lines of type wherever the composition wanted. The measured
variance was real (a shopkeeper at a mirror, a climber, a podium on a launch
pad), and it was the wrong axis: every image converged on dusk lighting over
navy water with gold light, and none of them explained anything. v3 below
fixes the rendering again — white canvas, tinted panels, one flat editorial
style — and puts the variance where it belongs, in the layout.

**v3, 2026-09-17: information design, not illustration.** This is the third
position on what an infographic is, and it reverses the second. v1 (August)
asked for a headline plus bullets and got three columns of text with clip-art
icons. v2 swung to one metaphor scene with at most seven six-word labels, and
after 400 images the verdict was: handsome and mute. Cover the labels on most of
them and you cannot say what they argue — the labels carried the idea and the
picture decorated it, which is the exact failure the format exists to avoid.

The models for v3 are the explainer graphics James Orr runs at
RealEstateFinancialPlanner.com — ten of them are saved in
`writer/screenshots/infographic-reference/james-orr-*.jpg` beside the two v3
proofs and their direction JSON, to compare against if the register drifts. What they do that v2 did
not: the **layout carries the argument** — two columns say "comparison" before
a word is read; the **text does real work** in a hierarchy of fifteen to
thirty elements (headline, sub-headline, section headers, labels each with a
detail line, real figures shown large, a boxed **THE TAKEAWAY**); illustration
is **small spot art inside tinted panels**, not the panel; the canvas is
**white, daylight, flat**; and the footer carries a **source line** — book,
chapter, author — beside `bizorca.com`.

What v1 got wrong was never the amount of text. It was the absence of
structure, hierarchy and real objects.

- `ImagePrompt::LAYOUTS` is the grammar: `levels`, `columns`, `stages`,
  `journey`, `hub`, `before_after`, `annotated_scene`. The director picks one.
  `annotated_scene` is the one that goes mute and the prompt says so.
- `ArtDirection::SCHEMA` asks for `layout`, `headline`, `subheadline`,
  `glance`, `composition`, `scene`, `sections[{header, items[{label, detail,
  figure, draw}]}]`, `takeaway`, `accents`, `avoid`. **`glance` is the test**:
  what a viewer concludes with every word covered. If it cannot be written, the
  layout is wrong.
- **Labels name the real-world thing, never a piece of the metaphor.** "Card on
  file, paid automatically" is a label; "Frayed ends, nothing returns" is not.
- **`figure` is verbatim from the material or empty.** The one number on the
  Sacred Economics proof, "300 visits a month", is in the chapter.
- **The source line is built by `ImagePrompt::sourceLine()` from stored
  fields** — `book_title`, `authors` (via `book_authors`), `number_label`,
  and the book's `kind` — never by the model, which is what once produced
  "(SENOFF/BODRI)". Scanned books get one; Big Ideas syntheses (no author)
  and posts do not. More than two authors becomes "et al.".
- `STRUCTURES` survives as the generate page's picker and the
  `posts.gemini_structure` enum, each key mapped onto a layout. The template
  path (`draft()`) now builds a v3 direction mechanically and renders through
  the same wrapper, so the free path and the directed path cannot drift apart.
- `fromDirection()` still accepts a v2 block (`elements`, no `sections`) by
  folding it into one section, because directions are snapshotted on assets
  and stored on posts.
- The authored pass (`bizorca-drafts/prompts/01-art-direction.md`,
  `validate_direction.py`, `import_drafts`) is on the v3 shape. The importer
  derives `gemini_structure` from `layout`; the one directed draft (001) was
  rewritten and re-imported.

Two proofs on 2026-09-17, hand-directed, rendered on `gemini-3-pro-image` at
2K in ~32s each: *Selling With Integrity* (levels, 22 text elements) and
*Working in the Gift* (columns plus an examples band, 27). Every string
rendered exactly. Gemini's one liberty was a two-word annotation on a drawn
invoice lifted from a detail line — the kind of thing `/review` exists for.

Every generated image carries `bizorca.com` on the bottom edge, specified in `ImagePrompt::fromDirection()`. Spelling is stated explicitly in the prompt because a misrendered brand URL is both embarrassing and easy to miss — verified rendering correctly at 2K. If it ever does come back misspelled, the fix is a deterministic PIL overlay after generation, not a longer prompt.
**The footer credit is selectable, added 2026-09-28.** `rightsLine()` was
hardcoded to bizorca.com, which was right while this app served one
publication. It now serves two, and a Hypnologue graphic came out of the same
pipeline stamped "© 2026 bizorca.com" — a quiet error, because the image looks
finished and the footer is the last place anyone reads.

- `ImagePrompt::ATTRIBUTIONS` is the list (key, label, line with `{year}` filled
  at render time). `rightsLine($key)` resolves a key, passes an unknown
  non-empty value through verbatim, and defaults to bizorca, so every existing
  caller is unchanged.
- `fromDirection()` takes it as an optional fourth argument.
- Dropdowns on the generate screen and in the post editor. Both controllers
  validate against the list rather than trusting input — an unrecognised key
  would otherwise fall back silently to the wrong publication's copyright,
  which is the failure that started this.
- **Migration 016 adds `posts.attribution`** so a post remembers its own and the
  generate screen defaults correctly. `PostController::update` writes that
  column, so **the code and the migration must ship together** — a
  templates-only rsync breaks saving. `deploy.sh` runs `migrate.php`, so a
  normal deploy is fine.
- A one-off render sets its own footer with a `RIGHTS:` header in the draft
  file; `press/worker/render_direction.py` passes it through.

Adding a third publication is a two-line edit to the array; both dropdowns and
the validation pick it up.

All Gemini output carries a SynthID watermark.

## Batch image generation

**One infographic per chapter, ordered at half price and answered later.** Built
and run end to end on 2026-09-04. `gemini-3-pro-image` supports
`batchGenerateContent` (confirmed against the live key, not a blog post), batch
is 50% of standard — $0.067 a 2K image against $0.134 — and results keep for six
weeks. The money is not the reason to use it: the whole library is 494 chapters
and $33 of savings. The reason is that 494 is not a number of buttons anyone is
going to click.

```
ssh bizorca-sg "$A php cli.php images --book=the-90-day-coach --dry-run"
ssh bizorca-sg "$A php cli.php images --book=the-90-day-coach"
ssh bizorca-sg "$A php cli.php batches [--book=slug]"
```

Then review at `/library/<slug>/images` — the contact sheet, linked from the book
page. Reject costs nothing; **Reject & redraw** orders another at $0.067 with a
fresh art direction, which is the point: the same summary directed twice does not
come back the same picture.

### The shape, and why it is four steps instead of one

`generate_image` fires, waits 40 seconds and writes an asset inside one lease.
Batch cannot work that way, so the run is `plan → art_direct ×N →
image_batch_submit → image_batch_poll`, and no single step runs longer than one
Claude call.

- **Chapters are the unit, and the summary is the input.** Pass 2's core idea
  plus its 150–300 word summary is exactly what an art director can hold in one
  read. `Composition::subject('chapter', …)` returns the chapter's *pages* —
  thousands of words of OCR — which is the wrong input here.
- **Asset rows exist before the pixels.** Each carries `batch_key` (`ch<id>`),
  which is what matches a returned image back to a chapter. Rows start `pending`.
- **Submit defers, it does not fail, while directions are still running.** A
  failure spends an attempt and eventually kills the job; a deferral re-queues a
  fresh one a minute out and costs nothing. Two hours of deferral is the giving-up
  point, since 16 directions at ~30s each cannot honestly take longer.
- **`jobs.not_before` is new.** Without it a poller re-queued for a 24-hour batch
  runs every minute for a day. Polls go 120s five times, then every 10 minutes.
- **`art_direct` gets `max_attempts=8`, not 3.** The cron window defers a job it
  cannot finish in 45 seconds, and a deferral costs an attempt — three of those
  would kill a chapter that was never broken.

### What the pilot found

- **The response nests under `metadata.output.inlinedResponses.inlinedResponses[]`,
  not under `response`.** `GeminiBatch::harvest()` walks the whole tree tracking
  the nearest enclosing `metadata.key` rather than reading a fixed path, which is
  the only reason this worked first time.
- **Every image drags a 1.2MB `thoughtSignature` back with it**, on top of ~800KB
  of base64. That is 3–5MB an entry once decoded, and it is why `CHUNK` is 16
  rather than 25 — PHP has 768MB here and json_decode holds the body and the array
  at once.
- **Art direction is ~30s a chapter on Opus 5**, not the ~14s the older note
  claims. Thirteen chapters is about seven minutes of queue, mostly unattended
  since the Site Tools cron drains it too.
- Every poll writes the operation, base64 elided, to `storage/logs/batch-N.json`.
  A response shape nothing has consumed before is worth a record.

`batch_probe.php` sends exactly one flash-lite request through the same code for
about two cents. Run it before trusting any change to the envelope.

### Draining a library-wide run

`php cli.php images --all` orders every press-scope chapter still missing an
image, grouped by book so each batch still belongs to one and the per-book
contact sheet keeps working. Reference-scope books are excluded — the Instant
Pot manuals and the Robotech novels are searchable but never reach the builder,
so illustrating them buys nothing. Run on 2026-09-04: 481 chapters, 33 books,
43 batches, $32.23.

**The Site Tools cron does not drain this queue, whatever the older note here
says.** There is no shell `crontab` on this account, and 481 art-direction jobs
sat at 481 for ten minutes untouched. Assume a big run needs driving by hand.

```
php cli.php work --types=art_direct --max=10 --window=280 --lock=drain1
```

- **`--lock=<name>`** gives a caller its own flock file, which is what lets
  several drains run at once. Safe rather than reckless: `Job::lease()` claims
  rows `FOR UPDATE SKIP LOCKED`, so concurrent workers never hand out the same
  job. The flock only ever existed to stop the cron stacking ticks on itself.
- **`--window=N`** replaces the 45-second cron window. An art direction is a
  ~28-second call, so 45 seconds fits one; a hand-run drain has no minute to
  finish inside and should keep working. Four workers at `--window=280` turn a
  four-hour serial run into about an hour.
- **Deferral no longer spends an attempt** (`Job::defer`). The window defers
  whatever it cannot start, and routing that through `fail()` burned one of
  eight attempts for work that was never tried — at 481 queued jobs the head of
  the queue gets leased and deferred every tick until it dies having never run.
- **The submit job waits on outstanding directions, not on the clock.** Its old
  two-hour stall check was sized for one book; across 43 batches the last one is
  not reached for hours, and an age-based check would have killed batches that
  were behaving perfectly. Outstanding directions mean the work is queued and
  coming; directions that genuinely fail leave the count at zero and fall
  through to the "nothing to submit" branch. A 24-hour valve remains for a batch
  orphaned by the queue itself being stopped.
- `worker/drain.sh` in the scratchpad pattern: workers reconnect per round
  rather than holding one SSH session, because a multi-hour session to SiteGround
  gets dropped and takes the run with it.

`ImageBatch::concepts()` exists and is unused. Concepts were considered for the
same treatment and held back: 3,994 of the library's 5,255 are `key_concept`
rows averaging **104 characters** — a glossary line, not an argument — and an
art director given one sentence invents the other nine tenths. `MIN_BODY` is
200 characters and the default kinds are thesis, big idea and caveat, which is
the 1,261 that carry a real argument.

### What the 481-chapter run actually hit

Ran 2026-09-04. **371 of 481 landed, $29.82.** The two failures are worth
knowing because neither was in the pipeline itself.

- **The Anthropic account hit its spend cap mid-run**, killing 96 art
  directions with a 400: *"You have reached your specified API usage limits. You
  will regain access on 2026-10-01."* Seven batches then failed with "every
  direction failed; there is nothing to submit", which is the right behaviour —
  no image money was spent on them. Raise the cap in the Anthropic console to
  finish those.
- **Three consecutive Gemini batches came back 100% `Precondition check failed`
  (code 9)** — 27 images across Sacred Economics and Surviving Debt, all
  submitted inside 30 seconds of each other, with batches on either side of them
  fine. It reads as transient rather than as content: those same prompts were
  resubmitted unchanged and drew normally.

`php cli.php images --retry` re-orders exactly that second case, reusing the
prompt snapshotted on the rejected asset. No Anthropic call, which is the whole
point when Anthropic is the resource that ran out — the expensive half of the
work is already sitting in the row. It only picks up subjects that have acquired
nothing since, so a chapter redrawn successfully is never ordered a third time.

**`unillustrated()` uses the inner alias `ax`, and that is load-bearing.** It
first used `a`, which collided with `retryable()`'s own `FROM assets a`: the
correlation `a.subject_id = a.subject_id` compared the inner row to itself, was
always true, and the NOT EXISTS then excluded every row in the table. The command
reported "nothing to retry" against 27 perfectly good rows. `chapters()` and
`allChapters()` select `FROM chapters c` and never collided, so the same helper
was correct in two callers and silently empty in the third.

### The review queue

`/review` — every infographic in the system, one per row, oldest first. Separate
from the per-book contact sheet on purpose: the sheet judges a book as a set
(thirteen thumbnails at once, to catch three chapters sharing a metaphor), the
queue works a backlog one decision at a time regardless of which book the next
row comes from. Keys are **J/K** to move, **A** keep, **R** redraw, **X** reject,
and each action posts a fetch rather than reloading a page carrying twenty 2K
images.

- **`assets.reviewed_at`, not a new status value.** Two places supersede an older
  picture with `WHERE status = 'ready'`; an `approved` status would quietly stop
  being superseded and leave a chapter with two current infographics. Migration
  014 backfills everything created before 2026-09-04 as reviewed, so the queue
  does not open with months of already-seen assets in it.
- **Redraw re-directs, it does not re-fire.** Re-sending the stored prompt returns
  the same picture with different noise. A fresh `ArtDirection` call gives the
  model a different metaphor: chapter 2371 went from stepping stones across water
  to a paved road through fog on the redraw, same argument underneath.
- **A single redraw goes interactive at $0.134, not batch.** Somebody is sitting
  there looking at the image they want replaced, and 24 hours is not a review
  loop. `applyDirection` branches on whether a `batch_id` is present.
- **`art_direct` is no longer chapter-only.** `directable()` keeps the chapter
  special case — core idea plus pass 2 summary, never the raw pages — and sends
  everything else through `Composition::subject()`, which is what lets the queue
  redraw a post or concept image too.
- The subject title comes from one query per subject type, not a five-way join:
  `assets` is polymorphic with no foreign key to any of them. A row whose subject
  was deleted keeps a `[deleted chapter 91]` placeholder rather than vanishing,
  since an orphaned image is exactly what a review queue should surface.

### The machine check before review (2026-10-05)

Gemini's misses are the kind a reader sees at a glance: a misspelled label, a
label drawn twice, a price nobody asked for, an arrow pointing at the wrong
drawing. **A Claude Code session now checks every new infographic against its
own prompt before you see it**, on the subscription, like extraction:

```
press/worker/.venv/bin/python press/worker/image_qa.py pull [--book=slug] [--limit=40]
#   then one sonnet agent per qa_runs/<run>/batches/batch-NN.md (8 images each)
press/worker/.venv/bin/python press/worker/image_qa.py status <run>
press/worker/.venv/bin/python press/worker/image_qa.py push <run> [--dry-run]
```

- **The prompt is the reference, not the spec.** `prompt_snapshot` holds every
  word the image should carry ("exactly: …" in v3, "Label it exactly …" in v2),
  so one check works on both registers. The brief is
  `worker/prompts/image-qa.md`.
- **pass keeps, fail redraws, a second fail goes to you.** `cli.php qa-apply`
  keeps a pass (sets `reviewed_at`), rejects and re-art-directs a first fail, and
  leaves a second fail on the same subject — or any `unsure` — in `/review` under
  **Flagged by check**, with the notes printed on the row. Migration 018 adds
  `qa_verdict`, `qa_notes`, `qa_at`; `qa_verdict` on a kept row is how a machine
  keep is told apart from a human one.
- **First run, 16 images: 10 pass, 6 fail** — invented prices, a label drawn
  twice (three times over), step cards reading 1, 3, 5, 6, 6, and two arrows
  on the wrong rooms. The agents caught the duplicates and invented text
  unprompted; they **passed** the repeated step number and called the wrong-room
  arrows "unsure". Both are now explicit fails in the brief, along with
  overlapping text. **Read a few verdicts against the images after every brief
  change** — the agents follow the list literally and are lenient on anything
  it leaves out.
- **Nothing drains the redraws on its own.** A fail queues an `art_direct`
  job, which queues a `generate_image` job; on 2026-10-05 neither moved for
  hours (113 directions sat queued), so whatever cron once drained the queue is
  not running for these types. After every push, drain both by hand, several
  workers each with their own lock:
  `php cli.php work --types=art_direct --max=40 --window=1700 --lock=qaN` and
  `php cli.php work --types=generate_image --max=60 --window=3000 --lock=giN`.
  A redraw is ~1¢ of Anthropic plus $0.134 of Gemini (interactive, not batch).
- **Agents drop mid-batch** (API connection resets, 600s stalls) — about one
  in eight. Tell them to append each verdict as decided, then re-issue only the
  ids with no verdict (`status <run>` lists them).
- **Results, 2026-10-05 (seven runs, the whole backlog): 299 kept, 73 flagged for
  a person, $29.48 of Gemini/Anthropic spend on redraws.** About 60% pass on the
  first check, about 45% of redraws pass on the second. **The v3 prompt leaks its
  own scaffolding**: `ImagePrompt` labels panels "SECTION n — header, exactly:"
  and the image often draws "SECTION 1 —" on the header; it also drew "Editorial
  explainer infographic" and "Editorial vector spot". Rewording those lines
  (not yet done — it is the brand prompt, so Jassen's call) is the cheapest way
  to cut the redraw rate. Commonest faults: a label drawn twice, an invented label or caption,
  a missing label, garbled words, the whole scene drawn as a triptych, and a
  drawing that reverses its own prompt.
- **Re-importing is unaffected**; this touches only `assets`. Runs live in
  `worker/qa_runs/` (gitignored), with 1600px JPEG viewing copies.

**Verified working 2026-08-07** via `worker/test_gemini.py`. That test renders a
deliberately text-heavy infographic; Nano Banana Pro returned 20+ words across five
elements with zero spelling errors. That result is why §9.4 routes by *whether text
must be exact* rather than by how much text there is.

## Art direction authored in Claude Code

Directions can be written ahead of publishing instead of generated at render.
Read `../bizorca-drafts/prompts/01-art-direction.md` before starting a pass.

```
bizorca-drafts/NNN-format-slug.txt        (read the post, append a block)
  → validate_direction.py                (structure, fields, label discipline)
  → import_drafts.py --dry-run           (parse and report, touch nothing)
  → import_drafts.py                     (enqueue, wait, report)
```

The block is a JSON object after a `---GEMINI---` line at the end of the file.
JSON rather than the prose style around it because it is the one part of a draft
a machine consumes, it round-trips into a JSON column, and a hand-rolled
key/value parser fails quietly on a colon inside a sentence.

**Store the direction, never the assembled prompt.** The assembled prompt is most
of a page of palette anchors, attribution, text discipline and the avoid list, and
`ImagePrompt` owns all of it so that this pass is never asked to decide the brand
and therefore cannot drift it. `validate_direction.py` fails a direction that
mentions `bizorca.com`, a hex colour, an aspect ratio or a watermark, because any
of those means the wrong layer is being written.

**Absence must not clear.** 410 drafts predate this and carry no block. If
`Content::posts()` had written the direction columns unconditionally, one routine
re-import would have destroyed every direction added since — so a file that omits
the block leaves those columns alone, and only a file supplying one writes to
them. `import_drafts` reports malformed blocks in a `rejected` list and imports
the rest rather than failing 410 good drafts for one bad comma.

`Database::row()` does not exist. It is `one()`. PHP's linter does not catch an
undefined static method, so this cost a fatal that `php -l` reported as clean.

## Extraction — Claude Code, not the API

Lives in `../extractions/`. Read `../extractions/README.md` at the start of any
extraction session.

```
book-scans/<book>.pdf → OCR → book-scans/<book>.txt
  → structure_shots.py <id>  (page images, when the text resists)
  → Claude Code runs prompts/0N-*.md → extractions/outbox/<slug>.<pass>.md
  → validate → import → extractions/uploaded/
```

**Check `book_slug` against the library before writing a profile.** The slug is
derived from the source filename and is often not what the title suggests —
`financial-counseling-2`, `carnivore-diet`, `the-financial-coaching-playbook`.
Four of the first eleven profiles had it wrong. The importer catches this
safely: it 422s with "No book with slug X. Ingest it first" and moves the file
to `extractions/failed/` with a `.log` beside it. It does **not** create a
second book. Fix the slug, move the file back to `outbox/`, re-run.

## Which text file is the source

**`library/book-N/full.txt`.** Not `book-scans/<book>.txt`, which the prompts
used to name — that is legacy OCR output and it exists for only **22 of 50
books**. Every book has a `full.txt`.

Where both exist they are the same text: same 261 page markers on book 13,
normalised lengths within 0.03%, and 118 of 119 sampled body sentences match.
The differences are line wrapping and curly-versus-straight quotes — ingest
folds `’` to `'` (1,157 → 0 on book 13) — and `validate_extraction.norm()`
already folds both, so verbatim checking against `full.txt` is safe.

Three representations of the same text, all current:

- `library/book-N/full.txt` — one file, page markers. The source of record and
  what `source_txt` must point at.
- `library/book-N/pages/NNNN.txt` — 12,215 files, the working format. Every
  local tool reads these, because the work is per-page: score a page against
  its book's median, read page 221, sample six folios. Splitting a 633KB file
  on markers for each of those would be absurd.
- the `pages` table with its FULLTEXT index — what `Expand` queries at compose
  time.

`source_txt` is now resolved on **every** pass. It used to be checked only on
`artifacts`, so a profile could name a nonexistent file and validate clean; the
failure then surfaced at pass 4 on a book extracted weeks earlier.

**`extractions/` is its own git repo** (2026-08-10), separate from this one:
it is the content the pipeline produces, not the app, and `writer/` as a whole
cannot be a repo — 38,000 corpus files, 12,000 page texts, the book PDFs.
`uploaded/` and `prompts/` are tracked; `outbox/` and `failed/` are ignored as
work in flight. No remote yet.

Commit there, not here, when a profile changes. Two commits in this repo once
carried messages describing profile corrections that were not in them.

**A partial pass 2 file is destructive.** `clearPass('chapters')` runs
`DELETE FROM chapters WHERE book_id = ?` — the whole map — so importing three
chapters of an eight-chapter book leaves that book with three. Write the whole
book before importing. Stage incomplete work in `extractions/wip/`, never in
`outbox/`, which is watched and can import on its own.

Pass 1's COVERS list becomes **proposed** chapters, and `printed_page_offset`
and `pagination_note` reach the books row. It only replaces chapters that are
themselves proposed: a book with confirmed or extracted chapters keeps them and
the import reports `note_covers_skipped` rather than a silent zero.

Four passes: `profile`, `chapters`, `ideas`, `artifacts`. Pass 3 reads Pass 2's output,
not the book. Pass 4 transcribes verbatim while the others summarize.

Always validate before considering a pass finished:

```
press/worker/.venv/bin/python press/worker/validate_extraction.py extractions/outbox/
```

Every item in a `verbatim: yes` artifact must appear as a normalized substring of the
source `.txt`. This is enforced in code. **Known issue: the 19 existing
`book-scans/*_concepts.txt` files are condensed, not verbatim** — the Bachrach checklist
says "CDs" where the book says "certificates of deposit" — so they import as
`verbatim: no` or get re-extracted with `prompts/04-artifacts.md`.

## Before you deploy

```
ssh bizorca-sg 'cd ~/www/anglerfish.bizorca.com/anglerfish && php smoke.php'
```

Runs every GET route through the real front controller with a faked session,
one process per route, and reports byte counts or the exception with file:line.
Written after a bare `/library` 500'd in production. Two traps it now avoids:
the app calls `exit()` on redirects (so routes cannot share a process), and
`index.php` calls `session_start()` itself (so the session must be started
before seeding `$_SESSION`, or every authenticated route silently "passes" as a
redirect).

## Operational gotchas (found the hard way)

- **SiteGround's WAF 403s the default `Python-urllib/x.y` User-Agent** on every
  route. Identical curl requests return 200. Both worker HTTP clients send
  `AnglerfishWorker/1.0`. Any new script that talks to the app must too.
- **`Job::lease([])` matches nothing, deliberately.** If an empty type list meant
  "all types", a probe or misconfigured client would silently lease real work and
  hold it for the full lease window. Always pass explicit types.
- **`applyIngest` overwrites derived fields rather than COALESCE-ing them.**
  `has_text_layer`, `printed_page_offset` and `pagination_note` are recomputed on
  every ingest; COALESCE made stale notes unclearable.
- **Assert on every scripted `str_replace` into source.** A silent no-op cost a
  round trip when the target string had drifted.
- Covers live in `storage/covers/` outside the web root and are served by
  `/media/cover/{id}`, so a deploy's `--delete` can never wipe them.

## The handoff (Phase 5)

`/posts` lists the 410 drafts; `/posts/{slug}` is the publishing screen. Substack's
API is deprecated, so this screen *is* the publishing path.

- `Post::toHtml()` emits only ProseMirror-safe tags — h2, h3, p, strong, em, ul,
  ol, li, blockquote, a. No divs, no classes, no inline styles. Anything else is
  dropped or rewritten by Substack's editor on paste.
- Text-node escaping uses `ENT_NOQUOTES`, not `ENT_QUOTES`. Escaping apostrophes
  put `&#039;` through the whole pasted body; `< > &` are still escaped, and this
  content is never an attribute value.
- **Copy body writes two clipboard flavors in one `ClipboardItem`** — `text/html`
  and `text/plain`. HTML alone can be refused; plain alone pastes as a wall with
  every heading and list lost. Safari rejects multi-flavor writes, so there is a
  selection + `execCommand` fallback.
- `Post::warnings()` blocks the embarrassing paste: every one of the 410 drafts
  ships with an unfilled `[shoutout: ___]`, and the screen says so in red.
- Mark published is the only signal that a post shipped, since nothing comes back
  from Substack. It clears the `write_about` triage mark.

## The weekly post

`/weekly` — a seventh post format, alongside the six rotating ones rather than
replacing them. One shape (trap, diagnostic, protocol, Go!), slotted against one
of twelve curriculum modules, shipping one free PDF deliverable. The writing
brief is `../bizorca-weekly-post-brief.md` and the modules are
`../bizorca-curriculum.md`; **read both before drafting.**

**Live as of 2026-09-03.** Migrations 011 and 012 are applied, the module map
is imported (673 rows), and the format is verified end to end in production:
composition 8 generated from module 8 in 69s and promoted to a draft with
`sequence_number` 1.

Why a separate screen instead of a seventh entry in the compose form: a weekly
post needs a module and a deliverable container, the compose form has nowhere to
ask for either, and a weekly post created without them cannot be tagged,
sequenced or compiled into the workbook. `Composition::FORMATS` deliberately
still lists only the six for that reason. Generation reuses the compose pipeline
unchanged — `/weekly` only gathers the extra fields.

- **`weekly` is additive to both format enums.** 410 drafts carry the six values
  and the Posts screen, the art-direction pass and the Vault all still read
  them. Appending to a MySQL ENUM does not renumber existing values, so no
  stored row changed meaning.
- **`sequence_number` is not `posts.number`.** `number` counts every draft ever
  made, including the 410 that predate this format; starting the printed series
  at 411 would be absurd. It is assigned at *promotion*, not at compose time, so
  a composition that is generated and abandoned does not burn a number and leave
  a visible gap in the workbook.
- **Both format specs go in the system prompt unconditionally.** Loading only
  the one a post needs would make the cached prefix depend on the format and
  split one cache entry into seven. The user turn names the format, and that is
  what selects the shape.
- **Weekly overrides its own length.** The band is 900–1,400 words, which is not
  one of `compositions.length`'s three values. The format decides, rather than a
  fourth enum value that only one format can use.
- **`module_map.ord`, not `rank`.** RANK is a reserved word in MySQL 8+ and the
  unquoted column is a syntax error — caught by applying every migration to a
  scratch database, not by `php -l`. `ord` is also what artifacts, chapters and
  concepts already call their ordering column.
- **An orphaned `module_map` row renders as a blank entry**, since the table is
  polymorphic and cannot carry a foreign key to its subject.
  `Weekly::sourcesForModule()` filters them with `HAVING title IS NOT NULL`.

### The deliverable

`render.py` target `weekly_deliverable` — the Render route, so no model and no
cost, and the text is exactly the text supplied. One fixed template, four
containers (`test`, `checklist`, `worksheet`, `role_document`), chosen never
designed.

- **It may run to two pages.** Unlike the one-page tool sheet, it does not step
  the font down to fit: a scored assessment whose blanks have been squeezed to
  8pt is not something anyone fills in with a pen.
- **The footer block is on every page**, not just page one — publication,
  sequence and module, post URL, date, page number, the cohort line and the
  permission line. Pages get separated, photocopied and handed around, and the
  file outlives the post. That origin line is the only acquisition mechanism a
  free ungated download gets.
- **The cohort line is keyed by cohort**, not hardcoded. A single string printed
  the Foundations pitch on a Growth role document.
- **Item numbering runs across sections**, not per section. A twelve-question
  test whose sections each restart at 1 does not add up to a score out of 60.
- **The scoring block travels as one unit** (`.scoring`, `break-inside: avoid`).
  Split across a page break it puts the total box on one sheet and the bands on
  the next.
- Everything derived — module tag, sequence, URL, date, cohort — comes from the
  post's own columns via `handlers.deliverable_spec()`, so re-slotting a post to
  another module reprints correctly without editing JSON. Only the authored
  content lives in `posts.deliverable_spec`.

Four worked mockups, one per container:

```
press/worker/.venv/bin/python press/worker/weekly_mockup.py
press/worker/.venv/bin/python press/worker/weekly_mockup.py --only test
```

Output goes to `writer/mockups/`. It is a design proof — it touches neither the
database nor the app.

### A weekly compose outlives the MySQL connection

**This is the trap to know about.** A weekly post is 900–1,400 words and its
Claude call runs about **68 seconds**. MySQL closes the connection well before
that, so the API call succeeds and the write of its result fails with 2006
"server has gone away".

It is the worst possible failure shape: the post was generated and paid for, the
job looks like it never ran, and the error write fails on the same dead
connection so nothing is recorded. It presented as three silent attempts and a
job stuck at 3/3 with a NULL error. The six rotating formats never hit it,
because a 400–600 word post finishes inside the timeout.

`Database` now retries a statement once on 2006/2013 after reconnecting, and
refuses to inside a transaction, where the work so far is already lost.
`insert()` does its execute and `lastInsertId` in one closure — split across two
calls, a reconnect between them returns 0 from a fresh connection that has never
inserted anything.

Anything else that grows past a minute of wall time — a longer format, a bigger
expansion, a slower model — inherits the same exposure. The fix is general, but
the lesson is that on this host a long API call and a database write cannot
assume they share a live connection.

### The default path: write it in Claude Code, file it with `weekly-import`

**As of 2026-09-18, weekly posts are written in a Claude Code session and
filed as finished compositions, not generated by the server.** The server
composer calls the Anthropic API and is metered per token (about a dime a
post); a post drafted in Claude Code runs on the subscription and costs
nothing per token — the same economics that already keep extraction off the
API. The server path (`weekly` + `work`) stays for unattended runs.

```
bizorca-drafts/weekly-mNN-<slug>.txt          write the post here
press/worker/.venv/bin/python press/worker/weekly_push.py <file> --check --proof
press/worker/.venv/bin/python press/worker/weekly_push.py <file>
```

The file is front matter, a title line, the plain-text body, and the
deliverable's content after a `---DELIVERABLE---` line:

```
---
module: 1
deliverable: test
subtitle: One line under the title
angle: The failure-first opening, if one was set
composition: 9          (optional: overwrite this row instead of inserting)
---
The Title

Body, plain text, blank lines between paragraphs. Post::toHtml() handles
the HTML at handoff, exactly as for the server-composed drafts.

---DELIVERABLE---
{ "container": "test", "title": "...", "intro": "...",
  "sections": [ {"title": "...", "instruction": "...", "items": [ {"text": "...", "note": "..."} ]},
                {"title": "...", "fields": [ {"label": "...", "hint": "...", "lines": 1} ]} ],
  "scoring": { "max": 60, "bands": [ {"range": "12 to 28", "meaning": "...", "action": "..."} ] } }
```

`weekly_push.py` scps the file to `storage/incoming/` and runs
`php cli.php weekly-import --from=...`, which writes one `compositions` row
with `status='done'` and `model='claude-code'` — no job, no model call — so
`/compose/<id>`, promotion and the deliverable render are the same screens as
before. `--check` validates without the server; `--proof` renders the PDF into
`writer/mockups/` first; warnings (length outside 900–1,400, a
`[shoutout: ___]` left in, an opener that reads as invented biography) block a
push unless `--force`. A `weekly-` filename is invisible to `import_drafts`,
which only matches the six daily format names, so nothing else picks it up.

Three things this path fixed or exposed:

- **`posts.deliverable_spec` had no writer.** The renderer reads it and the
  server composer never produced it, so every weekly post promoted before
  2026-09-18 would have printed an empty PDF container. Migration 015 adds
  `compositions.deliverable_spec`, `promote()` copies it to the post, and the
  `---DELIVERABLE---` block is where it gets authored.
- **The test scores 1–5 an item**, hardcoded in `render.py`'s `_rows()`, so a
  twelve-item test is out of 60 and the bands must tile that. The server
  composer wrote #9's body as 0/1/2 out of 24; `--check` refuses a spec whose
  `max` is not items × 5.
- **`composition:` in the front matter is the rewrite loop.** Pull a machine
  draft into `bizorca-drafts/`, rewrite it, push it back into the same row
  (and the promoted post, if there is one) rather than filing a second
  composition of the same post. `weekly-m01-money-baseline.txt` is the worked
  example: composition #9's body plus a hand-written twelve-item spec.

### CLI

`cli.php` grew three subcommands, for building weekly posts from Claude Code
rather than the browser. **It runs on the server, over SSH** — there is no local
`.env.php` and SiteGround does not accept remote MySQL connections.

```
A='cd ~/www/anglerfish.bizorca.com/anglerfish &&'

ssh bizorca-sg "$A php cli.php modules"                    # the twelve + coverage
ssh bizorca-sg "$A php cli.php sources --module=8"         # mapped material + ids
ssh bizorca-sg "$A php cli.php weekly --module=8 --deliverable=test \
                    [--angle='...'] [--source=concept:949] [--no-expand]"
ssh bizorca-sg "$A php cli.php work --types=compose --max=1"   # ~70s
```

Same path as the screen: `weekly` creates a composition and queues the job, and
nothing generates in that command. Review and promote in the browser at
`/compose/<id>`; promotion is what assigns the printed deliverable number.

**A Site Tools cron drains this queue every minute**, so `work` may return
silently because the cron holds the flock — the job is running anyway. Check
with `php cli.php status` rather than assuming the command did nothing.

`sources` exists because without it the CLI path dead-ends: you can pick a module
and a container from the terminal but have no way to find anything to write from,
which sends you back to the browser for the one value you needed.

**Only the server composes.** Until 2026-09-17 the Mac's fast lane
(`run-angler.sh fast`) also claimed `compose`, polling every 3s against the
server cron's once a minute, so the local Python handler won every race and
sent the post to Gemini — which had meanwhile retired `response_format:
{type: json_schema}`. Ten freshly queued weekly posts were dead within ten
seconds of `cli.php weekly` returning, and `status` showed it only as
`compose dead`. `compose` is out of the fast lane now; the Python handler's
schema shape is fixed too, but nothing routes to it.

**When Anthropic is capped, flip the job, not the code.** The spend cap that
stopped the image run also stops the composer (same 400, same 2026-10-01
date). `Anthropic::configured()` is true, so the server picks Claude, fails,
and burns three attempts a minute. The escape hatch is per job:

```
UPDATE jobs SET payload = JSON_SET(payload, '$.provider', 'gemini'),
  status='queued', attempts=0, error=NULL WHERE type='compose' AND id IN (...);
```

Gemini (`gemini-3.1-pro-preview`) composes a weekly post in ~60s through the
same prompt and `Compose::parse()`. The output holds the shape; what it does
worse is the no-invention rule — composition 9 opened with a fabricated
"first three years of my career… logging into Chase" paragraph. Read the
opener for invented biography before promoting anything Gemini wrote. A
composition regenerated later on Claude costs no deliverable number, since
numbers are assigned at promotion.

### Library to module mapping

`module_map` — which library material belongs to which of the twelve modules, so
the Weekly screen can offer sources for the module being written. Polymorphic
over concepts, chapters, artifacts, artifact items and clippings, for the same
reason `triage` and `assets` are.

```
press/worker/.venv/bin/python press/worker/map_modules.py --report   # score only
press/worker/.venv/bin/python press/worker/map_modules.py           # write the file
press/worker/.venv/bin/python press/worker/map_modules.py --sample 8  # eyeball a module
php import_module_map.php ../extractions/module-map.json --dry-run
php import_module_map.php ../extractions/module-map.json
```

Lexicon scoring, no model call — same reasoning that keeps corpus search off the
API. It runs locally against the extraction files and emits **natural keys**
(`book_slug` + `title`), because the local pass has no idea what a concept's id
is; `import_module_map.php` resolves them and reports anything that does not
match rather than fuzzy-matching onto whatever is nearest.

**Current yield: 673 rows over 549 of 1,869 items (29%).** Lead Flow is the
largest at 284, which is honest — most of a marketing corpus is lead flow.

- **Everything it writes is `source='auto'`,** and a re-import deletes only its
  own rows. Manual rows are the operator's judgement and survive untouched.
  That is the whole reason the `source` column exists.
- **Reuse `parse_big_ideas.parse()` for the syntheses.** A hand-rolled splitter
  agreed with it on neither the count (1,781 vs the real 580) nor the titles,
  because those 22 files open with a numbered table of contents that looks
  exactly like their numbered section headings. The natural keys only resolve
  if both sides split identically.
- **Slug convention must match `import_big_ideas.php`:** `big-ideas-mb-<topic>`.
- **The consumer/practice distinction is the sharpest signal in this library.**
  Several books are consumer personal finance, and untreated "Defending Your
  Home from Foreclosure" scored into First Delegation and the bankruptcy
  chapters into Tax and Entity Hygiene. There is a weighted negative set for it.
- **A match needs one weight-2-or-better term.** Three weight-1 words ("money",
  "plan", "team") are not evidence, and that is how apartment-management
  chapters reached First Delegation.
- **A file prior boosts evidence, never substitutes for it.** Ten of the 22
  synthesis topics map to Lead Flow; letting the prior alone map a section put
  559 rows there, most matching nothing.
- **The 790-question client question bank is excluded, deliberately.** It is
  consumer financial counselling — credit utilisation, vehicle purchases,
  retirement — assembled from eleven personal-finance titles, and it is 790 of
  the library's 822 questions. Scored anyway it yields ~30 rows, every one about
  a household's money. The library has essentially no practice-level question
  material, so the diagnostic movement's questions have to be written rather
  than retrieved.
- `Database::value()` returns PDO's `false` on no row, not `null`.
- Not `pattern.strip("\\b")` — `str.strip` removes the *characters* backslash
  and b, so `\bbalance sheet\b` came out as "alance sheet".

## Rendering (Phase 4)

`worker/render.py` is the Render route: HTML in, flat PNG or one-page PDF out,
via the gstack browse daemon. No model, no cost, text exactly as supplied.

- **The palette lives in `src/View/brand.css` and nowhere else.** render.py
  inlines that file verbatim and the layouts use `var(--navy)` etc. An earlier
  version substituted raw values into f-strings, which silently broke composite
  tokens like `--rule` whose own definition contains `var()` references.
- **The browse daemon sandboxes file paths** to a few roots. Both the input
  markup *and* the output file must be staged inside `worker/.render/`, then
  moved. Reading and writing fail separately, with the same error.
- Tool sheets step the base font 9.6 → 8.4pt until they fit one page.
- The four PIL card scripts are superseded; they stay in `reference/` as the
  layout source of truth.

Vault tools are artifacts with `kit_id` set instead of `book_id` — same store as
book extractions, since they are the same shape.

## Chapter detection

`detect_chapters` proposes boundaries; nothing is authoritative until confirmed in
the UI, because every extraction pass inherits these ranges.

TOC first (the author's own answer, with exact page numbers), heading scan as
fallback. Four things it has to survive, all found on real books:

- **Long titles eat the dot leaders.** Kuhn's "VI. ANOMALY AND THE EMERGENCE OF
  SCIENTIFIC DISCOVERIES 52" has a single space before the page number. Strict
  dot-leader matching identifies the TOC page; a looser second pass on that same
  page catches these. Merge on line number — concatenating the two passes
  destroys document order and the late entries look like they go backwards.
- **Running headers can look exactly like TOC entries.** One OCR'd book's header
  is "1 An Introduction 5". Guard: a real contents page is *mostly* contents, so
  require entry density ≥0.5 (or ≥0.25 with a "Contents" heading), and isolate
  the contiguous TOC block rather than accumulating every qualifying page.
- **A TOC spans several pages.** Walk outward from the densest page in *both*
  directions, or you silently lose the first half of the map.
- **The heading fallback sees the same header on every page.** Dedupe by chapter
  label, keeping the first page each one appears on.

**Q&A books are tried first.** A TOC parse on one returns the Parts — five
chapters spanning 60–100 pages each — which is useless to extract from, since
pass 2 wants a core idea and a 300-word summary per chapter. `from_questions()`
takes the first *body* occurrence of each `Question N:` heading (earlier ones are
the TOC, later ones are "see Question 29" cross-references) and requires eight or
more, strictly ascending, spanning ≥40% of the book before it will claim the
book. Verified: Wheel of Time Decay went from 5 Parts to 62 questions averaging
6 pages, and it correctly declines on all six non-Q&A books tested.

That book also showed what detection *cannot* fix: its TOC lists questions 26–35
and the body jumps straight from Q25 to Q36. The source PDF is incomplete. When
a Q&A book yields fewer chapters than its highest question number, suspect the
file before the parser.

A part and its first chapter legitimately share a page; `dedupe()` keeps the
chapter, since dropping it loses chapter 1 of every part-structured book.

## The daemon

The worker runs continuously under launchd as `com.bizorca.anglerfish.worker`.

```
worker/install-daemon.sh            install + start
worker/install-daemon.sh status     state, pid, last exit code
worker/install-daemon.sh restart    after changing handlers
worker/install-daemon.sh uninstall
tail -f worker/angler.log
```

**Restart it after editing any worker code** — the process holds the old module
in memory until it respawns.

How the Mac gets work: it polls the app outward over HTTPS. Nothing ever calls
in, which is why there is no VPN or inbound firewall rule. Polling is adaptive —
5s right after work appears (so clicking a button in the UI feels responsive),
backing off ×1.5 to a 30s ceiling when idle. A fixed floor would mean ~17k
pointless requests a day.

Three things that bite:

- **launchd's PATH does not include Homebrew.** `run-angler.sh` sets it
  explicitly or `pdftotext`, `tesseract` and `ocrmypdf` all vanish.
- **Normal logging goes only to `worker/angler.log`** (rotating, 5MB × 3).
  `angler-launchd.log` is for crashes. Logging to both duplicated every line
  into a file launchd only ever appends to.
- **A sleeping Mac polls nothing.** Queued work simply waits, then runs on wake.
  Jobs leased when the machine slept have their leases expire and requeue on
  their own, so nothing is lost.

## Worker environment

Python 3.12 in a venv at `worker/.venv`. System `python3` is 3.9 and past EOL; Homebrew
Python is PEP 668 managed, so the venv is required rather than a nicety.

```
worker/.venv/bin/python worker/<script>.py
```

Packages: `google-genai`, `pillow`, `pypdfium2`, `python-pptx`, `python-docx`.
Binaries: poppler (`pdftotext`, `pdfinfo`), Tesseract (no `ocrmypdf`), and the gstack
browse daemon at `~/.claude/skills/gstack/browse/dist/browse` for HTML → PNG/PDF.

## Whole-book summary and audiobook script

`/library/{slug}/summary` — the Cliff's-Notes view. Two outputs with very
different economics:

- **The outline is deterministic.** Assembled from concepts, chapters and
  artifacts that are already extracted, so it is instant, free, and never
  stored — a stored copy could only drift from the material it summarises.
  `summary.md` downloads it.
- **The audiobook script costs a Claude call** (~40s) and is stored in
  `book_summaries`, keyed unique on (book, kind). It records `source_sha` of
  the outline it came from, so the page can say "the concepts changed since
  this was generated" instead of silently serving a stale script.

The script is a real transformation, not a reformat: the input has headings,
bullets, page refs and bold — none of which exist in audio. `worker/prompts/
audiobook-script.md` converts all of it to sentences, counts lists out loud
("there are four of these"), and speaks figures. Verified output on
`big-ideas-dk-referrals`: zero headings, zero bullets, zero page references.

**Copyright is the hard constraint here.** A summary product that recites
another author's verbatim checklists is republication. The outline marks
verbatim artifacts `**[verbatim — do not republish]**`, and the script prompt
is told to describe such tools rather than read them out, losing a detail
rather than reproducing one.

`deploy.sh` syncs `worker/prompts/` — the server reads those specs too, and it
did not sync them before, so a new prompt reached production as an empty system
block and a 400 that read like an SDK fault. `Anthropic::complete()` now refuses
an empty system prompt with a message naming the real cause.

## Born-text sources (transcripts)

`book-scans/` accepts standalone `.txt` as well as PDFs — audiobook transcripts,
talks, anything with no scan behind it. Discovery skips two kinds of `.txt` that
are not books: one sitting beside a same-named `.pdf` (that is OCR output) and
`*_concepts.txt` (condensed summaries, not sources).

With no PDF there is nothing to paginate against, so `paginate_text()`
synthesises ~2,000-character pages — close enough to a real page that concept
page refs, per-page FULLTEXT and Expand's scoring all behave normally. It breaks
on **timecodes** when the file has five or more (`[00:14:22]`), on blank lines
otherwise, and never mid-sentence.

Three things learned building it:

- **Boundary-only splitting overshoots badly.** `coaching_questions.txt` has
  blank lines ~9k apart and produced 19k-character pages against a 2k target.
  Anything over 2× target is now subdivided on line breaks. Check `max` page
  length, not just the count, when a new source looks wrong.
- **Hygiene must not run on transcripts.** It strips running headers by how
  often a line repeats at the top of a page — which on a transcript means
  deleting the speaker labels. There is no OCR debris to clean either.
- **`ocr` is recorded false and `has_text_layer` true.** A transcript never went
  through Tesseract; saying otherwise misreports why the text reads as it does.

Covers come from `text_cover()` — PIL, not the browse daemon, so a transcript
does not fail to ingest whenever headless Chromium is down. Navy/gold/Georgia,
400×600 JPEG, "TRANSCRIPT" label.

## Page images for pass 1 — the vision step

**When a book resists pass 1, render the pages and look at them.** That is the
whole method; `worker/structure_shots.py` just picks which pages.

```
press/worker/.venv/bin/python press/worker/structure_shots.py 13
press/worker/.venv/bin/python press/worker/structure_shots.py 13 --pages 199,227
```

PNGs land in `library/book-N/shots/NNNN-<why>.png` and Claude Code reads them
with the Read tool. **This costs nothing** — extraction runs on the
subscription, not the API (hard rule 3), so there is no reason to ration it.

Why it exists: text quality is not what blocks pass 1. Only 3 of 45 books have
a text problem, yet 42 of 43 are opaque to structure detection. The failure is
a page whose *text* lacks the answer while the page itself has it plainly.
Book 13 proved every part of this in one sitting — its contents page OCR'd
chapter 3 as `Listening «0` with every page number destroyed, and the image is
flawless; no folio survives OCR anywhere in its 261 pages, and pdf 221 shows
"210" in 24-point type at the foot.

**Do not add opener-guessing heuristics. Two were measured and both lost to
chance.** A local-density dip found none, because a graphic chapter banner sits
at the top of an otherwise full page — book 13's openers run 2,200–3,700
characters, indistinguishable from body pages. A top-of-page junk score found
5 of 15 on book 13 and 0 of 8 on book 40, ranking the index above every opener.
Numbered openers are kept only when OCR yields four or more in a strictly
ascending run, which is exact rather than heuristic (books 16 and 37).

The sequence that works, and the one the selection is built around:

1. Read the contents page off the image. Dot leaders are what OCR destroys; the
   titles and printed page numbers are legible.
2. Read two folio probes, one near each end. **Check for drift** — book 13 runs
   −15 at the front and −11 at the back, book 47 walks −15 → −14 → −13. A single
   offset silently mislocates the back third of a book.
3. Convert printed starts to pdf pages, then confirm with `--pages`.

Text-derived openers are noisy and must be sanity-checked: printed-minus-pdf can
only stay flat or rise, because pages go missing and never get added. Book 13's
first-occurrence scan produced −15, −17, −12, which is impossible and meant the
scan was hitting running headers, not openers.

`library/book-N/source.json` records which file the book came from, written at
ingest. Without it nothing local can find the PDF to render.

## OCR quality — standing policy

**Re-OCR anything with big gaps.** `worker/ocr_quality.py` decides what that
means and is the tool to run before spending attention on a book:

```
press/worker/.venv/bin/python press/worker/ocr_quality.py            # score all
press/worker/.venv/bin/python press/worker/ocr_quality.py --flagged  # ids only
```

Three measures, because they fail differently: **sparse** (pages under 200
chars — a real page runs 1,500–3,000), **garbled** (text present but orphan
tokens and no real words, which a character count cannot see), and **median
density** (a book uniformly thin rather than patchily broken).

Force a full pass by queueing `ingest` with `force_ocr: true` in the payload.

Two things learned doing this on the first three flagged books:

- **The old 50-character OCR floor was too low.** A page carrying only its
  running header runs 100–300 chars, clears the floor, and is never OCR'd — so
  the book looks ingested while body pages are missing. The floor is now
  adaptive: `max(50, 25% of the book's own median)`, which judges each page
  against its own book rather than a constant.
- **Sparse does not mean broken.** Books 30, 35 and 49 came back from a full
  forced pass with identical scores, because their sparse pages are *pictures* —
  a magazine's photo spreads, an 1853 manual's engraved plates. There was no
  text to recover. A forced pass now writes `library/book-N/.ocr-forced` and the
  scanner stops flagging that book, or the policy loops on picture books forever.

**Text quality is not the reason most books resist pass 1.** Of 45 books scored,
only 3 flagged, with medians of 1,500–3,700 chars. Yet 42 of 43 are opaque to
structure detection — no parseable folios, contents page or chapter openers.
That is a detection limit, not a scan-quality one, and re-OCR does not fix it.
Those books need reading.

`ocrmypdf` is **not** installed despite older notes here saying so; OCR goes
through pypdfium2 rendering plus `tesseract --oem 3 --psm 1`.

## OCR

`pdftotext` first, always. Roughly three in five books have a real text layer, and
OCR'ing those is hours of work producing worse text. OCR only pages under 50 characters.

Use `--psm 1`, never `--psm 6`. The old `book-scans/process_books.py` used `--psm 6`,
which assumes a single uniform text block and shreds the two-column textbooks — visible
in the Bachrach `.txt`, where checkbox glyphs came through as "QO" and the columns
interleaved.

## Layout

```
press/
  SPEC.md          ← the authority
  migrations/      numbered *.sql + idempotent migrate.php
  src/             MVC, PSR-4
  public/          front controller
  templates/
  worker/          Python worker + .venv + .env (mode 600, never committed)
  reference/       the four PIL card scripts, kept as layout truth during the HTML port
```

---

## Two publications (2026-09-29)

This app serves **Bizorca Press** and **Hypnologue**. Migration 016 was the first
admission of that — a Hypnologue graphic rendered with bizorca's copyright — and
**migration 017 makes the publication the row that owns the differences**: voice,
format spec, model, effort, source material, and whether a citation is mandatory.

**Not a second install.** They share the library, the extraction pipeline, the
image pipeline and the job queue. A second database would mean ingesting every
book twice and maintaining two copies of `ImagePrompt`.

| | Bizorca Press | Hypnologue |
|---|---|---|
| Voice | `voice-bizorca-press.md` | `voice-hypnologue.md` |
| Format | `formats.md` + `format-weekly.md` | `format-hypnologue.md` |
| Model | `claude-opus-5` (app default) | **`claude-fable-5-1`, effort `high`** |
| Footer | `© {year} bizorca.com` | `© {year} Jassen Bowman. Hypnologue.net` |
| Citation | not required | **≥1 verified, enforced at promotion** |

**There is no Opus 5.5.** The lineup is Fable 5.1, Fable 5, Opus 5, Opus 4.8/4.7/4.6,
Sonnet 5, Sonnet 4.6, Haiku 4.5. Hypnologue runs Fable 5.1 because its posts are
short but dense. Fable-family specifics that matter here: thinking is always on and
**sending a `thinking` parameter at all is a 400**; `budget_tokens` is removed;
assistant prefill is removed; forced `tool_choice` returns 400. `Anthropic::THINKING_ALWAYS_ON`
records this so adding a thinking param for Opus later cannot silently break Hypnologue.

### The citation gate — the thing this is all for

`Citations::verify()` is the **only** path to a non-null `verified_at`, and it sets
it only after CrossRef returns a record for that exact DOI. `Composition::canPromote()`
blocks promotion without one, and it lives on the model rather than in a controller
because there are three ways to promote and a rule enforced in two of three is not a rule.

**A prompt cannot do this job.** Post 001 of Hypnologue shipped two fluent, plausible,
false attributions. A model asked for a citation produces something that looks exactly
like a real row. The voice spec gives it two escape hatches — `[DOI: unverified]` and
`[NO SOURCE FOUND: ...]` — because a model told to cite with no way out invents.

`supports = 'contradicts'` is first-class, not a failure. This publication's approach
is that a source disagreeing with the claim is part of the story.

### Writing a Hypnologue post — two paths, both live

**Local (default, subscription tokens).** Draft in a Claude Code session, then:

```
press/worker/.venv/bin/python press/worker/hypno_push.py hypnologue/011-post.md --check
press/worker/.venv/bin/python press/worker/hypno_push.py hypnologue/011-post.md
```

`--check` resolves every DOI against CrossRef **from the Mac, before anything
uploads**, and also catches unresolved `[NO SOURCE FOUND...]` placeholders and
Bizorca devices (a `Go!` section, a Kokoro mention) that leaked across. Lands via
`cli.php hypno-import`. `composition: <id>` in the front matter re-imports into the
same row instead of accumulating near-duplicates.

**Server (unattended, API tokens).**

```
ssh bizorca-sg "$A php cli.php hypno --angle='...' [--source=concept:949]"
ssh bizorca-sg "$A php cli.php work --types=compose --max=1"
ssh bizorca-sg "$A php cli.php cite --check=<id>"
```

Other commands: `publications`, `corpus --pub=hypnologue`,
`cite --doi=<doi> --composition=N [--supports=contradicts]`.

### Routing source material

`publication_scope` is a **join table, not a column on books** — a book on persuasion
serves both publications, and forcing exclusivity would be wrong in both directions.
`auto` rows are the classifier's and are replaced wholesale; `manual` rows survive
every re-run. Same contract as `module_map`.

```
press/worker/.venv/bin/python press/worker/map_publications.py --report --include-raw
press/worker/.venv/bin/python press/worker/map_publications.py --include-raw
php press/import_publication_scope.php extractions/publication-map.json --dry-run
```

**The first run put 34 of 34 books in Bizorca's scope**, including a massage-therapy
pathology textbook and a carnivore diet book. Two faults, both worth knowing because
either alone would do it again:

- **Raw counts scale with document length.** A 400-page book clears any fixed floor on
  incidental usage; a 200-word concept block cannot clear it on a perfect match.
  Everything is scored **per 1,000 words** now.
- **Generic words did the work.** `offer`, `entity`, `conversion`, `rate`, `fee`,
  `schedule` are in every book ever written. A pesticide manual scored 0.70 on
  "offers for sale" and "unit conversion". **A decisive (weight-3) term must be one
  nobody says by accident**, and at least one is now mandatory.

**Concepts and artifacts inherit their book's scope** rather than being scored. Direct
scoring gave 0 of 646 matches, and that is not a floor to tune — a paragraph about
pricing genuinely does not contain "engagement letter", and loosening until it does is
how the pesticide manual got in.

### The finding that matters most

**Not one of the 34 extracted books is about hypnosis.** The library is personal
finance, financial counselling and agriculture. Hypnologue scoring zero books was the
honest answer, not a bug — its material has never been ingested. `--include-raw`
scores the un-extracted captures in `book-scans/` to shortlist what extraction would
buy; it found 8, led by *Hypnosis Without Trance* (0.75) and the Nongard script book.
More hypnosis books are coming through the Kindle capture flow, and the classifier
picks them up automatically as they land.

### Corpora that are not books

`publication_sources` holds the three that will never have a `books` row: the Mandel
Academy and podcast notes, the 318 Brain Software transcripts, and the QHHT Level 1
course audio. Each carries an `attribution` (never optional, never inferred at render
time) and a `caution`.

**`citable = 0` on all four rows, and that is the point.** The Mandel corpus is
excellent on technique and unreliable on attribution and science — §20 of
`mandel-reference.md` is the standing audit, and post 001 took two claims from
material like it and both failed verification. **Leads, never citations.** QHHT
additionally carries a do-not-reproduce warning: it is purchased course material, and
its past-life and healing claims cannot be asserted as fact.

### The citation panel on the draft page

`/compose/<id>` carries a citations section. Each row gets **three new-tab links,
because they answer three different questions and only the first is automatic**:

- **doi.org** — does the handle resolve to a real publisher page?
- **CrossRef record** — is the stored metadata what the record actually says?
- **Scholar** — *does the paper say what the post claims it says?*

The third is why the panel exists. Verification proves a DOI exists; it cannot
prove the sentence it is attached to is supported by it. Post 011 cited a real
1986 paper and still needed a human to read the abstract before its percentages
were right.

`NOT CHECKED` (a DOI in the body that has never been through `verify()`) is a
distinct state from `UNVERIFIED` (checked, and CrossRef has no such record).
Collapsing them would hide the difference between work not done and a fabricated
citation.

### The Mandel and QHHT ingest (applied 2026-09-29)

Three books in production, scoped to Hypnologue at `source='manual'` so a later
`map_publications.py` run cannot clear them:

| Book | Chapters | Concepts | Pages |
|---|---|---|---|
| `mandel-academy` | 623 | 4,588 | — |
| `mandel-podcast` | 317 | 4,126 | — |
| `qhht-level-1` | 13 | — | 86 |

8,714 concepts, matching the documented corpus total exactly. Generated by
`worker/ingest_hypno_corpus.py`, which **emits SQL rather than connecting** —
SiteGround refuses remote MySQL, so the file is gzipped, scp'd and run there.

**This was a load, not an extraction.** The Mandel notes already carry a title,
a summary, topics and typed items, which is precisely the shape the five passes
exist to impose on a wall of OCR. QHHT is transcript text, so it paginates on
the timecodes at 8 blocks a page and gets no concepts.

**The caution lives in `books.context`**, not just in `publication_sources`.
`Compose::user()` already labels that field as framing rather than material and
hands it to the composer on every post built from the book — it is the one place
a warning is guaranteed to be read. 988 characters of leads-never-citations on
both Mandel books, 572 of do-not-reproduce on QHHT.

Two traps worth keeping:

- **`publication_scope` has no foreign key to `concepts`** — only to
  `publications`. A re-run deletes and reinserts concepts with fresh
  auto-increment ids, so without an explicit orphan sweep the old scope rows
  would point at nothing, or at ids since handed to a different book's concepts.
  The generated SQL sweeps both concept and book orphans before reinserting.
- **`books.source_sha256` is UNIQUE NOT NULL** and these corpora have no single
  source file. It is `sha256(slug + ':' + item_count)` — stable across re-runs,
  distinct per book, and incapable of colliding with a real PDF's hash.

### Numbers inside a drawn diagram: remove them, do not re-prompt

Post 012's Parkinson's panel needed two arm counts (qigong 8, sham 9). Gemini
rendered them **wrong twice, differently** — first reversed as `FULL (9) / SHAM (8)`,
then as `Sham (8) / Qigong (8)`, which sums to 16 of 17 participants. Pinning the
numbers harder in the direction produced the second error, not a fix.

The working move was to **delete the labels from the drawing** and let the prose
line beside it carry the split. That sentence rendered correctly on every attempt.
SPEC §9.4 already says exact text belongs in a deterministic render; the practical
corollary is that a `draw` instruction must never be the only place a figure
appears. Put the number in a `figure` or a `detail` — those are typeset as given —
and keep the illustration free of text it could get wrong.

Two smaller ones from the same run: `--force` overwrites a render **with no
backup**, and the model is nondeterministic, so a version discarded is gone for
good — render a variant to a new slug before rerolling if it might be wanted back.
And a rerolled fix can introduce a fresh defect elsewhere (v2 fixed the numbering
and duplicated a section header), so re-check the whole sheet after every reroll,
not just the thing that was wrong.

## Videos — the bite-size video production queue (2026-10-06)

`/videos` is Jassen's production queue for 100 short personal-finance videos (5-15
minutes, one core idea each), seeded by **migration 019** (`video_scripts`) from
`writer/finance/Bite-Size Video Curriculum.txt` — eleven parts in teaching order, built
from his 22-session class series and the Personal Finance Brain.

- **Scripts are written in Claude Code, never by the server.** Same economics as
  extraction and weekly posts: subscription, not API. The brief is
  `writer/video-scripts/BRIEF.md` — trusted sources only (Tier A for facts: FDIC, CFPB,
  HUD, SEC, NCLC, the textbooks, Bogle/Bogleheads, the counseling texts; Tier B for
  attributed ideas only; a do-not-use list that includes all Financial Beginnings),
  year-specific figures as `[UPDATE: ...]`, no invented stories (`[JASSEN STORY: ...]`).
- File format: front matter (`number`, `part`, `runtime`), a title line, the spoken
  script, then `---ON-SCREEN---`, `---SOURCES---` (brain citations, `short, year p.N`),
  `---NOTES---`. Files live in `writer/video-scripts/NNN-<slug>.txt`.
- File them: `press/worker/.venv/bin/python press/worker/video_push.py video-scripts/*.txt --check`
  then without `--check`. The push scps to `storage/incoming/` and runs
  `php cli.php video-import --from=...`. The check refuses 700-1,800 spoken words out of
  range, a missing SOURCES block, a source not in the brain's `source-index.tsv`, and
  any do-not-use source.
- Import fills the script fields and moves `topic` → `scripted`; it never touches
  status past that, the video link, the publish date or "My notes", so a rewrite pushed
  after recording keeps the production state.
- Statuses: topic, scripted, approved, recorded, edited, published, skipped. The script
  page lists every `[UPDATE]`/`[CHECK]`/`[JASSEN STORY]` marker under "Before recording",
  has a copy button for the spoken script and a teleprompter `.txt` download with stage
  directions stripped.
