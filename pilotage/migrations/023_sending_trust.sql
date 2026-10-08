-- ---------------------------------------------------------------------------
-- 023 — A new firm cannot mail strangers until it has done something real.
--
-- THE ASSET BEING PROTECTED IS THE SENDING DOMAIN.
--
-- Every firm sends from pilotagehq.com. That is forced by the three-domain
-- design (SPEC §7) and it is the right design — a promotional-looking sender
-- gets magic-link mail filtered. The cost is that deliverability is SHARED:
-- one firm blasting invitations damages every other firm's ability to have a
-- sign-in link arrive, on a domain that is young and still sitting at DMARC
-- p=none. A reputation takes months to rebuild and cannot be bought back.
--
-- Self-serve signup (FR-1.6) opened the front door, so the throttle has to be
-- structural rather than a matter of who happens to sign up.
--
-- `vouched_at` is the manual override: a platform admin saying "this one is
-- fine" promotes a firm immediately, without waiting for it to trip the
-- automatic bar. Nullable, and null is the normal state — the automatic path
-- is expected to cover almost everyone.
--
-- Deliberately NOT a status enum. Trust here is derived (has this firm got a
-- client? how old is it? how much has it already sent?) rather than stored,
-- so there is no second copy of the answer to drift out of date. The one thing
-- that genuinely needs storing is the human override.
-- ---------------------------------------------------------------------------

ALTER TABLE `pl_tenants`
    ADD COLUMN `vouched_at` DATETIME NULL
        COMMENT 'platform admin vouched for this firm; lifts sending limits early'
        AFTER `retention_confirmed_at`;

-- Counting what a firm has already sent is the hot path on every invitation,
-- and it always asks the same question: how many rows for this tenant since a
-- given moment.
CREATE INDEX `idx_invitations_tenant_created`
    ON `pl_invitations` (`tenant_id`, `created_at`);
