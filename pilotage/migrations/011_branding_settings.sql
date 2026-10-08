-- Pilotage — 011 tenant branding and settings
--
-- M1 (FR-1.2 to FR-1.5). Branding is what makes the client-side portal feel
-- like the coach's practice rather than like ours, which is the whole reason
-- subdomain tenancy was chosen over path prefixes.

ALTER TABLE `pl_tenants`
    ADD COLUMN `logo_url`      VARCHAR(512) NULL AFTER `name`,
    ADD COLUMN `primary_color` CHAR(7)      NULL AFTER `logo_url`,   -- #rrggbb
    ADD COLUMN `accent_color`  CHAR(7)      NULL AFTER `primary_color`,
    ADD COLUMN `mail_from_name` VARCHAR(120) NULL AFTER `accent_color`,
    ADD COLUMN `support_email`  VARCHAR(255) NULL AFTER `mail_from_name`,
    ADD COLUMN `hide_platform_credit` TINYINT(1) NOT NULL DEFAULT 0 AFTER `support_email`,
    ADD COLUMN `onboarded_at`  DATETIME NULL AFTER `hide_platform_credit`,
    ADD COLUMN `seat_limit`    SMALLINT UNSIGNED NULL AFTER `onboarded_at`;
