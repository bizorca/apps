-- ---------------------------------------------------------------------------
-- 022 — Emailed links survive scanners, and passwords can be recovered.
--
-- TWO PROBLEMS, ONE CAUSE: a link in an email is not fetched only by the
-- person it was sent to.
--
-- Corporate mail security (Outlook Safe Links, Mimecast, Proofpoint) fetches
-- URLs in incoming mail to check them. A single-use sign-in link consumed by a
-- scanner is a login that fails for the recipient with no explanation — and
-- our client-side users are businesses, which is exactly the population
-- running that filtering. This project already knows the pattern: the
-- unsubscribe route is GET-shows-confirmation, POST-applies, for precisely
-- this reason. Magic links now follow it too.
--
-- `delivery` records how a link reached its owner, because the answer changes
-- what is safe:
--
--   'email'   — went through a mailbox. GET renders a confirmation, POST
--               consumes. Default, because it is the safe answer.
--   'handoff' — never entered a mailbox. Currently only self-serve signup,
--               which redirects a browser straight onto the new firm's host.
--               No scanner can see it, so the confirmation step would be pure
--               friction. Consumed on GET.
--
-- Password reset gets its own table rather than another flavour of magic link.
-- A reset token deliberately is NOT a sign-in credential: it lets someone set
-- a password and then makes them use it. That keeps two-factor in the path —
-- a reset that signed you in would be a 2FA bypass wearing a helpful hat.
-- ---------------------------------------------------------------------------

ALTER TABLE `pl_magic_links`
    ADD COLUMN `delivery` ENUM('email','handoff') NOT NULL DEFAULT 'email'
        COMMENT 'email links need POST confirmation; handoff links never touched a mailbox'
        AFTER `redirect_to`;


CREATE TABLE IF NOT EXISTS `pl_password_resets` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `selector`      CHAR(32)     NOT NULL,
    `verifier_hash` CHAR(64)     NOT NULL,   -- sha256 of the verifier half
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`    DATETIME     NOT NULL,
    `consumed_at`   DATETIME     NULL,       -- single use
    `requested_ip`  VARBINARY(16) NULL,
    UNIQUE KEY `uq_reset_selector` (`selector`),
    KEY `idx_reset_tenant_user` (`tenant_id`, `user_id`),
    KEY `idx_reset_expires`     (`expires_at`),
    CONSTRAINT `pl_fk_reset_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `pl_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `pl_fk_reset_user`
        FOREIGN KEY (`user_id`) REFERENCES `pl_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
