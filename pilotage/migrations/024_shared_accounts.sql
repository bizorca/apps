-- Pilotage on tools.bizorca.com: every person signs in with the shared tools
-- account (`users`), and a row in pl_users is their MEMBERSHIP of one firm.
--
-- pl_users keeps everything it always held (tenant, role, client org, status,
-- 2FA, display name), because every permission resolver, scoped query and
-- audit row in this codebase is keyed on pl_users.id. What changes is how a
-- request proves it is that row: by being signed in to the shared account
-- that `account_id` points at. One person in three firms is three pl_users
-- rows sharing one account_id.
--
-- account_id is NULL for an invitation that has not been accepted yet (the
-- row exists with status 'invited' so it can be scoped and audited), and
-- after the shared account is deleted (ON DELETE SET NULL keeps the firm's
-- engagement history, the same reason erasure pseudonymises rather than
-- deletes). pl_users.password_hash is no longer read; passwords live on the
-- shared account.
--
-- users.id is a signed INT on the shared table, so account_id is signed too:
-- MySQL rejects a foreign key across a signedness mismatch.

ALTER TABLE `pl_users`
    ADD COLUMN `account_id` INT NULL DEFAULT NULL AFTER `tenant_id`,
    ADD UNIQUE KEY `uq_tenant_account` (`tenant_id`, `account_id`),
    ADD KEY `idx_account` (`account_id`),
    ADD CONSTRAINT `pl_fk_users_account`
        FOREIGN KEY (`account_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
