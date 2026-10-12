-- Daily Idea: one random Hypnosis Brain item emailed each morning.
-- The pool is pushed from the Mac by "Hypnosis Brain/export_daily.py --push";
-- see "Daily Idea" in CLAUDE.md.

CREATE TABLE IF NOT EXISTS `af_daily_ideas` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_key`   CHAR(40)     NOT NULL,
  `topic`      VARCHAR(120) NOT NULL,
  `section`    VARCHAR(60)  NOT NULL,
  `title`      VARCHAR(500) NOT NULL,
  `body`       MEDIUMTEXT   NOT NULL,
  `citation`   VARCHAR(255) NOT NULL DEFAULT '',
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `af_daily_ideas_key` (`item_key`),
  KEY `af_daily_ideas_topic` (`topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `af_daily_sends` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idea_id`    INT UNSIGNED NOT NULL,
  `send_date`  DATE         NOT NULL,
  `to_email`   VARCHAR(255) NOT NULL,
  `subject`    VARCHAR(500) NOT NULL,
  `distilled`  TEXT         NOT NULL,
  `model`      VARCHAR(60)  NOT NULL DEFAULT '',
  `sent`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `af_daily_sends_date` (`send_date`),
  KEY `af_daily_sends_idea` (`idea_id`),
  CONSTRAINT `af_daily_sends_idea_fk` FOREIGN KEY (`idea_id`) REFERENCES `af_daily_ideas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
