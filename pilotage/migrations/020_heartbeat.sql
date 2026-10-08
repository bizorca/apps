-- Pilotage — 020 the heartbeat
--
-- Background work is triggered by page loads rather than by cron.
--
-- ===========================================================================
-- WHY, AND WHAT IT COSTS
--
-- SiteGround's cron is a Site Tools affair rather than a crontab, and is not
-- wanted here. So a request that arrives when work is due fires a detached
-- self-request, and that second request does the work with its own execution
-- budget. The page load pays a couple of hundred milliseconds, once per
-- interval, not per request.
--
-- The honest cost: A SITE WITH NO TRAFFIC NEVER TICKS. Nothing about this
-- design can fix that — if nobody visits, nothing runs. Two things make it
-- survivable:
--
--   1. /_tick/{token} is reachable from outside, so any uptime pinger keeps a
--      quiet site alive. The token lives in this table.
--   2. Everything the tick does is idempotent and window-based, so a tick that
--      arrives late does the right thing once rather than the wrong thing
--      repeatedly. A digest window is claimed by (user, kind, window_start).
--      queueOnce will not re-notify; the retention sweep needs a notice period
--      to have elapsed. Irregular ticking was already tolerated by design.
--
-- The failure this table exists to prevent is the one that just happened: the
-- background layer was dormant in production for a day and nothing said so.
-- last_finished_at is read by /_health and the firm settings screen, so
-- "nothing has run since Tuesday" is visible rather than inferred.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `pl_tick_state` (
    -- Deliberately NOT tenant-scoped. The tick loops every tenant, so its
    -- schedule is a property of the installation. There is no tenant_id here
    -- and the isolation census will not ask for one.
    `mode` VARCHAR(32) NOT NULL PRIMARY KEY,

    -- Claimed when a runner starts, so concurrent page loads cannot all fire.
    -- The claim is the UPDATE itself: whoever changes the row wins.
    `last_started_at`  DATETIME NULL,
    `last_finished_at` DATETIME NULL,

    `last_note`     VARCHAR(500) NULL,
    `last_duration` SMALLINT UNSIGNED NULL,   -- seconds
    `run_count`     INT UNSIGNED NOT NULL DEFAULT 0,

    -- Consecutive claims that never reported finishing. A handful means the
    -- self-request is not arriving, which is exactly the silent failure this
    -- whole table exists to make loud.
    `stalled_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- The token that lets /_tick be called from outside.
--
-- One row, generated on first use. In the database rather than the environment
-- so it can be shown on the settings screen — the point of it is to be pasted
-- into an uptime monitor, and a value nobody can find is a value nobody uses.
--
-- It authorises running the tick and nothing else. Worst case for a leak is
-- somebody causing the queue to drain slightly sooner than it would have.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_tick_token` (
    `id`         TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    `token`      CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `pl_chk_single_row` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT IGNORE INTO `pl_tick_state` (`mode`) VALUES ('five-minute'), ('nightly');
