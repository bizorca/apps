-- Pilotage — 018 two-way calendar sync (Phase 3)
--
-- Google Calendar and Microsoft Graph, over their REST APIs. No SDK: zero
-- Composer dependencies is what keeps deploy at "rsync the files", and OAuth
-- plus four endpoints is a handful of curl calls.
--
-- ===========================================================================
-- THE FOUR DECISIONS THIS SCHEMA ENCODES
--
-- 1. CONNECTIONS ARE PER USER, NOT PER FIRM.
--    A calendar belongs to a person. A firm-wide connection would mean one
--    coach's OAuth grant writing into another's calendar, and a coach leaving
--    the firm taking everyone's sync with them. Each coach connects their own.
--    Client-side users do not connect at all — they get the .ics invite, which
--    is what they already expect from every other professional they deal with.
--
-- 2. LAST WRITER WINS, AND THE LOSER IS TOLD.
--    A session moved in Pilotage and in Google between two syncs is a genuine
--    conflict with no correct answer. Whichever changed most recently wins, and
--    the overwrite is recorded on the timeline — because the failure mode that
--    actually hurts is not "the wrong one won", it is "it changed and nobody
--    knows why". `remote_updated_at` and `pushed_at` are what make that
--    comparison possible.
--
-- 3. A DELETE OVER THERE IS NOT A DELETE OVER HERE.
--    Removing an event from your calendar usually means "off my view", not
--    "cancel this engagement's session". So a remote delete UNLINKS: the link
--    row goes, the session stays, and the coach is told. The reverse holds too
--    — cancelling a session here removes the event there, because that one IS
--    unambiguous.
--
-- 4. ECHOES ARE SUPPRESSED.
--    Pulling a change and then pushing it straight back is an infinite loop
--    with an API quota attached. `sync_origin` records who made the last
--    change, and the push step skips anything it just pulled.
-- ===========================================================================


-- ---------------------------------------------------------------------------
-- One connected calendar account.
--
-- The refresh token is ENCRYPTED, not hashed: it has to be presented to the
-- provider verbatim. See Core/Secrets. A leak of this table without the
-- environment key is useless; with it, it is someone's entire calendar, which
-- is why the two live in different places.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_calendar_connections` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id`   INT UNSIGNED NOT NULL,

    `provider` ENUM('google','microsoft') NOT NULL,

    -- Who they connected as, shown back to them. People have several accounts
    -- and forget which one they authorised.
    `account_email` VARCHAR(255) NULL,
    `calendar_id`   VARCHAR(255) NULL,   -- which calendar of theirs; null = primary

    `access_token`        TEXT     NULL,  -- encrypted; short-lived
    `refresh_token`       TEXT     NULL,  -- encrypted; the one that matters
    `access_expires_at`   DATETIME NULL,
    `scope`               VARCHAR(500) NULL,

    -- Incremental sync. Google calls it a syncToken, Microsoft a deltaLink.
    -- both mean "everything that changed since this point". Storing it turns
    -- a full calendar read every five minutes into a near-empty response.
    `sync_token` VARCHAR(1000) NULL,

    `status` ENUM('active','needs_reauth','revoked') NOT NULL DEFAULT 'active',
    `last_sync_at`    DATETIME NULL,
    `last_error`      VARCHAR(500) NULL,
    `last_error_at`   DATETIME NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One connection per provider per person. Connecting again re-authorises
    -- the existing row rather than accumulating stale grants.
    UNIQUE KEY `uq_user_provider` (`tenant_id`, `user_id`, `provider`),
    KEY `idx_due` (`status`, `last_sync_at`),

    CONSTRAINT `pl_fk_calconn_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_calconn_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- The link between one session and one remote event.
--
-- Per connection, not per session: two coaches on the same session each get
-- the event in their own calendar, and each link tracks its own state. The
-- alternative — one remote id on pl_sessions — silently means only one person
-- can sync.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_calendar_events` (
    `id`        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,

    `connection_id` INT UNSIGNED NOT NULL,
    `session_id`    INT UNSIGNED NOT NULL,

    `remote_event_id` VARCHAR(255) NOT NULL,
    `remote_etag`     VARCHAR(255) NULL,

    -- The two timestamps that decide a conflict. `pushed_at` is when we last
    -- wrote to the provider; `remote_updated_at` is what the provider says
    -- about its own copy. Comparing them is the whole of rule 2 above.
    `pushed_at`         DATETIME NULL,
    `remote_updated_at` DATETIME NULL,

    -- Who made the last change, so an echo can be recognised and skipped.
    `sync_origin` ENUM('local','remote') NOT NULL DEFAULT 'local',

    -- A hash of what we last pushed. Cheaper and more reliable than comparing
    -- five fields, and it means an unchanged session costs no API call at all.
    `pushed_digest` CHAR(64) NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_connection_session` (`connection_id`, `session_id`),
    UNIQUE KEY `uq_connection_remote`  (`connection_id`, `remote_event_id`),
    KEY `idx_session` (`tenant_id`, `session_id`),

    CONSTRAINT `pl_fk_calevent_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_calevent_connection`
        FOREIGN KEY (`connection_id`) REFERENCES `pl_calendar_connections`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_calevent_session`
        FOREIGN KEY (`session_id`) REFERENCES `pl_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- OAuth state, for the round trip.
--
-- A short-lived nonce tying the redirect back to the person who started it.
-- Without it the callback is a CSRF hole: an attacker sends a victim a crafted
-- callback URL and connects THEIR calendar to the victim's account, or the
-- victim's to theirs.
--
-- Deliberately its own table rather than the PHP session, because the callback
-- arrives on the tenant host from an external redirect and session cookies are
-- scoped tightly here (see the tenancy notes).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pl_oauth_states` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id`   INT UNSIGNED NOT NULL,

    `provider`  ENUM('google','microsoft') NOT NULL,
    `state`     CHAR(64) NOT NULL,
    `verifier`  VARCHAR(128) NULL,     -- PKCE, where the provider supports it

    `redirect_to` VARCHAR(255) NULL,   -- where to land afterwards
    `expires_at`  DATETIME NOT NULL,
    `used_at`     DATETIME NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_state` (`state`),
    KEY `idx_expiry` (`expires_at`),

    CONSTRAINT `pl_fk_oauthstate_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_oauthstate_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- No ALTER on pl_sessions: duration_minutes and timezone are already there
-- from 005, which is what the .ics exporter has been reading. A calendar event
-- needs both and both exist.


-- ---------------------------------------------------------------------------
-- Unlinking has to be remembered, or rule 2 is a lie.
--
-- Deleting the link row on a remote delete leaves the session looking like one
-- that has never been synced — so the next push, five minutes later, creates
-- the event again. A coach who removes a meeting from their calendar and
-- watches it reappear twice will turn the whole feature off, and they would be
-- right to.
--
-- So the row STAYS, marked. Push skips an unlinked link whose session has not
-- changed since. If the session genuinely moves, the digest changes and the
-- event comes back — which is the behaviour the screen promises.
-- ---------------------------------------------------------------------------
ALTER TABLE `pl_calendar_events`
    ADD COLUMN `unlinked_at` DATETIME NULL
        COMMENT 'removed at the provider; do not recreate until the session changes'
        AFTER `sync_origin`;
