-- 017 — Hypnologue, and the publication as a first-class thing.
--
-- Migration 016 was the first admission that this app serves more than one
-- masthead: a Hypnologue graphic came out stamped with bizorca's copyright,
-- and the fix was a key on `posts` naming which footer to print. That solved
-- the footer and nothing else. Everything else a publication owns — which
-- voice the composer writes in, which model it writes with, which books it may
-- draw on, whether a post needs a citation before it can ship — was still
-- either hardcoded to Bizorca or carried in the operator's head.
--
-- This migration makes the publication the row that owns those answers.
--
-- Deliberately NOT a second install. Hypnologue and Bizorca Press share the
-- library, the extraction pipeline, the image pipeline and the job queue, and
-- a second database would mean ingesting every book twice and maintaining two
-- copies of ImagePrompt. They differ in voice, model, source material and
-- evidence standard, and those are columns.

-- ── The publications ─────────────────────────────────────────────────────────
--
-- `pubkey` rather than `key`: KEY is reserved in MySQL and an unquoted column
-- named `key` breaks every hand-written query later. It matches the keys
-- already in ImagePrompt::ATTRIBUTIONS, because a second naming scheme for the
-- same two things is how the footer bug happens again.
--
-- model and effort live here because they are a publication's decision, not a
-- global one. Hypnologue writes on claude-fable-5-1: the posts are short but
-- the work is dense — an argument, a study that may disagree with it, and a
-- citation that has to actually support what the paragraph claims. Bizorca
-- stays on claude-opus-5, where 1,400 words of tactical prose has never wanted
-- more. NULL means "whatever config/app.php says", so nothing breaks if a row
-- is added without one.
--
-- citation_required is the column this publication exists for. Hypnologue's
-- angle is hypnosis without the mystique, which is only worth anything if the
-- claims survive checking. A post without a verified peer-reviewed citation is
-- not a Hypnologue post.

CREATE TABLE IF NOT EXISTS publications (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pubkey            VARCHAR(32)  NOT NULL,
  name              VARCHAR(120) NOT NULL,
  domain            VARCHAR(160) NULL,
  substack_url      VARCHAR(255) NULL,
  tagline           VARCHAR(500) NULL,
  -- Resolves to worker/prompts/voice-<preset>.md. Same mechanism the composer
  -- already used for bizorca_press; this just stops it being a default.
  voice_preset      VARCHAR(60)  NOT NULL DEFAULT 'bizorca_press',
  -- Resolves to worker/prompts/format-<slug>.md. NULL keeps the existing
  -- behaviour of loading formats.md + format-weekly.md.
  format_spec       VARCHAR(60)  NULL,
  -- Exact model id. Never a family name — 'opus' is not a model and the API
  -- rejects it. NULL falls back to config/app.php.
  model             VARCHAR(80)  NULL,
  -- output_config.effort: low | medium | high | xhigh | max. NULL sends none,
  -- which the API reads as 'high'.
  effort            VARCHAR(10)  NULL,
  -- The ImagePrompt::ATTRIBUTIONS key. Same vocabulary as migration 016.
  attribution       VARCHAR(32)  NOT NULL DEFAULT 'bizorca',
  citation_required TINYINT(1)   NOT NULL DEFAULT 0,
  -- How many verified citations a post needs before it may be promoted.
  -- Only consulted when citation_required = 1.
  citation_min      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  active            TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pub_key (pubkey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO publications
  (pubkey, name, domain, substack_url, tagline, voice_preset, format_spec,
   model, effort, attribution, citation_required, citation_min)
VALUES
  ('bizorca', 'Bizorca Press', 'bizorca.com', 'https://www.bizorca.com',
   'Practical business strategy for practitioners.',
   'bizorca_press', NULL, NULL, NULL, 'bizorca', 0, 1),
  ('hypnologue', 'Hypnologue', 'hypnologue.net', 'https://hypnologue.substack.com',
   'Closer than conscious: the science and practice of hypnosis.',
   'hypnologue', 'hypnologue', 'claude-fable-5-1', 'high', 'hypnologue', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ── Which publication a post belongs to ──────────────────────────────────────
--
-- Defaulting to 1 (bizorca, the first insert above) keeps all 410 existing
-- drafts exactly where they are. `attribution` from 016 stays: it is the
-- printed footer key and a post may in principle carry a different one from
-- its publication's default, which is why 016 put it on the post in the first
-- place. The publication supplies the default; the post remembers the choice.

ALTER TABLE posts
    ADD COLUMN publication_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_posts_pub (publication_id, status),
    ADD CONSTRAINT fk_posts_pub FOREIGN KEY (publication_id)
        REFERENCES publications(id);

ALTER TABLE compositions
    ADD COLUMN publication_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_comp_pub (publication_id, status),
    ADD CONSTRAINT fk_comp_pub FOREIGN KEY (publication_id)
        REFERENCES publications(id);

-- The six daily formats and `weekly` are Bizorca shapes. Hypnologue has its
-- own, and rather than grow the enum every time a publication is added, an
-- eighth value covers "this publication's format spec decides". Appending to a
-- MySQL ENUM does not renumber existing values, so no stored row is touched —
-- the same reasoning migration 012 used to add `weekly`.
ALTER TABLE posts
    MODIFY COLUMN format ENUM('before_noon','gut_check','steal_this','the_upgrade',
                              'the_protocol','one_number','weekly','essay') NOT NULL;

ALTER TABLE compositions
    MODIFY COLUMN format ENUM('before_noon','gut_check','steal_this','the_upgrade',
                              'the_protocol','one_number','weekly','essay') NOT NULL;

-- ── Which publication may draw on which source material ──────────────────────
--
-- A join table, not a column on `books`, because the relationship is many to
-- many and pretending otherwise would be wrong in both directions. Cialdini
-- serves Bizorca (persuasion in a sales conversation) and Hypnologue (the
-- compliance literature is directly relevant to suggestion). A Kindle capture
-- of a hypnosis script book serves only Hypnologue. The WSU extension manuals
-- serve neither.
--
-- subject_type is polymorphic over the three things the composer can be handed
-- as a source — the same vocabulary compositions.subject_type already uses —
-- so a single concept from an otherwise irrelevant book can be routed without
-- routing the book.
--
-- relevance is 0.00-1.00 and orders the picker. source records who decided:
-- 'auto' rows are written by the classifier and replaced wholesale on a re-run,
-- 'manual' rows are the operator's and are never touched. This is the same
-- contract module_map uses, and it is what makes the classifier safe to re-run.

CREATE TABLE IF NOT EXISTS publication_scope (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  publication_id INT UNSIGNED NOT NULL,
  subject_type   ENUM('book','concept','artifact') NOT NULL,
  subject_id     INT UNSIGNED NOT NULL,
  relevance      DECIMAL(3,2) NOT NULL DEFAULT 0.50,
  source         ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  note           VARCHAR(500) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_scope (publication_id, subject_type, subject_id),
  KEY idx_scope_subject (subject_type, subject_id),
  KEY idx_scope_pick (publication_id, subject_type, relevance),
  CONSTRAINT fk_scope_pub FOREIGN KEY (publication_id)
      REFERENCES publications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Citations ────────────────────────────────────────────────────────────────
--
-- The rule this table enforces: every Hypnologue post carries at least one
-- peer-reviewed citation that was RETRIEVED, not recalled.
--
-- Post 001 is why. Two claims in it failed verification after drafting — a
-- definition attributed to Hilgard that appears nowhere in his work, and a flat
-- statement about Braid coining "hypnotism" that the literature contradicts.
-- Both were fluent, both were plausible, and neither was true. A model asked
-- for a citation will produce one that looks exactly like this table's contents.
-- The defence is not a better prompt. It is that `verified_at` can only be set
-- by code that fetched the DOI from CrossRef and got a record back.
--
-- So: the composer may propose, and proposing sets nothing. `verified_at` NULL
-- means unverified and a post with only unverified citations cannot be
-- promoted. `resolves` is a second, separate check — CrossRef can hold a record
-- for a DOI that no longer resolves at doi.org.
--
-- supports = 'contradicts' is a first-class value, not a failure state. This
-- publication's whole approach is that a source disagreeing with the claim is
-- part of the story; post 001 is built on Spanos and Hewitt arguing against the
-- finding it describes. A post citing only agreeable sources is the thing to be
-- suspicious of.

CREATE TABLE IF NOT EXISTS citations (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id        INT UNSIGNED NULL,
  composition_id INT UNSIGNED NULL,
  doi            VARCHAR(255) NOT NULL,
  title          VARCHAR(1000) NULL,
  authors        VARCHAR(1000) NULL,   -- "Gorassini, D. R., & Spanos, N. P."
  container      VARCHAR(500) NULL,    -- journal name
  year           SMALLINT     NULL,
  volume         VARCHAR(40)  NULL,
  issue          VARCHAR(40)  NULL,
  pages          VARCHAR(60)  NULL,
  -- What this source does to the claim it is attached to.
  supports       ENUM('supports','contradicts','complicates','background')
                   NOT NULL DEFAULT 'supports',
  -- The sentence or claim in the post this citation is attached to. Written by
  -- whoever added it; the point is that a citation floating free of a claim is
  -- decoration.
  claim          TEXT NULL,
  -- Set only by code that fetched the record. Never by a model, never by hand.
  verified_at    DATETIME NULL,
  -- Did https://doi.org/<doi> resolve? Separate from being in CrossRef.
  resolves       TINYINT(1) NULL,
  -- The CrossRef record as returned, so a later dispute can be settled without
  -- re-fetching and so a changed record is detectable.
  crossref_json  JSON NULL,
  peer_reviewed  TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cit_post (post_id),
  KEY idx_cit_comp (composition_id),
  KEY idx_cit_doi (doi),
  KEY idx_cit_verified (verified_at),
  CONSTRAINT fk_cit_post FOREIGN KEY (post_id)
      REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cit_comp FOREIGN KEY (composition_id)
      REFERENCES compositions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Corpora that are not books ───────────────────────────────────────────────
--
-- Three of Hypnologue's most important sources have no `books` row and never
-- will: the Mike Mandel Academy and podcast notes (8,714 items across 940 JSON
-- files), the Brain Software podcast transcripts (317 episodes, 227 hours), and
-- the QHHT Level 1 course audio (13 lessons, 141,914 words). They are not PDFs
-- in the watched intake folder, they have not been through the five extraction
-- passes, and putting 318 podcast episodes through intake as 318 books is a
-- decision rather than a side effect.
--
-- They still have to be routable and, more importantly, ATTRIBUTABLE. A post
-- built on Mandel material that does not say so is passing off a named
-- practitioner's craft as the author's own, and a post built on QHHT material
-- has a specific provenance a reader is entitled to.
--
-- `caution` is the column that earns this table. The Mandel corpus is
-- excellent on technique and unreliable on attribution and science — §20 of
-- mandel-reference.md is a standing audit of claims in it that outrun their
-- evidence, and post 001 of Hypnologue shipped two of them before anyone
-- checked. So the corpus is a source of LEADS, never of citations, and that
-- sentence has to travel with the material rather than living in a file the
-- composer never reads.

CREATE TABLE IF NOT EXISTS publication_sources (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  publication_id INT UNSIGNED NOT NULL,
  slug           VARCHAR(80)  NOT NULL,
  name           VARCHAR(255) NOT NULL,
  kind           ENUM('course','podcast','notes','transcripts','corpus')
                   NOT NULL DEFAULT 'corpus',
  -- Where it lives, relative to the writer/ directory. Local only; none of this
  -- is uploaded, exactly like books.source_path.
  local_path     VARCHAR(500) NULL,
  -- Printed or spoken credit. Never optional, never inferred at render time.
  attribution    VARCHAR(500) NOT NULL,
  -- What a writer must know before using it. Shown next to the material.
  caution        TEXT NULL,
  -- 1 = may be quoted as evidence for a factual claim. 0 = leads only; any
  -- claim taken from here needs its own peer-reviewed source.
  citable        TINYINT(1) NOT NULL DEFAULT 0,
  item_count     INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pubsrc (publication_id, slug),
  CONSTRAINT fk_pubsrc_pub FOREIGN KEY (publication_id)
      REFERENCES publications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO publication_sources
  (publication_id, slug, name, kind, local_path, attribution, caution, citable, item_count)
SELECT p.id, v.slug, v.name, v.kind, v.local_path, v.attribution, v.caution,
       v.citable, v.item_count
  FROM publications p
  JOIN (
    SELECT 'mandel-academy' AS slug,
           'Mike Mandel Hypnosis Academy — structured notes' AS name,
           'notes' AS kind,
           'Mike Mandel Brain/academy/notes' AS local_path,
           'Mike Mandel Hypnosis Academy — Mike Mandel and Chris Thompson' AS attribution,
           'LEADS, NEVER CITATIONS. The craft and technique are excellent; the attributions and the science are not reliable. Section 20 of Mike Mandel Brain/mandel-reference.md is the standing audit — the bits-per-second and connections-per-second figures, PKC as memory storage, PGO spikes, left/right brain, the fibromyalgia, migraine and allergy "cures", ego-state counts, and the recovered-memory protocol all appear in this corpus and none of them should be repeated. Post 001 of Hypnologue took two claims from material like this and both failed verification. Use it to find out what to go and check; never as the support for a claim.' AS caution,
           0 AS citable, 4588 AS item_count
    UNION ALL
    SELECT 'mandel-podcast',
           'Brain Software with Mike Mandel — episode notes',
           'notes',
           'Mike Mandel Brain/notes',
           'Brain Software with Mike Mandel and Chris Thompson (podcast)',
           'LEADS, NEVER CITATIONS — see mandel-academy. Also: the file position in this set is NOT the episode number, so never cite an episode by its filename.',
           0, 4126
    UNION ALL
    SELECT 'brain-software-transcripts',
           'Brain Software with Mike Mandel — full transcripts',
           'transcripts',
           'podcasts/brain-software/transcripts',
           'Brain Software with Mike Mandel and Chris Thompson (podcast)',
           'Machine transcription (Whisper large-v3), 318 episodes. Proper nouns are unreliable — the show has a fixed cast Whisper mishears, and run.sh carries an explicit substitution list for the ones that were caught. Spot-check any name before printing it. LEADS, NEVER CITATIONS.',
           0, 318
    UNION ALL
    SELECT 'qhht-level-1',
           'Quantum Healing Hypnosis Technique, Level 1 — course audio',
           'course',
           'QHHT/transcribed/transcripts',
           'Quantum Healing Hypnosis Technique (QHHT), Level 1 course — method originated by Dolores Cannon',
           'PURCHASED COURSE MATERIAL, recorded off playback. Do not reproduce it — not the scripts, not the induction wording, not the procedure as taught. Describe a technique in your own words or leave it out. QHHT makes claims about past-life recall and healing that this publication cannot assert as fact; write about what the method DOES and what practitioners believe, never about what it proves. Machine transcription, so spot-check proper nouns. LEADS, NEVER CITATIONS.',
           0, 13
  ) v
 WHERE p.pubkey = 'hypnologue'
ON DUPLICATE KEY UPDATE
  name        = VALUES(name),
  attribution = VALUES(attribution),
  caution     = VALUES(caution),
  item_count  = VALUES(item_count);
