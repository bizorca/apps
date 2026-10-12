-- Daily Idea becomes a roster: one row per send date, written ahead of time in
-- Claude Code (subscription) and queued from the Mac, then sent by cron.
--   reserved  the idea is picked for the date, nothing written yet
--   queued    the explainer and art direction are in; the plate is drawn by cron
--   sent      emailed
--   failed    gave up for the day (SMTP2GO refused three times)

ALTER TABLE `af_daily_sends`
  ADD COLUMN `status` ENUM('reserved','queued','sent','failed') NOT NULL DEFAULT 'reserved' AFTER `send_date`,
  ADD COLUMN `direction` MEDIUMTEXT NULL AFTER `distilled`,
  ADD COLUMN `image_path` VARCHAR(500) NULL AFTER `direction`,
  ADD COLUMN `image_prompt` MEDIUMTEXT NULL AFTER `image_path`,
  ADD COLUMN `image_error` VARCHAR(500) NULL AFTER `image_prompt`,
  ADD COLUMN `image_tries` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `image_error`,
  ADD COLUMN `send_tries` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `image_tries`,
  ADD COLUMN `sent_at` DATETIME NULL AFTER `sent`,
  MODIFY `distilled` TEXT NULL,
  MODIFY `subject` VARCHAR(500) NOT NULL DEFAULT '',
  MODIFY `to_email` VARCHAR(255) NOT NULL DEFAULT '';

UPDATE `af_daily_sends` SET `status` = IF(`sent` = 1, 'sent', 'failed'), `sent_at` = IF(`sent` = 1, `created_at`, NULL);

ALTER TABLE `af_daily_sends` ADD UNIQUE KEY `af_daily_sends_one_per_day` (`send_date`);
