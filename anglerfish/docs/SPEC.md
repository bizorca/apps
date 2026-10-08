# Anglerfish Press — Software Specification

**App**: Anglerfish Press (short name: Press)
**Domain**: anglerfish.bizorca.com
**Location**: `Archipelago/writer/press/`
**Owner**: Bizorca LLC / Jassen Bowman
**Status**: Draft v6 — 2026-08-08

Revision v6 incorporates: the composer running on Claude via the official PHP SDK (§11.2); context expansion across the library at compose time (§11.2a); concepts rather than chapters as the unit the desk is organised around, with typed cross-book links (§11.2b, §8.3 pass 5); Big Ideas syntheses imported as books; born-text sources such as audiobook transcripts (§7.1); the whole-book summary and audiobook script (§11.5); and revised key custody now that generation runs server-side (§15.3).

Revision v5 incorporated: the existing `book-scans/process_books.py` pipeline and its 19 `_concepts.txt` outputs; the mockup-shotgun image builder; library-first navigation; corpus scoping and the watched-folder intake; Bizorca SSO; the manual copy-paste handoff that replaces the deprecated Substack API; verified Gemini access and the recalibrated route rule; and extraction moving out of the app entirely into Claude Code CLI with a file-based handoff (§8).

Naming convention follows the mascot. The hosted app is **the female** — large, stationary, carries the lure. The local worker is **the male** — small, attaches to the host, does the metabolic work, and never detaches. Worker process name: `angler`. Daemon label: `com.bizorca.anglerfish.worker`.

---

## 1. Purpose

Press is a content production desk. It ingests source material, extracts structured knowledge, generates standalone visual assets, and turns all of it into finished, published work.

The design thesis is **one content store, many render targets**. A single concept can leave the building as a Substack post, an infographic, a 1080×1080 card, a Vault kit page, a branded tool PDF, a slide, or a book chapter. Each of those already has a working standalone Python script in `writer/`. None of them share a data layer. Press is the store those scripts should have been sitting on.

Four inbound streams:

1. **Books** — PDFs, digital and scanned, ingested and extracted in-app. 51 already in `book-scans/`.
2. **Corpora** — one corpus, the Marketing Brain (38,067 indexed files). The Dan Kennedy Brain (10,153 entries when this was written) was folded into it on 2026-08-09 and its folder removed 2026-10-03; see CLAUDE.md, "One corpus".
3. **Own drafts** — ~415 Substack drafts, 20 Vault kits, 20 companion tools.
4. **Manuscripts** — Million Dollar Tax Resolution, Claim What's Yours, future books.

## 2. What exists today

`book-scans/process_books.py` (82 lines) is the current ingestion pipeline:

- `pypdfium2` renders every page at `scale=3` (~300 DPI)
- `tesseract --oem 3 --psm 6` on each page image
- Pages joined with `--- Page N ---` markers into `<book>.txt`
- Re-runnable; skips PDFs that already have a `.txt`
- Concept extraction is **not** in the script — done manually through Claude Code, deliberately, to use the Pro plan instead of API credits

Current state: 51 PDFs, 26 with OCR text, 19 with `_concepts.txt`.

Three defects worth fixing, in priority order:

1. **No text-layer fast path.** Every PDF is fully OCR'd even when it has perfect embedded text. Measured: Sacred Economics yields 51,595 characters from the first 20 pages via `pdftotext`, Kuhn 38,142, The Egg and I 72,190 — all in about a second each. OCR'ing those takes hours and produces *worse* text than the layer already present. (Garman Forgue and the Kelsa Dickey book return 20 characters, so those correctly need Tesseract.)
2. **`--psm 6`** tells Tesseract to assume a single uniform block of text. That is wrong for the textbooks, which are the highest-value files in the set — two-column layouts, sidebars, boxed features, tables. `--psm 1` (auto-segmentation with orientation detection) is the correct default for books.
3. **No hygiene pass.** Running headers, footers, bare page numbers, and line-break hyphenation all survive into the `.txt` and then into every downstream extraction.

One thing the current script gets right and the spec preserves: the `--- Page N ---` markers. They are a free page map, which means **all 26 existing `.txt` files import directly into the `pages` table with no re-processing.**

## 3. Scope

In scope: PDF ingestion with OCR; full-text extraction with page-level fidelity and printed-page mapping; structured extraction of chapters, concepts, big ideas, and verbatim artifacts; a library browse UI serving as builder navigation; standalone image generation via a mockup-shotgun builder; triage; composer; a copy-paste handoff surface for Substack; cost tracking.

## 4. Non-goals (v1)

- **Any public surface at all.** No landing page, no marketing pages, no signup flow, no
  pricing page, no about page. `public/index.php` is the login prompt and nothing else:
  unauthenticated hits redirect straight to Bizorca SSO, authenticated hits land on the
  dashboard. Every route behind the wall.
- **Public** library browse. The library UI is internal navigation, SSO-gated. Not a product surface.
- Multi-user or multi-tenant. **Single operator.** No roles, no permissions matrix, no
  invitations, no user administration screen. `users` exists to record who came back from
  SSO, not to manage a population.
- Republishing verbatim artifacts. Stored intact for reference; the composer transforms rather than reproduces (§9.6).
- Mobile authoring. Desktop desk; mobile read-only acceptable.
- Ingesting the Brain corpora as books. They import as indexed sources only.

---

## 5. Architecture

### 5.1 Split

Long-running work — OCR, extraction, image generation — cannot run on SiteGround, which kills background processes. Two halves:

**Hosted app** (anglerfish.bizorca.com): database, web UI, job queue, web-optimized assets, composer and handoff. Vanilla PHP, no framework.

**Local worker** (Mac, launchd): PDF handling, Tesseract, Gemini image generation, HTML rendering via the gstack browse daemon, and importing extraction files. Python 3.12 in a venv at `press/worker/.venv`.

The worker **polls outward over HTTPS** — leases jobs, works, posts results back. No inbound access, no VPN, works from any network. Same operating pattern as the existing `com.jassen.dk-transcribe.plist`.

### 5.2 Job granularity

Jobs are scoped to the smallest retryable unit: one ingest job per book, one OCR job per 25-page batch, one extraction job per chapter, one render job per asset. Chapter 11 fails on its two-page table, chapter 11 retries — not the book.

All jobs idempotent. Re-running replaces rows keyed on `(subject_type, subject_id, pass)`.

### 5.3 Stack

Server: PHP 8.2 (SiteGround constraint, never 8.3+), MySQL via raw PDO singleton, numbered `migrations/*.sql` with idempotent `migrate.php`, Tailwind + Alpine via CDN (no build step), front-controller MVC (`public/index.php` + `src/`), SSO via `login.bizorca.com` with the `BizorcaSSO.php` class client, CSRF on all POSTs, `h()` escaping, `url()` helper, PRG.

Worker: Python 3.12 in a venv (system `python3` is 3.9 and past EOL; Homebrew Python is PEP 668 managed, so a venv is required, not optional). Packages: `pypdfium2`, `google-genai`, `pillow`, `python-pptx`, `python-docx`. Binaries: poppler-utils (`pdftotext`, `pdfinfo`), `ocrmypdf` + Tesseract, and the browse daemon at `~/.claude/skills/gstack/browse/dist/browse`.

**No `anthropic` package in the worker.** Extraction runs in Claude Code CLI against the subscription and reaches the app as files (§8), so the worker never calls Anthropic. The *server* does, for the composer only (§11.2) — `anthropic-ai/sdk` plus `guzzlehttp/guzzle` as its PSR-18 transport, vendored into `press/vendor/` and rsynced by `deploy.sh` rather than installed on SiteGround.

`composer.json` pins `config.platform.php` to `8.2.33`. Without it, resolving against a newer local PHP (8.5 here) can select packages the server cannot parse — the failure would land at runtime, in production, on a syntax error.

---

## 6. Data model

### 6.1 Library

```sql
books (
  id, slug, title, subtitle, year, publisher, isbn,
  source_path,            -- local absolute path; PDFs are never uploaded
  source_sha256,          -- unique, dedup key
  scope,                  -- press|reference (see §7.2)
  scope_confirmed BOOL,   -- FALSE until the operator accepts the auto-classification
  pdf_page_count,
  has_text_layer BOOL,    -- probe result; drives the OCR decision
  printed_page_offset,    -- printed = pdf + offset; NULL if underivable
  pagination_note,
  cover_path, cover_source,   -- auto (page 1 render) | manual
  ingest_status,          -- pending|probing|ocr|clean|mapped|failed
  extract_status,         -- pending|running|partial|complete|failed
  model_used,
  ingested_at, extracted_at, created_at, updated_at
)

authors (id, slug, name, bio)
book_authors (book_id, author_id, ord)
categories (id, slug, name, parent_id)
book_categories (book_id, category_id)
terms (id, slug, name, kind)              -- theme|topic
book_terms (book_id, term_id)

pages (
  id, book_id, pdf_page, printed_page,
  text, char_count, ocr BOOL, ocr_confidence,
  UNIQUE (book_id, pdf_page)
)

chapters (
  id, book_id, ord, number_label, title,
  pdf_page_start, pdf_page_end,
  printed_page_start, printed_page_end,
  core_idea TEXT, summary TEXT, word_count,
  status                                  -- proposed|confirmed|extracted
)

concepts (
  id, book_id, chapter_id NULL, ord,
  kind,                                   -- key_concept|big_idea|caveat|thesis
  title, body, page_ref
)
```

`pages` is the atom. Per-page text rather than one blob is what makes accurate citation, re-chunking, and printed-page mapping possible without re-ingesting.

### 6.2 Artifacts

Artifacts are first-class, queryable across the whole library, and shaped to match `vault/tools/*.md` so `make_tool_pdfs.py` renders them with no new code.

```sql
artifacts (
  id, book_id, chapter_id NULL, ord,
  type,                     -- see taxonomy below
  title, intro,
  verbatim BOOL,
  page_start, page_end,
  confidence DECIMAL(3,2),
  review_status,            -- unreviewed|approved|flagged|rejected
  created_at
)

artifact_items (
  id, artifact_id, ord,
  group_label, text, note, indent_level
)
```

**Type taxonomy**, derived from what the 19 existing `_concepts.txt` files actually contain rather than invented:

- `checklist` — "FINANCIAL DOCUMENTS CHECKLIST (what to gather)", "TRUSTED ADVISOR CHECKLIST"
- `question_set` — "SECTION 4 — QUESTIONS FOR CLIENTS". High volume; `coaching_questions.txt` is 148KB on its own.
- `script` — "PHONE SCREENING SCRIPT (Bachrach's exact recommended language)"
- `framework` — "THE SEVEN GENERATIONS OF SELLING", "THE FOUR D's", "THE VALUES STAIRCASE"
- `worksheet` — "THE FOUR QUADRANTS WORKSHEET", "QUALITY OF LIFE ENHANCER WORKSHEET"
- `comparison` — "SALESPERSON vs. TRUSTED ADVISOR", "PERSON A vs. PERSON B"
- `stat` — "NOTABLE FACTS AND STATISTICS CITED"
- `procedure` — "ACTIONABLE RECOMMENDATIONS"
- `worked_example`, `table`, `quote`

Five of these map one-to-one onto the six Substack formats, which is not a coincidence and should be exploited by the composer: `checklist` → Before Noon, `question_set` → Gut Check, `comparison` → The Upgrade, `procedure` → The Protocol, `stat` → One Number.

Markdown mapping is direct: `##` heading → `group_label`, `- [ ]` → item `text`, trailing parenthetical → `note`, `______` blanks preserved verbatim in `text`.

### 6.3 Own content

```sql
kits (id, number, slug, title, description)     -- the 20 Vault kits

posts (
  id, number, slug, format, title, subtitle, body,
  gemini_structure,          -- flow|metaphor|steps|contrast|anatomy (§9.5b)
  gemini_direction JSON,     -- authored art direction; ImagePrompt adds the invariants
  gemini_direction_at,
  status,                   -- draft|queued|scheduled|published|retired
  kit_id NULL, angle TEXT,
  source_refs JSON,
  voice_preset, model_used, cost_usd,
  substack_url,              -- operator-pasted after publishing; no API
  scheduled_for,             -- operator intent, not a platform commitment
  published_at,
  created_at, updated_at
)

sources (                   -- Brain corpora index, read-only reference
  id, brain, path, author, collection, content_type,
  topics JSON, summary,
  UNIQUE (brain, path)
)
```

`format` enum: `before_noon | gut_check | steal_this | the_upgrade | the_protocol | one_number`.

Draft import maps from filename (`001-before-noon-send-ten-notes.txt`): digits → `number`, format slug → `format`, remainder → `slug`, first line → `title`, rest → `body`. A trailing `---GEMINI---` block carrying a JSON art direction (the §9.5b shape plus a `structure` key) populates `gemini_structure` and `gemini_direction`; files without one import as before, and a re-import never clears a direction a file does not mention. `sources` imports from the existing `text-index.json` and `dk-text-index.json`, which already carry author, collection, content_type, topics, and summary per file.

### 6.4 Assets and candidates

```sql
candidates (               -- one shotgun round
  id, subject_type, subject_id, round,
  label, rationale, visual_type,
  fidelity,                -- exact|editorial (see §9.4)
  copy JSON,               -- {headline, callouts[], takeaway, source_line}
  mockup_html TEXT,
  mockup_png_path,
  gemini_prompt TEXT,
  recommended_route,       -- render|generate
  chosen BOOL,
  created_at
)

assets (
  id, subject_type, subject_id, candidate_id NULL,
  kind,                    -- infographic|card|diagram|cover|tool_pdf|slide
  route,                   -- render|generate|hybrid
  spec JSON, html_path NULL,
  prompt_snapshot TEXT,
  file_path,               -- full-res, local
  web_path NULL, thumb_path NULL,
  width, height, format,   -- png|jpg
  status,                  -- pending|rendering|ready|rejected|superseded
  version, supersedes_id NULL,
  model NULL, cost_usd,
  created_at
)
```

Every reroll is a new row with `supersedes_id` set. Nothing is overwritten, so a worse sixth attempt can fall back to the third.

### 6.5 Operations

```sql
jobs (
  id, type, subject_type, subject_id, payload JSON, priority,
  status,                  -- queued|leased|running|done|failed|dead
  attempts, max_attempts,
  lease_token, leased_at, lease_expires_at,
  error TEXT, cost_usd, created_at, started_at, finished_at
)

api_calls (
  id, job_id, vendor, model,
  input_tokens, output_tokens, images,
  cost_usd, latency_ms, created_at
)

prompts (id, `key`, version, purpose, body TEXT, model_default, params JSON, active BOOL, created_at)
triage (id, subject_type, subject_id, mark, note, created_at, UNIQUE (subject_type, subject_id, mark))
settings (`key`, value)
```

`triage.mark`: `favorite | read | write_about | meh`.

`prompts` is versioned and editable from admin. Extraction and render prompts are operational configuration, not code — they change often and their history matters for reproducing an earlier result. The current ad-hoc approach is exactly why the 19 `_concepts.txt` files have inconsistent section structures across books.

---

## 7. Ingestion

Job type: `ingest`. Input: a local PDF path.

### 7.1 Intake — watched folder

`book-scans/` is a watched directory. A `scan_intake` job runs on a schedule, finds PDFs with no matching `books` row, and registers them. New material arrives by dropping a file in the folder; nothing else is required.

`pdfinfo` for page count and metadata. SHA-256 the file; reject duplicates — note that `Arda Zuber - Wheel of Time Decay.pdf` and `Arda Zuber - Wheel of Time Decay (1).pdf` are byte-identical at 124,663,522 bytes and the hash check catches exactly this case.

**Born-text sources.** `book-scans/` accepts standalone `.txt` as well as PDFs — audiobook transcripts, talks, anything with no scan behind it. Discovery skips two shapes that are not books: a `.txt` beside a same-named `.pdf` (that is OCR output) and `*_concepts.txt` (condensed summaries).

With no PDF there is nothing to paginate against, so pages are synthesised at ~2,000 characters — close enough to a real page that concept page refs, per-page FULLTEXT and Expand's scoring all behave normally. Breaks fall on timecodes where the file has five or more, on blank lines otherwise, and never mid-sentence. Boundary-only splitting is not sufficient on its own: a file whose blank lines are 9,000 characters apart produced 19,000-character pages against a 2,000 target, so anything over twice target is subdivided on line breaks.

Hygiene (§7.5) is **skipped** for these. It identifies running headers by how often a line repeats at the top of a page, which on a transcript means deleting the speaker labels, and there is no OCR debris to remove. Such pages record `ocr=false` and `has_text_layer=true`, because claiming Tesseract touched them would misreport why the text reads as it does. Covers come from a typographic fallback drawn with PIL rather than the browse daemon, so ingest does not depend on headless Chromium being up.

### 7.2 Scope classification

The folder holds two populations and they need different handling. Everything is ingested and made searchable; only press-scope material surfaces in the builder and the composer.

- **`press`** — business, practice, and professional material. The current set: personal finance and financial counseling (Grable Palmer, Garman Forgue, Keown, Durband, Pulvino, Klontz, Kitces, Bachrach, Dickey, Surviving Debt, Money for Teens), coaching and helping skills (Chang Decker Scott, Kadushin, Milligan, Shadow Growth Journal), options and investing (Freeman ×2, Broussard), real estate (Nickerson), health and nutrition (Fox Wild, Ede, Danenberg), wellness practice (massage pathology guide), writing craft (Perret), and **all WSU extension manuals** (weed management, insect and disease, right-of-way vegetation, turf and ornamental, pesticide laws).
- **`reference`** — personal. Robotech, the Instant Pot manuals, the Quileute primer, The Egg and I, the epoxy and sheet-metal manuals, the sailing anchor text, the psilocybin guide, and similar.

New arrivals are auto-classified by a cheap model pass over title and first pages, land with `scope_confirmed = FALSE`, and appear in an intake queue for one-key confirm or flip. Misclassification costs a keystroke; the default is `reference`, so nothing enters the content engine without being waved through.

Reference-scope books still get full ingestion, OCR, and search. They are excluded from the builder, the composer, extraction Passes 3 and 4, and the coverage dashboard.

### 7.3 Probe — the fast path
Run `pdftotext -layout` per page and record `char_count`. Decide OCR **per page**, not per book, because mixed documents are normal:

- Page needs OCR if `char_count < 50`
- `books.has_text_layer` set true if the median page clears the bar

For the three-in-five of the current library with real text layers, this reduces ingestion from hours to seconds and *improves* text quality.

### 7.4 OCR
Only for pages that need it.

Preferred: `ocrmypdf --skip-text --rotate-pages --deskew --optimize 1`, which leaves good text alone and adds a layer only where missing.

Fallback (the current script's approach, retained for files ocrmypdf rejects): `pypdfium2` render at `scale=3`, then `tesseract <page>.png stdout --oem 3 --psm 1`.

**`--psm 1`, not `--psm 6`.** Auto page segmentation with orientation detection handles the multi-column textbooks correctly; `--psm 6` flattens them into interleaved nonsense.

Record `ocr = TRUE` and confidence per page.

### 7.5 Hygiene
Deterministic, no model, every page:

1. De-hyphenate words split across line breaks, guarded by a dictionary check against legitimate compounds.
2. Strip running headers and footers — lines recurring at the same relative position on more than 40% of pages.
3. Remove standalone page-number lines.
4. Normalize whitespace, smart quotes, ligatures (`ﬁ` → `fi`), dashes.
5. Rejoin paragraphs broken across column and page boundaries.

A model-based cleanup pass runs **only on pages below the OCR confidence threshold**. Clean text never costs a token.

### 7.6 Import of existing work
`import_existing` job: parse the 26 current `.txt` files on their `--- Page N ---` markers straight into `pages`, and the 19 `_concepts.txt` files into `concepts` and `artifacts` using the §6.2 taxonomy. No re-OCR. Books whose text quality is visibly poor get flagged for re-ingestion through the new pipeline, rather than the whole set being redone.

### 7.7 Outputs
`pages` rows; `library/<slug>/text/full.txt`; `library/<slug>/pages/NNNN.txt`; `library/<slug>/cover.jpg` from a page-1 render at 400×600.

### 7.8 Structure mapping
1. **Chapter detection** — parse the TOC when present and parseable; otherwise heading heuristics (short line, title case or `Chapter N`, preceded by an unusual vertical gap, followed by body text).
2. **Human confirm** — proposed chapters render as an editable list with page ranges. Nothing extracts until confirmed. Everything downstream inherits these boundaries, so a bad map poisons the book; the gate is worth thirty seconds.
3. **Printed-page offset** — scan first and last lines for bare integers, regress against `pdf_page`, store the offset or note that it is identical or underivable.

---

## 8. Extraction — Claude Code with a file handoff

**Extraction does not run in the app and does not use the Anthropic API.** It runs in Claude Code CLI against the subscription, driven by hand, and reaches the app as plain text files. This is a deliberate reversal of the earlier design and it follows the practice already established in `book-scans/process_books.py`, whose own comment says analysis is "handled by Claude Code directly, which uses the Pro plan rather than separate API credits."

What this buys: no per-token extraction cost, no `anthropic` dependency in the worker, no prompt-versioning table to maintain, and full human judgment on the pass that matters most. What it costs: extraction is manual and batch rather than automatic. Given roughly 30 press-scope books and a few arriving per month, that is the correct trade.

### 8.1 The pipeline

```
book-scans/<book>.pdf
  → (worker)        OCR / text-layer extraction        → book-scans/<book>.txt
  → (Claude Code)   run a pass prompt over the .txt    → extractions/outbox/<slug>.<pass>.md
  → (worker)        validate, import, file             → extractions/uploaded/
```

Directory layout, at `writer/extractions/`:

- `README.md` — the workflow, written for Claude Code to read at the start of a session
- `prompts/01-book-profile.md`, `02-chapters.md`, `03-big-ideas.md`, `04-artifacts.md` — the four pass prompts, on disk and version-controlled
- `outbox/` — Claude Code writes finished extractions here
- `uploaded/` — moved here after a successful import
- `failed/` — moved here on validation failure, alongside a `.log` naming every problem

The `prompts` table from §6.5 is retained for render and composer prompts. Extraction prompts live on disk instead, because that is where Claude Code can read them.

### 8.2 File format

One file per book per pass. YAML frontmatter for metadata, strict markdown body. Human-readable so it can be eyeballed and hand-corrected, strict enough to parse without heuristics.

```markdown
---
book_slug: financial-coaching-playbook
source_txt: book-scans/The Financial Coaching Playbook - Kelsa Dickey.txt
pass: artifacts
extracted_at: 2026-08-08
---

## CHECKLIST: Financial Documents to Gather
chapter: 3
pages: 128-140
verbatim: yes
confidence: 0.95
intro: What the client brings to the first meeting.

### Income
- Last two pay stubs
- Prior year W-2 and 1099s

### Assets
- Most recent brokerage statement
```

The `## TYPE: Title` header carries the §6.2 taxonomy in caps — CHECKLIST, QUESTION_SET, SCRIPT, FRAMEWORK, WORKSHEET, COMPARISON, STAT, PROCEDURE, WORKED_EXAMPLE, TABLE, QUOTE. `###` headers become `artifact_items.group_label`. List items become items in order.

### 8.3 The five passes

**Pass 1 — Book profile.** Front matter, TOC, first and last chapter. Title, subtitle, authors, year, publisher, category, terms, central thesis, and a "what it covers" list. (Maps to the CENTRAL THESIS and AUTHOR AND CONTEXT blocks in the existing `_concepts.txt` files.)

**Pass 2 — Chapters.** Per chapter: `core_idea`, a 150–300 word `summary`, and 3–8 key concepts.

**Pass 3 — Big ideas.** Input is **the Pass 2 output, not the full text.** Produces 5–15 big ideas plus caveats. Reading summaries rather than raw text is both cheaper in context and better at cross-chapter synthesis.

**Pass 4 — Verbatim artifacts.** The opposite instruction from the other three: they summarize, this one transcribes. Full §6.2 taxonomy.

Pass 4 prompt rules, stated explicitly:

- Transcribe exactly as written. Do not paraphrase, condense, or improve.
- Preserve original order and grouping.
- Do not invent items to complete a pattern.
- Record start and end page.
- Report a confidence score.
- If a list is illustrative prose rather than a real checklist, do not extract it.

**Pass 5 — `links`.** Typed edges from this book's concepts to concepts in *other* books: `supports`, `contrasts`, `expands`, `related`. Like pass 3 it reads extraction output rather than the book, plus a candidate list from the rest of the library, because placing an idea against everything already stored needs the summaries in context at once.

Both endpoints must already exist; the importer skips a link it cannot resolve and counts it rather than creating an edge, since a fabricated relationship renders on the book page as a claim nobody made. The validator rejects a link with no target, an unknown relation, or under forty characters of justification — an unexplained edge is the failure mode this pass is most prone to. An empty links file is a valid answer for a book that genuinely relates to nothing in the library.

Re-running replaces only that book's `origin='extraction'` edges, so hand-made links and other books' claims about it survive.

### 8.4 Validation

**Enforced in code, not trusted to the model.** `worker/validate_extraction.py` runs before import and checks:

1. Frontmatter present and complete; `source_txt` resolves to a real file.
2. Every `## TYPE:` is in the §6.2 taxonomy.
3. Page ranges fall inside the book's page count.
4. **For `verbatim: yes` artifacts, every item appears as a normalized substring of the source `.txt`** — whitespace, case, and punctuation stripped. This is the check that makes "intact" an enforceable property rather than a hope, and it is the whole reason the format carries `source_txt`.

Failures move the file to `failed/` with a log naming each bad item and its nearest match in the source. Fixing means editing the markdown and moving it back to `outbox/` — no re-extraction needed for a stray comma.

Running the validator standalone requires no app and no network, so it is useful from day one.

### 8.5 Import trigger

Deliberately dumb. `worker/import_extraction.py` scans `outbox/`, validates, POSTs to `/api/worker/extractions`, and moves the file to `uploaded/` or `failed/`.

It runs three ways, all equivalent: on the worker's normal poll loop, from a launchd `WatchPaths` trigger on `outbox/`, or by hand. The manual path is explicitly supported and is the expected default early on — finish an extraction in Claude Code, run the importer, see it land.

Import is idempotent on `(book_slug, pass)`. Re-importing a corrected file replaces that pass's rows rather than duplicating them.

---

## 9. The builder

### 9.1 Navigation — library first

`/library` — cover grid, exactly the shape that works in James's app. Each card shows the cover, title, author, year, and a badge with the asset count. Hovering exposes the triage row. Filters down the left: category, recently added, recently rendered, unextracted, no assets yet.

The grid defaults to `scope = press`. Reference-scope books are reachable through a filter and remain full-text searchable, but never appear in the builder, the composer, or coverage. An intake badge shows the count of unconfirmed new arrivals.

Covers come free — page 1 of the PDF rendered at 400×600 during ingest. Manual replacement is supported for the cases where page 1 is a blank or a scan artifact.

`/library/{book}` — book detail. Cover and metadata at top, chapter list in the left sidebar, and the body listing chapters, big ideas, key concepts, and artifacts. Every row carries a **Build image** action.

`/library/{book}/build/{subject_type}/{id}` — the shotgun.

### 9.2 The mockup shotgun

The core flow, replacing the earlier single-path design:

1. Pick a subject — chapter, big idea, key concept, artifact, stat, comparison.
2. The app generates **4–6 candidate treatments in one text-model call** (see §9.9 for which model). Each candidate carries a label and one-line rationale, a visual type, a self-contained HTML mockup, the exact copy strings, a ready-to-run Gemini prompt, and a `fidelity` classification.
3. **Contact sheet** — all candidates rendered to thumbnails side by side.
4. Operator picks one and chooses a route.
5. Output is a **standalone flat image file**, downloadable, ready to drag into Substack.
6. Reroll either route. Every attempt kept as a version.

The economics are the point. Candidates are text tokens — six of them cost a fraction of a single Gemini image. Browse many, spend on one.

### 9.3 Two routes, one output shape

**Render** — the HTML mockup goes to the browse daemon and comes out as a PNG at full size. Free, instant, text exactly as written. Correct for diagrams, frameworks, comparisons, data, checklists, and process flows — which is most of what a business book yields.

**Generate** — the Gemini prompt produces an image. Costs money, right for illustrated and metaphorical treatments.

Both emit one flat image file. Nothing about this requires compositing at upload time, and nothing depends on Substack supporting anything beyond a plain image upload.

A third route, **hybrid**, remains available where an illustrated look is wanted but the copy is text-heavy: Gemini generates the illustration text-free, typography is overlaid in HTML, and the composite is flattened to a single PNG by the worker. Still one standalone file.

### 9.4 Route recommendation

**Calibrated 2026-08-07 against real output** (`worker/calibration-pro-2k.png`). Nano Banana Pro rendered a deliberately text-heavy test infographic — 20+ words across a headline, three numbered captions, and a full takeaway sentence — with zero spelling errors, correct typographic apostrophes, and accurate palette. The original rule assumed image models degrade as text volume rises. On this model, at this volume, they do not.

The routing question is therefore **not how much text, but whether the text must be exact**:

- **`fidelity = exact`** → **Render.** Anything where a wrong character is a factual error: stat cards with specific figures, dollar amounts, checklists and question sets transcribed from books, dates, citations, anything carrying a source page number. Deterministic rendering guarantees the string; generation only makes it very likely.
- **`fidelity = editorial`** → **Generate.** Headlines, takeaways, panel captions, section labels. Copy you will read before publishing anyway, where a rephrasing is a rewrite rather than an error.

Candidates carry a `fidelity` classification and the UI recommends accordingly. Always overridable.

The residual risk on the Generate route is silent substitution — a model producing plausible copy that is not the copy supplied. The handoff screen therefore shows the intended copy strings beside the generated image for a read-through before the asset is accepted. That check costs seconds and catches the one failure mode that survives.

### 9.5 Prompt construction

The Gemini prompt is built from the chosen mockup, so composition, copy, and palette are already decided rather than left to chance. It carries subject matter, visual metaphor, layout description, exact text strings, palette hex values, aspect ratio, style directives, and negative instructions. It is fully editable in the workbench before firing, and the fired version is snapshotted onto the asset.

### 9.5b Art direction

A prompt template fixes the composition, so every image comes out the same shape. `ArtDirection` moves the per-concept decisions to a model that has read the concept: the visual metaphor, how the picture is arranged, the exact labels, the takeaway line, the accent colours, and the cliché to avoid for that particular subject. Output is schema-constrained, because a missing field is a broken render rather than a slightly worse sentence.

The split is the point. Everything that could drift the brand stays hardcoded in `ImagePrompt::fromDirection()` — palette anchors, the bizorca.com attribution, six-word label discipline, the avoid list. The directing model supplies only what should vary. A bad art-direction call therefore produces a strange picture, never an off-brand one.

Opus 4.8 by explicit operator choice rather than the app default. One call is roughly a cent against $0.134 for the image it directs, and it is opt-in per render — the template remains the instant, free path.

A direction can also be **authored ahead of time** rather than generated at render. Posts carry `gemini_structure` and `gemini_direction` (migration 011), written into the draft file as a trailing `---GEMINI---` block by a Claude Code pass over `bizorca-drafts/` and imported with the draft. The generate page prefers a stored direction over both the template and a fresh model call, because it has been read by a human, which neither of the other two can claim. `?direct=1` still overrides so a stored direction can be re-rolled, and switching the structure picker away from the stored shape falls back to generating — changing the shape is exactly the case where the old composition no longer applies. Only the direction is stored, never the assembled prompt: materialising a page of brand invariants into 415 rows would copy them 415 times and stale every one the day the palette changes. What was actually sent to Gemini is already recorded per render in `assets.prompt_snapshot`.

### 9.6 Output sizes

Default 1456px wide, matching Substack's content column. Landscape 1456×816, square 1456×1456, social 1080×1080.

Generation targets 2K and the worker downscales to 1456 — sharper than generating at 1K, and Nano Banana Pro prices 1K and 2K identically.

### 9.7 Guardrail on verbatim material

Verbatim artifacts are stored intact because that is what makes them useful internally. They carry `verbatim = TRUE`, and any composer or asset request that pulls one receives it with a transform-do-not-reproduce instruction attached. The store keeps the original; the output carries the operator's own expression. A constraint on one code path in prompt assembly, not a limit on what gets collected.

---

### 9.8 Gemini access and model selection

Access is via the **Gemini Developer API** with an AI Studio key, not Vertex AI. A Google Cloud project is required only for billing; the full Vertex/Cloud Console path buys nothing here. **No image model has a free tier** — the project must be on the paid tier. A budget alert on that project is mandatory, since a runaway reroll loop is the realistic failure mode. Paid tier also means prompts are not used to improve Google's products, which matters given prompts carry text extracted from in-copyright books.

Current models and standard-tier pricing:

- `gemini-3-pro-image` — Nano Banana Pro. $0.134 at 1K–2K, $0.24 at 4K.
- `gemini-3.1-flash-image` — Nano Banana 2. $0.067 at 1K, $0.101 at 2K, $0.151 at 4K.
- `gemini-3.1-flash-lite-image` — Nano Banana 2 Lite. $0.034 at 1K.
- `gemini-2.5-flash-image` — legacy, not used.

**Default the Generate route to `gemini-3-pro-image`.** Two attempts on Nano Banana 2 at 2K cost $0.202 against $0.134 for one on Pro, so Pro pays for itself if it cuts the reroll rate to two-thirds — and infographics are exactly its case, being dense composition with text that must render legibly. Nano Banana 2 is the right model for the hybrid route's text-free illustrated backgrounds, where the job is easy and quality-per-attempt is already high.

The Batch API is half price but asynchronous, so it is wrong for the interactive shotgun. Reserve it for bulk backfill across an already-ingested library.

API shape (Interactions API, now GA — not the older `generate_content`):

```python
interaction = client.interactions.create(
    model="gemini-3-pro-image",
    input=prompt,
    response_format={"type": "image", "aspect_ratio": "16:9", "image_size": "2K"},
)
image_bytes = base64.b64decode(interaction.output_image.data)
```

Generated images carry Google's SynthID watermark. Not a blocker for commercial publication, but a known property of every asset produced on this route.

`worker/test_gemini.py` is the smoke test: it verifies the key and billing, and renders a deliberately text-heavy infographic so the §9.4 route rule can be calibrated against real output rather than assumption.

### 9.9 Text model for candidate generation

The shotgun needs a text model to write mockup HTML and Gemini prompts. Extraction moved to Claude Code (§8), but the shotgun is interactive and cannot use a file handoff without ruining it, so this one call stays in the app.

**Default to Gemini** (`gemini-3.1-pro` or equivalent current text model) using the key already configured for image generation. One vendor, one key, one bill, no new account. The task is bounded — produce structured JSON with HTML mockups and prompt strings — and well within what the Gemini text models handle.

Anthropic API remains the fallback if candidate HTML quality proves inadequate. It is a config setting, not an architectural commitment.

There is also an escape hatch that costs nothing: candidate rounds for important assets can be prepared in Claude Code and dropped into `candidates` through the same file-import path as extractions. Batch-prep the covers of a launch series by hand, use the in-app shotgun for everything else.

---

## 10. Brand system

One tokens file, `src/View/brand.css`, consumed by every HTML render path and by the tool PDFs. Values from the existing generators:

```
--navy:  #1B3A5C     --navy-2: #142C47
--gold:  #D4A017     --ink:    #26303B
--muted: #58616D     --grey:   #8A8F98
--cream: #F7F3E7     --paper:  #F5F7FA
body:    Georgia, 'Times New Roman', serif
label:   Helvetica, Arial, sans-serif — uppercase, letterspaced, gold
rule:    linear-gradient(90deg, gold 0%, navy 70%)
footer:  bizorca.com · © 2026 Bizorca LLC
```

Mascot: the male deep-sea anglerfish. Used as the favicon and the empty-state illustration, drawn once as an inline SVG in the navy and gold palette so it scales and re-colors with everything else. Not applied to generated assets — published infographics carry the Bizorca chrome from §9, not the mascot.

**Consolidation requirement:** port the four PIL card generators to HTML templates and retire PIL. Every render route is already HTML, and one renderer means one palette definition and one command that re-renders every asset ever made when the brand shifts. The PIL scripts move to `press/reference/` as layout source of truth during the port.

---

## 11. The desk

### 11.1 Triage
A persistent keyboard row on every reviewable subject — books, chapters, concepts, artifacts, candidates, assets, posts:

```
F  Favorite    R  Read    W  Write About This    M  Meh    X  Reroll    →  next
```

`W` writes a `triage` row and pushes the subject onto the composer queue, with the count in the sidebar. This is what converts a 415-item backlog from a folder into something that can be burned down in an evening.

### 11.2 Composer
`/compose/{subject_type}/{id}`. Fields: **Your story / angle**; **How to use the visual**; **Format** (one of six); **Length** (short 150–250 / medium 400–600 / long 800–1200); **Audience** (practitioners, tax professionals, coaches & consultants, real estate investors); **Voice preset** (Bizorca Press / Heartfelt Finance / Book); **Model** with rate displayed; **Extra instructions**.

Output: subject, subtitle, body as rich HTML with copy-body, plus a standing signature block stored per publication.

**The angle is optional** (revised 2026-08-08). It was mandatory for book-sourced material, because composing from another author's chapter summary without a lived angle drifts into book-report register immediately and reads fine while not being his. The requirement was dropped because every post is hand-edited before it ships, and an editor catches that drift better than a required field does.

What replaces it is narrower and still enforced in the prompt: with no angle, the composer is told to write from the source alone and **invent nothing personal — no clients, no anecdotes, no numbers absent from the source**. That is the part that matters, since a fabricated client is expensive to spot after the fact whereas flat register is obvious on the first read. Verified on a book-sourced draft with an empty angle: no invented clients, history or anecdotes. It will still write a generic first-person line ("I've filled it"), which is a voice move rather than a fabricated specific.

Voice presets assemble their system prompt from `memory/writing-style.md` plus the format spec in `writer/CLAUDE.md`.

**Sources.** A composition is written from one of six things: a `concept` (including all 580 Big Ideas sections), a `chapter`, an `artifact` (kit tool or book checklist), a `clipping` (saved corpus excerpt), a `post` (remix), or `freeform`. Every source screen carries **Compose** and **Image** actions, and `/compose` itself has a picker — before this, the nav link dead-ended in freeform and none of the library was reachable, which also meant the angle guardrail never fired in practice.

**Context expansion (§11.2a).** Big Ideas sections are breadth — a compressed, attributed synthesis. Writing from one alone produces a post that is correct and thin. `Services\Expand` pulls the depth back: one FULLTEXT query over all 12,717 pages returns related passages from every other press-scope book, including the other brain's take on the same topic. Retrieval is generous and filtering is left to the composer, which is already reading the prompt. Two gates keep it usable — an OCR-readability check (half the library is scanned) and a two-term overlap requirement (natural-language mode will match on one common word). Attribution travels with every passage, because "Kennedy calls this…" is half the value of this corpus.

The deeper tier — grep over the 38,086-file corpus — stays a job, and stays on the Mac until the 1.26GB of bodies is synced.

**The composer runs on Claude** (`claude-opus-5`) via the official PHP SDK, server-side. Voice matching against a long, specific style guide is the task this model is best at, and the composer is the one place in the system where getting the voice wrong is the whole failure. Measured 21s server-side against Gemini's 29–52s.

The prompt is split rather than concatenated: `Compose::system()` is the voice spec, the six format definitions, and the output contract — byte-identical on every call and marked with a cache breakpoint (1,485 tokens, read back at ~0.1× cost). `Compose::user()` is the per-post block. `Compose::prompt()` returns `system() . "\n\n" . user()` for providers with no system channel, so the two paths cannot drift; a test asserts the equality.

Gemini stays reachable by setting `provider=gemini` in the job payload — a bad Anthropic day is a config change, not a deploy. There is deliberately no UI for it.

Two failure modes are handled explicitly in `Services\Anthropic`, because neither raises: a refusal is a successful HTTP 200 with `stop_reason: "refusal"` and empty content, and adaptive thinking blocks precede the text blocks and carry no text. SDK-level retry is disabled (`maxRetries: 0`) — the default of 2 would turn one 90s timeout into 270s of wall clock and get the PHP process killed before it could record the failure. The job queue owns retries.

### 11.2b Concepts are the unit

A concept — not a chapter — is what gets written about and what gets an infographic, so the book page leads with concepts and collapses chapters to secondary navigation carrying page anchors. This required no data change: `concepts.chapter_id` was always nullable. What it did require was populating `concepts.page_ref` at import, so a concept states where in the PDF it came from without depending on a join.

Chapters are kept rather than removed. They are cheap navigation and the page-range spine, and the reference app this borrows from keeps both layers for the same reason.

Chapters arrive by two independent routes and neither blocks the other. `detect_chapters` proposes them from a TOC or heading scan (`status='proposed'`, never authoritative until confirmed); extraction pass 2 writes its own (`status='extracted'`) from the Pass 1 COVERS list. A book with zero proposals therefore extracts end to end normally — chapter maps affect the compose picker and page anchors, not the pipeline.

**Typed links between concepts** live in `concept_links`: `supports`, `contrasts`, `expands`, `related`. They are directional, cross-book by design, and shown in both directions on the book page — a contrast reads as well backwards as forwards. Pass 5 (§8.3) produces them. The importer resolves both endpoints and skips a link whose far concept does not exist rather than inventing an edge, because a fabricated relationship would render as a claim nobody made. Re-running a book replaces only its own `origin='extraction'` edges, so hand-made links and other books' claims about it survive.

This is distinct from `Expand` (§11.2a), which is untyped, unstored, and recomputed per composition. Links are the browsable graph; Expand is retrieval at write time.

### 11.3 Render targets
Any stored content can be requested as: Substack post (clean semantic HTML for paste, per §11.4), infographic (PNG), 1080 card (PNG), Vault kit page (markdown, `build_vault.py` logic), branded tool PDF (`make_tool_pdfs.py` logic), slide deck (pptx, `make_slides.py` logic), KDP chapter (docx, `make_kdp_docx.py` logic). Each is a `render` job with a `target` in the payload.

### 11.4 Handoff

**Substack's publishing API is deprecated.** `substack_post.py` and its cookie-auth flow are dead and do not carry forward. Publishing is manual: copy the title, copy the subtitle, copy the body, upload the image. James's composer works exactly this way for the same reason, which is a useful confirmation rather than a coincidence.

This makes the handoff surface the most-used screen in the app, so it is specified rather than left to improvisation:

- **Copy title** and **Copy subtitle** — plain text, one click each.
- **Copy body** — writes **both `text/html` and `text/plain` flavors** to the clipboard in a single `ClipboardItem` via `navigator.clipboard.write()`, so pasting into Substack's editor preserves headings, bold, italics, lists, and blockquotes instead of arriving as a wall of plain text. Requires HTTPS and a user gesture; both hold. Fallback for browsers that reject multi-flavor writes: a hidden `contenteditable` node plus `document.execCommand('copy')`.
- **The emitted HTML must be clean and semantic** — `h2`, `h3`, `p`, `strong`, `em`, `ul`, `ol`, `li`, `blockquote`, `a` and nothing else. No `div`, no inline styles, no classes. Substack's editor is ProseMirror-based and silently strips or mangles anything outside its schema, so the composer's HTML serializer is written to that whitelist rather than to general-purpose markdown output.
- **Image** — a Download button writing the standalone PNG to disk, plus a Copy image action putting `image/png` on the clipboard for a direct paste. Download is the reliable path; the copy action is a convenience.
- **Export** — the whole post as `.html` or `.md` to disk, for the cases where the clipboard is the wrong tool.

**Mark published** takes an optional pasted-back Substack URL and a date, sets `published_at`, clears the `write_about` triage mark, and logs the source concept or artifact as used so it stops resurfacing in the queue. Without an API there is no automatic confirmation, so this is an honest manual checkpoint and the only thing that keeps the queue accurate.

`posts.status` values `queued` and `scheduled` therefore mean *operator intends to send* rather than *the platform has accepted*, and `scheduled_for` is operator-entered intent, not a platform commitment.

---

### 11.5 Whole-book summary and audiobook script

`/library/{slug}/summary`. Everything extracted from one book, in one document, and a narrator-ready script made from it — the Cliff's-Notes shape, from material that already exists.

**The outline is deterministic and is not stored.** It is assembled on request from concepts (thesis → big ideas → key concepts → caveats), chapter core ideas, and artifacts, in reading order with page anchors. Rebuilding is instant and free, and a stored copy could only drift from the concepts underneath. `summary.md` downloads it verbatim.

**The audiobook script is stored**, in `book_summaries`, unique on (book, kind). It costs one Claude call at roughly forty seconds, which is why it persists where the outline does not. Each row records `source_sha` — the hash of the outline it was generated from — so the page can distinguish a current script from one written before a re-extraction changed the concepts, rather than silently serving the stale one.

Converting the outline to speech is a transformation, not a reformat: headings, bullet lists, page references and bold text have no spoken equivalent. `worker/prompts/audiobook-script.md` carries all of it in sentences, counts lists aloud ("there are four of these"), expands abbreviations on first use, and speaks figures. Measured on `big-ideas-dk-referrals`: 1,357 words, nine minutes narrated, zero headings, bullets or page references in the output. Runtime is estimated at 150 words per minute, which is what a listener asks about; word count is not.

**Copyright binds harder here than anywhere else in the app**, because this output is product-shaped in a way a draft post is not. A summary that recites another author's verbatim checklists is republication. The outline marks such artifacts `**[verbatim — do not republish]**` and the page counts them in a banner; the script prompt is instructed to describe what a tool does rather than read it out, and to lose a detail rather than reproduce one. This is §9.7 applied to a second surface.

## 12. Admin

Dashboard (counts, queue depth, jobs in flight, spend this month) · Coverage (unconfirmed chapter maps, unextracted chapters, thin kits, over- and under-used formats, concepts never written about) · Re-render (batch by template, route, or brand version) · Prompts (edit and version) · Costs (by day, book, route, model) · Review queue (verbatim validation failures) · Covers (replace auto-extracted covers) · Import.

---

## 13. Worker API

All under `/api/worker`, bearer token from server-side `.env`, rate-limited, IP-logged.

```
POST /api/worker/lease                 { types: [...], capacity: N }
     → [ { job_id, type, payload, lease_token, lease_expires_at } ]
POST /api/worker/jobs/{id}/heartbeat   { lease_token } → { ok, extended_to }
POST /api/worker/jobs/{id}/result      { lease_token, status, data, cost_usd, api_calls: [...] }
POST /api/worker/assets   (multipart)  { asset_id, web_file, thumb_file }
POST /api/worker/extractions           { book_slug, pass, body, source_sha256 }
     → { ok, imported: {...}, replaced: N }   -- idempotent on (book_slug, pass)
```

Leases expire. A worker that dies mid-job releases it back to `queued` after `lease_expires_at` with `attempts` incremented. At `max_attempts` the job goes `dead` and surfaces on the dashboard.

---

## 14. Storage

**Local** (Mac, or `/Volumes/Backup_1/` following the tax-ai precedent):

```
library/<book-slug>/
  source.pdf
  cover.jpg
  text/full.txt
  pages/0001.txt …
  assets/<asset-id>.png      (full resolution)
  assets/<asset-id>.html     (render source)
  candidates/<candidate-id>.png
```

**Server**: database rows, extracted text, web-sized asset copies (max 1600px long edge), tool PDFs, post bodies, cover thumbnails.

**Image formats are PNG and JPEG only. WebP is not used anywhere in this system** — not for assets, not for thumbnails, not for covers. PNG for anything with flat color, text, or transparency; JPEG at quality 88 for photographic and illustrated output where PNG runs large. The 2752×1536 calibration render is 2.1MB as PNG and about 300KB as JPEG at the same visual quality, so illustrated Gemini output defaults to JPEG and deterministic HTML renders default to PNG.

Source PDFs are never uploaded — the existing 51 already total several gigabytes. If asset volume later becomes a problem, `assets.web_path` is the single indirection point needed to move to object storage without touching application code.

---

## 15. Security and access control

### 15.1 SSO

Authentication is Bizorca SSO against `login.bizorca.com`. App slug: **`anglerfish`**. Full flow, JWT payload, and entitlement resolution are documented in `Archipelago/login/CLAUDE.md`.

Registration, in order:

1. Register the app at login.bizorca.com `/admin` → Apps (slug `anglerfish`, callback `https://anglerfish.bizorca.com/sso/callback`, generated secret), or by SQL against the login DB using `thinkrep/migrations/003_register_sso_app.sql` as the template.
2. Add a `plan_entitlements` row granting `anglerfish` at level `full`. Single-operator app, so a manual override on the owner account via `user_entitlement_overrides` is sufficient and preferable to granting it on the free plan — this app should not be free-for-all.
3. Copy `login/sso-client/src/BizorcaSSO.php` into `src/Services/BizorcaSSO.php`. Zero dependencies; do not add the Composer package for one class.
4. Put `SSO_APP_SLUG`, `SSO_APP_SECRET`, and `SSO_SERVER_URL` in the server-side `.env`. Never committed.
5. Build `public/sso-callback.php` using `thinkrep/public/sso-callback.php` as the reference implementation.

The flow: unauthenticated request redirects to `https://login.bizorca.com/authorize?app=anglerfish&redirect=https://anglerfish.bizorca.com/sso/callback` → user authenticates → the server issues a one-time 64-hex auth code valid 60 seconds → the app POSTs `/token` with `app`, `secret`, and `code` → receives an HS256 JWT signed with the app's own secret → verifies it locally, reads `entitlements.anglerfish`, upserts the local user, and starts a local session.

```php
$sso  = new \Bizorca\SSOClient\BizorcaSSO('anglerfish', $secret, 'https://login.bizorca.com');
$user = $sso->handleCallback($_GET['code'], 'https://anglerfish.bizorca.com/sso/callback');
if (!$sso->can($user, 'full')) { /* 403 */ }
```

Every route requires `full`. `trial` and `read_only` are not meaningful here and are treated as no access.

**Redirect threading** must be preserved through the whole login and register chain, per the convention documented in `login/CLAUDE.md`. Breaking it lands an SSO-originated visit on the Bizorca dashboard instead of back in Press.

### 15.2 Local user record

```sql
users (
  id, sso_user_id, email, first_name, last_name,
  sso_access_level,          -- full|trial|read_only
  is_owner BOOL,
  last_login_at, created_at,
  UNIQUE (sso_user_id), UNIQUE (email)
)
```

Upsert on `sso_user_id` first, falling back to `email` for accounts that predate an SSO link. Store `sso_access_level` on every login so a revoked entitlement takes effect on the next session rather than silently persisting.

### 15.3 Everything else

Worker bearer token in server-side `.env`, never committed. CSRF on all POSTs, `h()` on all output, `hash_equals()` for every secret comparison. No web-facing PDF upload in v1 — the worker reads from the watched local directory.

**Key custody, revised.** The original rule was that no API key lives on the server, so a server compromise could not spend money. Moving generation server-side gave that up knowingly: `.env.php` (mode 600, outside the web root, never synced) now holds both a Gemini key and an Anthropic key. The mitigation is no longer location but **budget caps** — both server-side keys should be capped and distinct from any key held elsewhere, so the worst case is a capped bill rather than an open tab. The worker keeps its own uncapped Gemini key in `press/worker/.env` at mode 600.

### 15.4 Deployment

SiteGround, subdomain `anglerfish.bizorca.com`. SSH port 18765, key auth only. Web root `~/www/anglerfish.bizorca.com/public_html/` holding a thin `index.php` shim pointing at the app directory outside the web root, following the established pattern. Deploy by `./deploy.sh` rsync. PHP 8.2 on the server — never 8.3+ syntax. No build step, no npm.

---

## 16. Build phases

**Phase 1 — Ingest and text.** Schema, migrations, SSO, job queue, worker skeleton. Probe, conditional OCR, hygiene, page rows, cover extraction. Import the 26 existing `.txt` files on their page markers.
*Accept:* all 51 books registered; the three known text-layer books ingest in seconds via the fast path; one image-only scan OCRs correctly at `--psm 1` and reads cleanly end to end; existing text imports without re-OCR.

**Phase 2 — Structure and library.** Chapter detection, confirm UI, printed-page mapping, the `/library` cover grid and book detail views.
*Accept:* chapter boundaries and page ranges correct on five books after confirm; citations resolve to the right printed page; the grid is navigable to any chapter in two clicks.

**Phase 3 — Extraction.** The four pass prompts in `extractions/prompts/`, the validator, the importer, and the review queue. Reformat the 19 existing `_concepts.txt` files into the §8.2 format and import them.
*Accept:* every extracted checklist passes verbatim substring validation against its source `.txt` or is correctly flagged into `failed/`; zero silent acceptances of invented items. The validator runs standalone with no app and no network.

**Phase 4 — Template rendering.** PIL layouts ported to HTML, brand tokens consolidated, Render route live.
*Accept:* all 20 Vault tools and a sample of the six card formats re-render from database rows, visually matching the current PDFs and PNGs. Zero API spend in this phase.

**Phase 5 — Desk.** Import 415 drafts, 20 kits, both Brain indexes. Triage, composer, voice presets, handoff surface.
*Accept:* idea to a Substack draft sitting in the browser — title, subtitle, body, and image all pasted in — in under ten minutes, measured end to end including the paste. Body formatting must survive the paste intact. **If this fails, stop and fix it before Phase 6.**

**Phase 6 — The shotgun.** Candidate generation, contact sheet, prompt workbench, Generate and hybrid routes, cost display, reroll chains.
*Accept:* from a cold start on an unseen chapter, a publishable standalone image in under five minutes and under two Gemini calls, averaged over twenty attempts.

---

## 16a. Build status — 2026-08-08

Phases 1 and 2 are live, plus the Phase 5 data layer and triage.

Done: schema (25 tables), SSO, job queue with leases, intake scan, ingest with the
text-layer fast path, `import_existing`, extraction import for all four passes,
bulk content import, library cover grid, book detail, F/R/W/M/X triage, write queue.

Data in place: 50 books registered, 26 with text (6,707 pages, 16.1MB), 20 kits,
410 drafts, 38,086 corpus sources.

Chapter detection and the confirm UI landed 2026-08-08, completing Phase 2:
160 chapters proposed across 13 books, awaiting confirmation.

Phase 4 (template rendering) landed 2026-08-08: brand tokens consolidated into
`src/View/brand.css`, PIL cards ported to HTML, 20 Vault tools imported as
kit-linked artifacts and re-rendered as one-page branded PDFs from database
rows. Zero API spend, as specified.

The handoff surface landed 2026-08-08 (§11.4): post list, editable handoff
screen, ProseMirror-safe HTML with dual-flavor clipboard copy, placeholder
warnings, and mark-published. It runs against the 410 existing drafts with no
model dependency.

The worker daemon landed 2026-08-08: `com.bizorca.anglerfish.worker` under
launchd, adaptive polling (5s active, 30s idle), rotating log. Jobs now run
without anyone remembering to start the worker.

Not started: the composer's generation step (§11.2 — needs a text model, and the
Gemini key lives on the worker, so it wants to be a job rather than an inline
call), and the mockup shotgun (Phase 6).

## 17. Open questions

1. **Shotgun text model** — Gemini (one vendor, key already live) or Anthropic API (better HTML, new billing relationship). §9.9 recommends Gemini for v1; worth revisiting after the first twenty candidate rounds.
2. **Key longevity** — the working key has an `AQ.` prefix rather than the standard `AIza`, which is associated with session-scoped tokens. If auth fails unexpectedly, issue a standard key from AI Studio's API keys page.

Resolved since v2: Substack publishing (§11.4 — API deprecated, manual copy-paste handoff); corpus scope (§7.2 — press vs. reference, WSU manuals in scope, everything ingested and searchable but only press-scope reaches the builder); image builder flow (§9.2 — mockup shotgun, two routes, standalone image output); navigation (§9.1 — library cover grid first); authentication (§15.1 — Bizorca SSO, slug `anglerfish`, `full` required).

Resolved since v4: extraction route (§8 — Claude Code CLI with a file handoff, no Anthropic API); image formats (§14 — PNG and JPEG only, never WebP); domain (anglerfish.bizorca.com); Gemini access verified end to end (§9.8).
