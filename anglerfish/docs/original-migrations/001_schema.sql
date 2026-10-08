-- Anglerfish Press — 001 schema
-- Implements SPEC.md §6. MySQL 8 / utf8mb4.
-- No CREATE DATABASE / USE — migrate.php strips them and SiteGround rejects them.

-- ── Users (SSO-backed, §15.2) ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sso_user_id       VARCHAR(64)  NULL,
  email             VARCHAR(255) NOT NULL,
  first_name        VARCHAR(100) NULL,
  last_name         VARCHAR(100) NULL,
  sso_access_level  ENUM('full','trial','read_only') NULL,
  is_owner          TINYINT(1)   NOT NULL DEFAULT 0,
  last_login_at     DATETIME     NULL,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_sso   (sso_user_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Library (§6.1) ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS books (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug                 VARCHAR(160) NOT NULL,
  title                VARCHAR(500) NOT NULL,
  subtitle             VARCHAR(500) NULL,
  year                 SMALLINT     NULL,
  publisher            VARCHAR(255) NULL,
  isbn                 VARCHAR(20)  NULL,
  source_path          VARCHAR(1000) NOT NULL,   -- local; PDFs are never uploaded
  source_sha256        CHAR(64)     NOT NULL,
  scope                ENUM('press','reference') NOT NULL DEFAULT 'reference',
  scope_confirmed      TINYINT(1)   NOT NULL DEFAULT 0,
  pdf_page_count       SMALLINT UNSIGNED NULL,
  has_text_layer       TINYINT(1)   NULL,
  printed_page_offset  SMALLINT     NULL,        -- printed = pdf + offset
  pagination_note      VARCHAR(500) NULL,
  cover_path           VARCHAR(1000) NULL,
  cover_source         ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  ingest_status        ENUM('pending','probing','ocr','clean','mapped','failed')
                         NOT NULL DEFAULT 'pending',
  extract_status       ENUM('pending','running','partial','complete','failed')
                         NOT NULL DEFAULT 'pending',
  model_used           VARCHAR(80)  NULL,
  ingested_at          DATETIME     NULL,
  extracted_at         DATETIME     NULL,
  created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_books_slug   (slug),
  UNIQUE KEY uq_books_sha    (source_sha256),
  KEY idx_books_scope        (scope, scope_confirmed),
  KEY idx_books_ingest       (ingest_status),
  KEY idx_books_extract      (extract_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS authors (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug  VARCHAR(160) NOT NULL,
  name  VARCHAR(255) NOT NULL,
  bio   TEXT NULL,
  UNIQUE KEY uq_authors_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_authors (
  book_id   INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  ord       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (book_id, author_id),
  KEY idx_ba_author (author_id),
  CONSTRAINT fk_ba_book   FOREIGN KEY (book_id)   REFERENCES books(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ba_author FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(160) NOT NULL,
  name      VARCHAR(255) NOT NULL,
  parent_id INT UNSIGNED NULL,
  UNIQUE KEY uq_cat_slug (slug),
  KEY idx_cat_parent (parent_id),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_categories (
  book_id     INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (book_id, category_id),
  KEY idx_bc_cat (category_id),
  CONSTRAINT fk_bc_book FOREIGN KEY (book_id)     REFERENCES books(id)      ON DELETE CASCADE,
  CONSTRAINT fk_bc_cat  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS terms (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(160) NOT NULL,
  name VARCHAR(255) NOT NULL,
  kind ENUM('theme','topic') NOT NULL DEFAULT 'topic',
  UNIQUE KEY uq_terms_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_terms (
  book_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (book_id, term_id),
  KEY idx_bt_term (term_id),
  CONSTRAINT fk_bt_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT fk_bt_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The atom of the system: per-page text. Makes citation, re-chunking, and
-- printed-page mapping possible without re-ingesting.
CREATE TABLE IF NOT EXISTS pages (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id        INT UNSIGNED NOT NULL,
  pdf_page       SMALLINT UNSIGNED NOT NULL,
  printed_page   SMALLINT NULL,
  text           MEDIUMTEXT NULL,
  char_count     MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  ocr            TINYINT(1) NOT NULL DEFAULT 0,
  ocr_confidence DECIMAL(4,3) NULL,
  UNIQUE KEY uq_pages_book_page (book_id, pdf_page),
  CONSTRAINT fk_pages_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  FULLTEXT KEY ft_pages_text (text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chapters (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id            INT UNSIGNED NOT NULL,
  ord                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  number_label       VARCHAR(40)  NULL,
  title              VARCHAR(500) NOT NULL,
  pdf_page_start     SMALLINT UNSIGNED NULL,
  pdf_page_end       SMALLINT UNSIGNED NULL,
  printed_page_start SMALLINT NULL,
  printed_page_end   SMALLINT NULL,
  core_idea          TEXT NULL,
  summary            TEXT NULL,
  word_count         MEDIUMINT UNSIGNED NULL,
  status             ENUM('proposed','confirmed','extracted') NOT NULL DEFAULT 'proposed',
  KEY idx_ch_book (book_id, ord),
  KEY idx_ch_status (status),
  CONSTRAINT fk_ch_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS concepts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id    INT UNSIGNED NOT NULL,
  chapter_id INT UNSIGNED NULL,
  ord        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  kind       ENUM('key_concept','big_idea','caveat','thesis') NOT NULL,
  title      VARCHAR(500) NOT NULL,
  body       TEXT NULL,
  page_ref   VARCHAR(60) NULL,
  chapters_ref VARCHAR(120) NULL,      -- pass 3 "chapters: 2, 5, 7"
  KEY idx_con_book (book_id, kind),
  KEY idx_con_chapter (chapter_id),
  CONSTRAINT fk_con_book    FOREIGN KEY (book_id)    REFERENCES books(id)    ON DELETE CASCADE,
  CONSTRAINT fk_con_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE SET NULL,
  FULLTEXT KEY ft_concepts (title, body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Artifacts (§6.2) — checklists and friends, first-class and queryable ─────

CREATE TABLE IF NOT EXISTS artifacts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id       INT UNSIGNED NOT NULL,
  chapter_id    INT UNSIGNED NULL,
  ord           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  type          ENUM('checklist','question_set','script','framework','worksheet',
                     'comparison','stat','procedure','worked_example','table','quote') NOT NULL,
  title         VARCHAR(500) NOT NULL,
  intro         TEXT NULL,
  verbatim      TINYINT(1) NOT NULL DEFAULT 0,
  page_start    SMALLINT UNSIGNED NULL,
  page_end      SMALLINT UNSIGNED NULL,
  confidence    DECIMAL(3,2) NULL,
  review_status ENUM('unreviewed','approved','flagged','rejected')
                  NOT NULL DEFAULT 'unreviewed',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_art_book (book_id, type),
  KEY idx_art_type (type),
  KEY idx_art_review (review_status),
  CONSTRAINT fk_art_book    FOREIGN KEY (book_id)    REFERENCES books(id)    ON DELETE CASCADE,
  CONSTRAINT fk_art_chapter FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE SET NULL,
  FULLTEXT KEY ft_artifacts (title, intro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artifact_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artifact_id  INT UNSIGNED NOT NULL,
  ord          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  group_label  VARCHAR(255) NULL,
  text         TEXT NOT NULL,
  note         TEXT NULL,
  indent_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_ai_artifact (artifact_id, ord),
  CONSTRAINT fk_ai_artifact FOREIGN KEY (artifact_id) REFERENCES artifacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Own content (§6.3) ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS kits (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number      TINYINT UNSIGNED NOT NULL,
  slug        VARCHAR(160) NOT NULL,
  title       VARCHAR(255) NOT NULL,
  description TEXT NULL,
  UNIQUE KEY uq_kits_number (number),
  UNIQUE KEY uq_kits_slug   (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number        SMALLINT UNSIGNED NULL,
  slug          VARCHAR(200) NOT NULL,
  format        ENUM('before_noon','gut_check','steal_this','the_upgrade',
                     'the_protocol','one_number') NOT NULL,
  title         VARCHAR(500) NOT NULL,
  subtitle      VARCHAR(500) NULL,
  body          MEDIUMTEXT NULL,
  status        ENUM('draft','queued','scheduled','published','retired')
                  NOT NULL DEFAULT 'draft',
  kit_id        INT UNSIGNED NULL,
  angle         TEXT NULL,               -- mandatory for book-sourced (§11.2)
  source_refs   JSON NULL,
  voice_preset  VARCHAR(60) NULL,
  model_used    VARCHAR(80) NULL,
  cost_usd      DECIMAL(10,4) NOT NULL DEFAULT 0,
  substack_url  VARCHAR(500) NULL,       -- operator-pasted; no API (§11.4)
  scheduled_for DATETIME NULL,           -- operator intent, not a platform commitment
  published_at  DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_status (status),
  KEY idx_posts_format (format),
  KEY idx_posts_kit (kit_id),
  CONSTRAINT fk_posts_kit FOREIGN KEY (kit_id) REFERENCES kits(id) ON DELETE SET NULL,
  FULLTEXT KEY ft_posts (title, body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marketing Brain / Dan Kennedy Brain index import — reference only
CREATE TABLE IF NOT EXISTS sources (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brain        ENUM('mb','dk') NOT NULL,
  path         VARCHAR(1000) NOT NULL,
  path_hash    CHAR(40) NOT NULL,          -- sha1(path); MySQL can't index 1000 chars
  author       VARCHAR(255) NULL,
  collection   VARCHAR(255) NULL,
  content_type VARCHAR(80)  NULL,
  topics       JSON NULL,
  summary      TEXT NULL,
  UNIQUE KEY uq_src (brain, path_hash),
  KEY idx_src_author (author),
  FULLTEXT KEY ft_sources (summary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Assets and candidates (§6.4) ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS candidates (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_type      ENUM('book','chapter','concept','artifact','post') NOT NULL,
  subject_id        INT UNSIGNED NOT NULL,
  round             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  label             VARCHAR(255) NULL,
  rationale         TEXT NULL,
  visual_type       VARCHAR(80) NULL,
  fidelity          ENUM('exact','editorial') NOT NULL DEFAULT 'editorial',
  copy_json         JSON NULL,
  mockup_html       MEDIUMTEXT NULL,
  mockup_png_path   VARCHAR(1000) NULL,
  gemini_prompt     MEDIUMTEXT NULL,
  recommended_route ENUM('render','generate') NULL,
  chosen            TINYINT(1) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cand_subject (subject_type, subject_id, round)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_type    ENUM('book','chapter','concept','artifact','post') NOT NULL,
  subject_id      INT UNSIGNED NOT NULL,
  candidate_id    INT UNSIGNED NULL,
  kind            ENUM('infographic','card','diagram','cover','tool_pdf','slide') NOT NULL,
  route           ENUM('template','claude_layout','generate','hybrid') NOT NULL,
  template_key    VARCHAR(80) NULL,
  prompt_snapshot MEDIUMTEXT NULL,
  spec_json       JSON NULL,
  html_path       VARCHAR(1000) NULL,
  file_path       VARCHAR(1000) NULL,     -- full-res, local
  web_path        VARCHAR(1000) NULL,     -- served copy; PNG or JPEG only, never WebP
  thumb_path      VARCHAR(1000) NULL,
  width           SMALLINT UNSIGNED NULL,
  height          SMALLINT UNSIGNED NULL,
  format          ENUM('png','jpg') NOT NULL DEFAULT 'png',
  status          ENUM('pending','rendering','ready','rejected','superseded')
                    NOT NULL DEFAULT 'pending',
  version         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  supersedes_id   INT UNSIGNED NULL,
  model           VARCHAR(80) NULL,
  cost_usd        DECIMAL(10,4) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_asset_subject (subject_type, subject_id),
  KEY idx_asset_status (status),
  KEY idx_asset_supersedes (supersedes_id),
  CONSTRAINT fk_asset_candidate FOREIGN KEY (candidate_id)
    REFERENCES candidates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Operations (§6.5) ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS jobs (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type              VARCHAR(60) NOT NULL,
  subject_type      VARCHAR(40) NULL,
  subject_id        INT UNSIGNED NULL,
  payload           JSON NULL,
  priority          TINYINT NOT NULL DEFAULT 5,
  status            ENUM('queued','leased','running','done','failed','dead')
                      NOT NULL DEFAULT 'queued',
  attempts          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts      TINYINT UNSIGNED NOT NULL DEFAULT 3,
  lease_token       CHAR(32) NULL,
  leased_at         DATETIME NULL,
  lease_expires_at  DATETIME NULL,
  error             TEXT NULL,
  cost_usd          DECIMAL(10,4) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at        DATETIME NULL,
  finished_at       DATETIME NULL,
  KEY idx_jobs_claim (status, priority, id),
  KEY idx_jobs_lease (status, lease_expires_at),
  KEY idx_jobs_subject (subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_calls (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id        INT UNSIGNED NULL,
  vendor        ENUM('google') NOT NULL DEFAULT 'google',  -- no Anthropic API (§8)
  model         VARCHAR(80) NOT NULL,
  input_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  images        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cost_usd      DECIMAL(10,4) NOT NULL DEFAULT 0,
  latency_ms    INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_api_job (job_id),
  KEY idx_api_created (created_at),
  CONSTRAINT fk_api_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Render + composer prompts. Extraction prompts live on disk in
-- ../extractions/prompts/ where Claude Code can read them (§8.1).
CREATE TABLE IF NOT EXISTS prompts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`         VARCHAR(80) NOT NULL,
  version       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  purpose       VARCHAR(255) NULL,
  body          MEDIUMTEXT NOT NULL,
  model_default VARCHAR(80) NULL,
  params        JSON NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prompts_key_ver (`key`, version),
  KEY idx_prompts_active (`key`, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS triage (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_type ENUM('book','chapter','concept','artifact','candidate','asset','post') NOT NULL,
  subject_id   INT UNSIGNED NOT NULL,
  mark         ENUM('favorite','read','write_about','meh') NOT NULL,
  note         TEXT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_triage (subject_type, subject_id, mark),
  KEY idx_triage_mark (mark)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ledger of imported extraction files; enforces idempotency on (book, pass).
CREATE TABLE IF NOT EXISTS extraction_imports (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id     INT UNSIGNED NULL,
  book_slug   VARCHAR(160) NOT NULL,
  `pass`      ENUM('profile','chapters','ideas','artifacts') NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  body_sha256 CHAR(64) NOT NULL,
  rows_imported INT UNSIGNED NOT NULL DEFAULT 0,
  rows_replaced INT UNSIGNED NOT NULL DEFAULT 0,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_extract (book_slug, `pass`),
  CONSTRAINT fk_extract_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`      VARCHAR(80) PRIMARY KEY,
  value      TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
