-- 012 — The weekly post format and the twelve-module curriculum behind it.
--
-- Bizorca Press moved from a daily post in one of six rotating formats to one
-- weekly post in a single fixed shape: trap, diagnostic, protocol, Go!. Every
-- weekly post is slotted against one of twelve curriculum modules, publishes in
-- module order, and ships with one freely downloadable PDF deliverable.
--
-- The six formats are NOT retired here. 410 drafts carry those enum values and
-- the Posts screen, the art-direction pass and the compose flow all still read
-- them. `weekly` is a seventh value, additive, and nothing existing changes
-- meaning. Adding a value to the end of a MySQL ENUM does not renumber the ones
-- before it, so no stored row is touched.
--
-- Three things are genuinely new per weekly post and get columns rather than
-- being buried in a JSON blob:
--
--   module_id       which of the twelve. Drives the PDF footer tag, the publish
--                   order, and the compile into the workbook. A foreign key
--                   because a post pointing at module 13 is a bug, not data.
--   sequence_number the running number printed on the deliverable
--                   ("Foundations Worksheet 14"). Separate from posts.number,
--                   which counts every draft ever made including the 410 that
--                   predate this and will never be a weekly post.
--   deliverable     which of the four fixed containers. The brief's central
--                   discipline is that the container is chosen from four, never
--                   invented per post, so this is an ENUM and not a string.
--
-- deliverable_spec is the content of that container — the questions and their
-- scoring, the checklist steps, the worksheet prompts. JSON because its shape
-- differs per container and because the renderer is the only thing that reads
-- it. What got rendered is recorded per asset in assets.prompt_snapshot and
-- spec_json; this is the authored intent.

-- ── The twelve modules ───────────────────────────────────────────────────────
--
-- Seeded from bizorca-curriculum.md, which stays the prose authority. This
-- table exists so a post can be constrained to a real module and so the
-- composer can be handed the module's premise without the operator retyping it.
--
-- Ordering is by dependency, not domain: module 8 works because module 1
-- happened. `stalls` marks 3, 8 and 9, where the obstacle is behavioural rather
-- than informational and the protocol has to remove the decision rather than
-- explain the concept again.

CREATE TABLE IF NOT EXISTS modules (
  id        TINYINT UNSIGNED NOT NULL PRIMARY KEY,   -- 1-12, the module number
  slug      VARCHAR(60)  NOT NULL,
  title     VARCHAR(120) NOT NULL,
  premise   TEXT NOT NULL,          -- what the module is for, in one or two lines
  artifact  VARCHAR(255) NOT NULL,  -- what the client is left holding
  cohort    ENUM('foundations','growth') NOT NULL,
  stalls    TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_modules_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO modules (id, slug, title, premise, artifact, cohort, stalls) VALUES
 (1,  'money-baseline', 'Money Baseline',
  'You cannot fix what you cannot see, and most owners have never seen it. Establishes the true financial picture before any decisions get made against it.',
  'A one-page financial baseline: cash position, break-even, true owner pay.', 'foundations', 0),
 (2,  'owners-time-audit', 'The Owner''s Time Audit',
  'Gerber''s three roles applied to an actual week. Most owners discover they spend eighty percent of their time as technician and call it running a business.',
  'The time audit, with the three-way split and the first candidate for removal.', 'foundations', 0),
 (3,  'the-one-offer', 'The One Offer',
  'Most marketing problems are offer problems. Narrows the offer to something describable in a sentence and priced against real delivery cost.',
  'The written offer and the pricing rationale behind it.', 'foundations', 1),
 (4,  'lead-flow', 'Lead Flow',
  'One channel built to work, rather than five channels dabbled in. Chosen by a question: does the buyer know they have this problem before they meet you? No means workshop-led, yes means referral-led.',
  'One documented acquisition channel with a defined weekly action.', 'foundations', 0),
 (5,  'the-sales-conversation', 'The Sales Conversation',
  'The gap between an interested prospect and a paying client is usually a conversation nobody planned. Makes it repeatable.',
  'A documented conversation structure and a written follow-up sequence.', 'foundations', 0),
 (6,  'delivery-documented', 'Delivery Documented',
  'The franchise prototype question made concrete: if you handed this to someone else next month, what would they need?',
  'The task library — the delivery process written down.', 'growth', 0),
 (7,  'collections-cadence', 'Collections Cadence',
  'Revenue that never arrives is not revenue. Short on theory and long on the specific discomfort of asking for money.',
  'The collections calendar and a cleared backlog.', 'foundations', 0),
 (8,  'the-price-increase', 'The Price Increase',
  'Executed, not contemplated. Where the earlier work pays for itself, and where behavioural resistance shows up most reliably.',
  'The increase in effect, with the communication sent.', 'growth', 1),
 (9,  'first-delegation', 'First Delegation',
  'One role defined and handed off. Not a hiring plan — a single transfer that proves the task library works.',
  'One role defined, documented, and transferred.', 'growth', 1),
 (10, 'owners-dashboard', 'The Owner''s Dashboard',
  'Five numbers reviewed weekly. Tax-return accounting is historical; operating decisions are forward-looking.',
  'The dashboard, with a standing weekly review on the calendar.', 'foundations', 0),
 (11, 'tax-entity-hygiene', 'Tax and Entity Hygiene',
  'The regulatory layer addressed once a year rather than discovered in a letter. Where the EA credential does work no other coach in this price band can do. Stay on the EA side of the line: tax and entity structure, not investment advice.',
  'A structure review and a funded quarterly schedule.', 'growth', 0),
 (12, 'the-annual-plan', 'The Annual Plan',
  'One page. Everything installed across the year, turned into what happens next.',
  'The next twelve months on a single page.', 'growth', 0)
ON DUPLICATE KEY UPDATE
  slug=VALUES(slug), title=VALUES(title), premise=VALUES(premise),
  artifact=VALUES(artifact), cohort=VALUES(cohort), stalls=VALUES(stalls);

-- ── Weekly posts ─────────────────────────────────────────────────────────────

ALTER TABLE posts
  MODIFY format ENUM('before_noon','gut_check','steal_this','the_upgrade',
                     'the_protocol','one_number','weekly') NOT NULL,
  ADD COLUMN module_id       TINYINT UNSIGNED NULL AFTER kit_id,
  ADD COLUMN sequence_number SMALLINT UNSIGNED NULL AFTER module_id,
  ADD COLUMN deliverable     ENUM('test','checklist','worksheet','role_document')
      NULL AFTER sequence_number,
  ADD COLUMN deliverable_spec JSON NULL AFTER deliverable,
  ADD KEY idx_posts_module (module_id, sequence_number),
  ADD CONSTRAINT fk_posts_module FOREIGN KEY (module_id)
      REFERENCES modules(id) ON DELETE SET NULL;

ALTER TABLE compositions
  MODIFY format ENUM('before_noon','gut_check','steal_this','the_upgrade',
                     'the_protocol','one_number','weekly') NOT NULL,
  ADD COLUMN module_id   TINYINT UNSIGNED NULL AFTER format,
  ADD COLUMN deliverable ENUM('test','checklist','worksheet','role_document')
      NULL AFTER module_id;

-- ── Library to module mapping ────────────────────────────────────────────────
--
-- Polymorphic on purpose. The things worth mapping are concepts (including the
-- 580 Big Ideas), book chapters, extracted artifacts and clippings — four
-- tables that share no parent, exactly like `triage` and `assets` already do.
--
-- A row per (subject, module) rather than a column on each subject table,
-- because the relationship is many-to-many: a concept about raising prices to
-- clients you are afraid of losing belongs to module 8 and module 5 both, and
-- forcing a single winner would throw away the second one.
--
-- `source` separates a machine pass from a human decision so a re-run can
-- replace its own output without touching anything confirmed by hand.

-- `artifact_item` is in the list because the diagnostic movement of a weekly
-- post is made of questions, and the 790 questions in the client question bank
-- are artifact_items rather than artifacts. Mapping only the 26 parent question
-- sets would mean retrieving a hundred questions to use five.
CREATE TABLE IF NOT EXISTS module_map (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_type ENUM('concept','chapter','artifact','artifact_item','clipping','post') NOT NULL,
  subject_id   INT UNSIGNED NOT NULL,
  module_id    TINYINT UNSIGNED NOT NULL,
  -- `ord` rather than `rank`: RANK is a reserved word in MySQL 8+ (the window
  -- function) and the unquoted column is a syntax error. `ord` is also what
  -- artifacts, chapters and concepts already call their ordering column.
  ord          TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- 1 = best fit for this subject
  confidence   DECIMAL(3,2) NULL,
  rationale    VARCHAR(500) NULL,
  source       ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_map (subject_type, subject_id, module_id),
  KEY idx_map_module (module_id, ord),
  KEY idx_map_subject (subject_type, subject_id),
  CONSTRAINT fk_map_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
