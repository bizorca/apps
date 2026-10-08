-- Anglerfish Press (tools.bizorca.com/anglerfish), af_ tables.
--
-- Generated 2026-10-08 from the live SiteGround schema (SHOW CREATE TABLE),
-- which is the sum of the original app's migrations 001-019. Those files are in
-- docs/original-migrations/ for history. Prefixed af_, constraint names too
-- (they are database-wide). Not carried over: users (Bizorca SSO, replaced by
-- the shared tools account; nothing referenced it) and schema_migrations (the
-- shared migrate.php tracks this file).
-- Ordered so every table exists before a foreign key points at it.

CREATE TABLE IF NOT EXISTS `af_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` int unsigned DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `priority` tinyint NOT NULL DEFAULT '5',
  `not_before` datetime DEFAULT NULL,
  `status` enum('queued','leased','running','done','failed','dead') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `max_attempts` tinyint unsigned NOT NULL DEFAULT '3',
  `lease_token` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leased_at` datetime DEFAULT NULL,
  `lease_expires_at` datetime DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jobs_claim` (`status`,`priority`,`id`),
  KEY `idx_jobs_lease` (`status`,`lease_expires_at`),
  KEY `idx_jobs_subject` (`subject_type`,`subject_id`),
  KEY `idx_jobs_due` (`status`,`not_before`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_api_calls` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `job_id` int unsigned DEFAULT NULL,
  `vendor` enum('google') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'google',
  `model` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `input_tokens` int unsigned NOT NULL DEFAULT '0',
  `output_tokens` int unsigned NOT NULL DEFAULT '0',
  `images` smallint unsigned NOT NULL DEFAULT '0',
  `cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `latency_ms` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_job` (`job_id`),
  KEY `idx_api_created` (`created_at`),
  CONSTRAINT `af_fk_api_job` FOREIGN KEY (`job_id`) REFERENCES `af_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_books` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` smallint DEFAULT NULL,
  `publisher` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_path` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` enum('press','reference') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reference',
  `scope_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `kind` enum('scan','big_ideas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scan',
  `brain` enum('mb','dk') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_page_count` smallint unsigned DEFAULT NULL,
  `has_text_layer` tinyint(1) DEFAULT NULL,
  `printed_page_offset` smallint DEFAULT NULL,
  `pagination_note` text COLLATE utf8mb4_unicode_ci,
  `context` text COLLATE utf8mb4_unicode_ci,
  `cover_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_source` enum('auto','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `ingest_status` enum('pending','probing','ocr','clean','mapped','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `extract_status` enum('pending','running','partial','complete','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `model_used` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ingested_at` datetime DEFAULT NULL,
  `extracted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_books_slug` (`slug`),
  UNIQUE KEY `uq_books_sha` (`source_sha256`),
  KEY `idx_books_scope` (`scope`,`scope_confirmed`),
  KEY `idx_books_ingest` (`ingest_status`),
  KEY `idx_books_extract` (`extract_status`),
  KEY `idx_books_kind` (`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_chapters` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `book_id` int unsigned NOT NULL,
  `ord` smallint unsigned NOT NULL DEFAULT '0',
  `number_label` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_page_start` smallint unsigned DEFAULT NULL,
  `pdf_page_end` smallint unsigned DEFAULT NULL,
  `printed_page_start` smallint DEFAULT NULL,
  `printed_page_end` smallint DEFAULT NULL,
  `core_idea` text COLLATE utf8mb4_unicode_ci,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `word_count` mediumint unsigned DEFAULT NULL,
  `status` enum('proposed','confirmed','extracted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `idx_ch_book` (`book_id`,`ord`),
  KEY `idx_ch_status` (`status`),
  CONSTRAINT `af_fk_ch_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_kits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `number` tinyint unsigned NOT NULL,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kits_number` (`number`),
  UNIQUE KEY `uq_kits_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_artifacts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `book_id` int unsigned DEFAULT NULL,
  `kit_id` int unsigned DEFAULT NULL,
  `chapter_id` int unsigned DEFAULT NULL,
  `ord` smallint unsigned NOT NULL DEFAULT '0',
  `type` enum('checklist','question_set','script','framework','worksheet','comparison','stat','procedure','worked_example','table','quote') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `intro` text COLLATE utf8mb4_unicode_ci,
  `verbatim` tinyint(1) NOT NULL DEFAULT '0',
  `page_start` smallint unsigned DEFAULT NULL,
  `page_end` smallint unsigned DEFAULT NULL,
  `confidence` decimal(3,2) DEFAULT NULL,
  `review_status` enum('unreviewed','approved','flagged','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unreviewed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_art_book` (`book_id`,`type`),
  KEY `idx_art_type` (`type`),
  KEY `idx_art_review` (`review_status`),
  KEY `fk_art_chapter` (`chapter_id`),
  KEY `idx_art_kit` (`kit_id`),
  FULLTEXT KEY `ft_artifacts` (`title`,`intro`),
  CONSTRAINT `af_fk_art_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `af_fk_art_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `af_chapters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `af_fk_art_kit` FOREIGN KEY (`kit_id`) REFERENCES `af_kits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_artifact_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `artifact_id` int unsigned NOT NULL,
  `ord` smallint unsigned NOT NULL DEFAULT '0',
  `group_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `indent_level` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_ai_artifact` (`artifact_id`,`ord`),
  CONSTRAINT `af_fk_ai_artifact` FOREIGN KEY (`artifact_id`) REFERENCES `af_artifacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_candidates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` enum('book','chapter','concept','artifact','post','kit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int unsigned NOT NULL,
  `round` smallint unsigned NOT NULL DEFAULT '1',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rationale` text COLLATE utf8mb4_unicode_ci,
  `visual_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fidelity` enum('exact','editorial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'editorial',
  `copy_json` json DEFAULT NULL,
  `mockup_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `mockup_png_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gemini_prompt` mediumtext COLLATE utf8mb4_unicode_ci,
  `recommended_route` enum('render','generate') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chosen` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cand_subject` (`subject_type`,`subject_id`,`round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_assets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` enum('book','chapter','concept','artifact','post','kit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int unsigned NOT NULL,
  `candidate_id` int unsigned DEFAULT NULL,
  `batch_id` int unsigned DEFAULT NULL,
  `batch_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kind` enum('infographic','card','diagram','cover','tool_pdf','slide') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` enum('template','claude_layout','generate','hybrid') COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_key` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prompt_snapshot` mediumtext COLLATE utf8mb4_unicode_ci,
  `spec_json` json DEFAULT NULL,
  `html_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `web_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumb_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` smallint unsigned DEFAULT NULL,
  `height` smallint unsigned DEFAULT NULL,
  `format` enum('png','jpg','pdf') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'png',
  `status` enum('pending','rendering','ready','rejected','superseded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_at` datetime DEFAULT NULL,
  `qa_verdict` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qa_notes` text COLLATE utf8mb4_unicode_ci,
  `qa_at` datetime DEFAULT NULL,
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `supersedes_id` int unsigned DEFAULT NULL,
  `model` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asset_subject` (`subject_type`,`subject_id`),
  KEY `idx_asset_status` (`status`),
  KEY `idx_asset_supersedes` (`supersedes_id`),
  KEY `fk_asset_candidate` (`candidate_id`),
  KEY `idx_asset_target` (`subject_type`,`subject_id`,`target`),
  KEY `idx_asset_batch` (`batch_id`,`batch_key`),
  KEY `idx_asset_review` (`kind`,`status`,`reviewed_at`),
  CONSTRAINT `af_fk_asset_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `af_candidates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_authors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_authors_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_book_authors` (
  `book_id` int unsigned NOT NULL,
  `author_id` int unsigned NOT NULL,
  `ord` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`book_id`,`author_id`),
  KEY `idx_ba_author` (`author_id`),
  CONSTRAINT `af_fk_ba_author` FOREIGN KEY (`author_id`) REFERENCES `af_authors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `af_fk_ba_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_slug` (`slug`),
  KEY `idx_cat_parent` (`parent_id`),
  CONSTRAINT `af_fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `af_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_book_categories` (
  `book_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  PRIMARY KEY (`book_id`,`category_id`),
  KEY `idx_bc_cat` (`category_id`),
  CONSTRAINT `af_fk_bc_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `af_fk_bc_cat` FOREIGN KEY (`category_id`) REFERENCES `af_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_book_summaries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `book_id` int unsigned NOT NULL,
  `kind` enum('audiobook_script','narrative') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'audiobook_script',
  `status` enum('queued','running','done','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `word_count` mediumint unsigned DEFAULT NULL,
  `est_seconds` mediumint unsigned DEFAULT NULL,
  `model` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_sha` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_summary` (`book_id`,`kind`),
  KEY `idx_summary_status` (`status`),
  CONSTRAINT `af_fk_summary_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_terms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('theme','topic') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'topic',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terms_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_book_terms` (
  `book_id` int unsigned NOT NULL,
  `term_id` int unsigned NOT NULL,
  PRIMARY KEY (`book_id`,`term_id`),
  KEY `idx_bt_term` (`term_id`),
  CONSTRAINT `af_fk_bt_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `af_fk_bt_term` FOREIGN KEY (`term_id`) REFERENCES `af_terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_modules` (
  `id` tinyint unsigned NOT NULL,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `premise` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `artifact` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cohort` enum('foundations','growth') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stalls` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modules_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_publications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pubkey` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `substack_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tagline` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voice_preset` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bizorca_press',
  `format_spec` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effort` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribution` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bizorca',
  `citation_required` tinyint(1) NOT NULL DEFAULT '0',
  `citation_min` tinyint unsigned NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pub_key` (`pubkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `publication_id` int unsigned NOT NULL DEFAULT '1',
  `number` smallint unsigned DEFAULT NULL,
  `slug` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` enum('before_noon','gut_check','steal_this','the_upgrade','the_protocol','one_number','weekly','essay') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `gemini_structure` enum('flow','metaphor','steps','contrast','anatomy') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribution` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bizorca',
  `gemini_direction` json DEFAULT NULL,
  `gemini_direction_at` datetime DEFAULT NULL,
  `status` enum('draft','queued','scheduled','published','retired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `kit_id` int unsigned DEFAULT NULL,
  `module_id` tinyint unsigned DEFAULT NULL,
  `sequence_number` smallint unsigned DEFAULT NULL,
  `deliverable` enum('test','checklist','worksheet','role_document') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deliverable_spec` json DEFAULT NULL,
  `angle` text COLLATE utf8mb4_unicode_ci,
  `source_refs` json DEFAULT NULL,
  `voice_preset` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_used` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `substack_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_for` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`),
  KEY `idx_posts_status` (`status`),
  KEY `idx_posts_format` (`format`),
  KEY `idx_posts_kit` (`kit_id`),
  KEY `idx_posts_module` (`module_id`,`sequence_number`),
  KEY `idx_posts_pub` (`publication_id`,`status`),
  FULLTEXT KEY `ft_posts` (`title`,`body`),
  CONSTRAINT `af_fk_posts_kit` FOREIGN KEY (`kit_id`) REFERENCES `af_kits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `af_fk_posts_module` FOREIGN KEY (`module_id`) REFERENCES `af_modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `af_fk_posts_pub` FOREIGN KEY (`publication_id`) REFERENCES `af_publications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_compositions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `publication_id` int unsigned NOT NULL DEFAULT '1',
  `subject_type` enum('concept','artifact','post','source','freeform') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int unsigned DEFAULT NULL,
  `format` enum('before_noon','gut_check','steal_this','the_upgrade','the_protocol','one_number','weekly','essay') COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` tinyint unsigned DEFAULT NULL,
  `deliverable` enum('test','checklist','worksheet','role_document') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deliverable_spec` json DEFAULT NULL,
  `angle` text COLLATE utf8mb4_unicode_ci,
  `audience` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'practitioners',
  `length` enum('short','medium','long') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `voice_preset` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bizorca_press',
  `extra` text COLLATE utf8mb4_unicode_ci,
  `model` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('queued','running','done','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `post_id` int unsigned DEFAULT NULL,
  `cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comp_status` (`status`),
  KEY `idx_comp_subject` (`subject_type`,`subject_id`),
  KEY `fk_comp_post` (`post_id`),
  KEY `idx_comp_pub` (`publication_id`,`status`),
  CONSTRAINT `af_fk_comp_post` FOREIGN KEY (`post_id`) REFERENCES `af_posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `af_fk_comp_pub` FOREIGN KEY (`publication_id`) REFERENCES `af_publications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_citations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int unsigned DEFAULT NULL,
  `composition_id` int unsigned DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authors` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `container` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` smallint DEFAULT NULL,
  `volume` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pages` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supports` enum('supports','contradicts','complicates','background') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'supports',
  `claim` text COLLATE utf8mb4_unicode_ci,
  `verified_at` datetime DEFAULT NULL,
  `resolves` tinyint(1) DEFAULT NULL,
  `crossref_json` json DEFAULT NULL,
  `peer_reviewed` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cit_post` (`post_id`),
  KEY `idx_cit_comp` (`composition_id`),
  KEY `idx_cit_doi` (`doi`),
  KEY `idx_cit_verified` (`verified_at`),
  CONSTRAINT `af_fk_cit_comp` FOREIGN KEY (`composition_id`) REFERENCES `af_compositions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `af_fk_cit_post` FOREIGN KEY (`post_id`) REFERENCES `af_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_clippings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `brain` enum('mb','dk') COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path_hash` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line_ref` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verbatim` tinyint(1) NOT NULL DEFAULT '1',
  `query` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_clip_brain` (`brain`),
  KEY `idx_clip_path` (`path_hash`),
  FULLTEXT KEY `ft_clippings` (`title`,`body`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_concepts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `book_id` int unsigned NOT NULL,
  `chapter_id` int unsigned DEFAULT NULL,
  `ord` smallint unsigned NOT NULL DEFAULT '0',
  `kind` enum('key_concept','big_idea','caveat','thesis') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `page_ref` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chapters_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_con_book` (`book_id`,`kind`),
  KEY `idx_con_chapter` (`chapter_id`),
  FULLTEXT KEY `ft_concepts` (`title`,`body`),
  CONSTRAINT `af_fk_con_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `af_fk_con_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `af_chapters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_concept_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `from_concept` int unsigned NOT NULL,
  `to_concept` int unsigned NOT NULL,
  `relation` enum('supports','contrasts','expands','related') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'related',
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin` enum('extraction','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'extraction',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_link` (`from_concept`,`to_concept`,`relation`),
  KEY `idx_link_from` (`from_concept`,`relation`),
  KEY `idx_link_to` (`to_concept`,`relation`),
  CONSTRAINT `af_fk_link_from` FOREIGN KEY (`from_concept`) REFERENCES `af_concepts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `af_fk_link_to` FOREIGN KEY (`to_concept`) REFERENCES `af_concepts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_corpus_searches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `query` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brain` enum('mb','dk','both') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `status` enum('queued','running','done','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `hits` int unsigned NOT NULL DEFAULT '0',
  `files` int unsigned NOT NULL DEFAULT '0',
  `results` json DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_query` (`query`,`brain`),
  KEY `idx_cs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_extraction_imports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `book_id` int unsigned DEFAULT NULL,
  `book_slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pass` enum('profile','chapters','ideas','artifacts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rows_imported` int unsigned NOT NULL DEFAULT '0',
  `rows_replaced` int unsigned NOT NULL DEFAULT '0',
  `imported_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_extract` (`book_slug`,`pass`),
  KEY `fk_extract_book` (`book_id`),
  CONSTRAINT `af_fk_extract_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_image_batches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `provider` enum('gemini') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gemini',
  `model` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` enum('book') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'book',
  `subject_id` int unsigned NOT NULL,
  `status` enum('directing','submitting','running','done','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'directing',
  `remote_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remote_state` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aspect_ratio` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '16:9',
  `image_size` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2K',
  `request_count` smallint unsigned NOT NULL DEFAULT '0',
  `applied_count` smallint unsigned NOT NULL DEFAULT '0',
  `failed_count` smallint unsigned NOT NULL DEFAULT '0',
  `polls` smallint unsigned NOT NULL DEFAULT '0',
  `cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_batch_status` (`status`),
  KEY `idx_batch_subject` (`subject_type`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_module_map` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` enum('concept','chapter','artifact','artifact_item','clipping','post') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int unsigned NOT NULL,
  `module_id` tinyint unsigned NOT NULL,
  `ord` tinyint unsigned NOT NULL DEFAULT '1',
  `confidence` decimal(3,2) DEFAULT NULL,
  `rationale` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` enum('auto','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_map` (`subject_type`,`subject_id`,`module_id`),
  KEY `idx_map_module` (`module_id`,`ord`),
  KEY `idx_map_subject` (`subject_type`,`subject_id`),
  CONSTRAINT `af_fk_map_module` FOREIGN KEY (`module_id`) REFERENCES `af_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_pages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `book_id` int unsigned NOT NULL,
  `pdf_page` smallint unsigned NOT NULL,
  `printed_page` smallint DEFAULT NULL,
  `text` mediumtext COLLATE utf8mb4_unicode_ci,
  `char_count` mediumint unsigned NOT NULL DEFAULT '0',
  `ocr` tinyint(1) NOT NULL DEFAULT '0',
  `ocr_confidence` decimal(4,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_book_page` (`book_id`,`pdf_page`),
  FULLTEXT KEY `ft_pages_text` (`text`),
  CONSTRAINT `af_fk_pages_book` FOREIGN KEY (`book_id`) REFERENCES `af_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_prompts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_default` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `params` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prompts_key_ver` (`key`,`version`),
  KEY `idx_prompts_active` (`key`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_publication_scope` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `publication_id` int unsigned NOT NULL,
  `subject_type` enum('book','concept','artifact') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int unsigned NOT NULL,
  `relevance` decimal(3,2) NOT NULL DEFAULT '0.50',
  `source` enum('auto','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scope` (`publication_id`,`subject_type`,`subject_id`),
  KEY `idx_scope_subject` (`subject_type`,`subject_id`),
  KEY `idx_scope_pick` (`publication_id`,`subject_type`,`relevance`),
  CONSTRAINT `af_fk_scope_pub` FOREIGN KEY (`publication_id`) REFERENCES `af_publications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_publication_sources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `publication_id` int unsigned NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('course','podcast','notes','transcripts','corpus') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'corpus',
  `local_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribution` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caution` text COLLATE utf8mb4_unicode_ci,
  `citable` tinyint(1) NOT NULL DEFAULT '0',
  `item_count` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pubsrc` (`publication_id`,`slug`),
  CONSTRAINT `af_fk_pubsrc_pub` FOREIGN KEY (`publication_id`) REFERENCES `af_publications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_settings` (
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_sources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `brain` enum('mb','dk') COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path_hash` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `topics` json DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_src` (`brain`,`path_hash`),
  KEY `idx_src_author` (`author`),
  FULLTEXT KEY `ft_sources` (`summary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_triage` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` enum('book','chapter','concept','artifact','candidate','asset','post','kit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int unsigned NOT NULL,
  `mark` enum('favorite','read','write_about','meh') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_triage` (`subject_type`,`subject_id`,`mark`),
  KEY `idx_triage_mark` (`mark`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_video_scripts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `number` smallint unsigned NOT NULL,
  `part_no` tinyint unsigned NOT NULL,
  `part_title` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('topic','scripted','approved','recorded','edited','published','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'topic',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `script` mediumtext COLLATE utf8mb4_unicode_ci,
  `on_screen` text COLLATE utf8mb4_unicode_ci,
  `sources` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `words` smallint unsigned DEFAULT NULL,
  `runtime_min` tinyint unsigned DEFAULT NULL,
  `model` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scripted_at` datetime DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `published_at` date DEFAULT NULL,
  `my_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_video_number` (`number`),
  KEY `ix_video_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
