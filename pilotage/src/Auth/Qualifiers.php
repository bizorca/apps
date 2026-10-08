<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

/**
 * Resolvers for the qualifiers in the permission matrix.
 *
 * Policy denies any qualifier with no resolver, so this file is the record of
 * how much of SPEC.md §8 is actually live. Each module registers its own as it
 * ships; `Policy::unresolvedQualifiers()` reports what is still owed.
 *
 * Registered so far:
 *   own_org  (M3) — the actor belongs to the client organization in question
 *   assigned (M3) — a firm-side user is attached to the record in question
 *
 * Still owed: attendee (M5), until_lock (M4B), delivered/shared (M7),
 * client_facing/progress_only (M4), owner (M9).
 */
final class Qualifiers
{
    public static function register(): void
    {
        /**
         * own_org — the actor is a client-side user and the object belongs to
         * their own client organization.
         *
         * Context must carry client_org_id. A missing one denies: an object
         * that cannot say which organization it belongs to is not an object a
         * client-side user may act on.
         */
        Policy::registerQualifier('own_org', static function (array $user, ?array $context): bool {
            $userOrg = $user['client_org_id'] ?? null;
            $objectOrg = $context['client_org_id'] ?? null;

            if ($userOrg === null || $objectOrg === null) {
                return false;
            }

            return (int) $userOrg === (int) $objectOrg;
        });

        /**
         * assigned — a firm-side user is attached to the record.
         *
         * Accepts either a single owner_user_id or an assigned_user_ids list,
         * so it works for records with one owner (an organization) and records
         * with a team (an engagement) without each caller inventing a shape.
         */
        Policy::registerQualifier('assigned', static function (array $user, ?array $context): bool {
            if ($context === null) {
                return false;
            }

            $userId = (int) ($user['id'] ?? 0);

            if ($userId === 0) {
                return false;
            }

            if (isset($context['owner_user_id']) && (int) $context['owner_user_id'] === $userId) {
                return true;
            }

            $assigned = $context['assigned_user_ids'] ?? null;

            if (is_array($assigned)) {
                foreach ($assigned as $candidate) {
                    if ((int) $candidate === $userId) {
                        return true;
                    }
                }
            }

            return false;
        });

        /**
         * own — reused across modules for "the record I own or authored".
         * Registered here because M3 is the first module that needs it; later
         * modules extend the accepted context keys rather than re-registering.
         */
        Policy::registerQualifier('own', static function (array $user, ?array $context): bool {
            if ($context === null) {
                return false;
            }

            $userId = (int) ($user['id'] ?? 0);

            if ($userId === 0) {
                return false;
            }

            foreach (['owner_user_id', 'user_id', 'author_user_id', 'assignee_user_id'] as $key) {
                if (isset($context[$key]) && (int) $context[$key] === $userId) {
                    return true;
                }
            }

            // A client-side user "owns" things belonging to their organization.
            $userOrg = $user['client_org_id'] ?? null;
            $objectOrg = $context['client_org_id'] ?? null;

            if ($userOrg !== null && $objectOrg !== null && (int) $userOrg === (int) $objectOrg) {
                return true;
            }

            return false;
        });

        /**
         * client_facing (M4) — a client-side user may read a playbook step, but
         * only its client-facing half.
         *
         * The qualifier grants access to the OBJECT; stripping coach_guidance
         * from the payload is done by PlaybookRunner::journey($clientSide), so
         * the two enforce the same wall from different directions. A locked
         * step is not client-facing at all: telling a client what is coming
         * three phases ahead invites questions the engagement is not ready for.
         */
        Policy::registerQualifier('client_facing', static function (array $user, ?array $context): bool {
            if ($context === null) {
                return false;
            }

            $status = (string) ($context['status'] ?? '');

            return in_array($status, ['available', 'in_progress', 'complete', 'skipped'], true);
        });

        /**
         * progress_only (M4) — a sponsor sees that the engagement is moving,
         * never what is in it.
         *
         * Grants only where the caller has explicitly marked the payload as a
         * progress summary. Anything that has not said so is denied, so a new
         * screen cannot accidentally expose content to a sponsor by reusing an
         * existing permission check.
         */
        Policy::registerQualifier('progress_only', static fn (array $user, ?array $context): bool
            => ($context['progress_summary'] ?? false) === true);

        /**
         * attendee (M5) — a client team member may read a session only if they
         * were actually in it.
         *
         * The client OWNER sees every session in their engagement; a department
         * head sees the ones they attended. That distinction is the point of
         * the row in §8, and it is why a controller invited to two meetings out
         * of twelve does not get the other ten.
         *
         * The caller supplies attendee_user_ids — resolving it here would mean
         * a database round trip inside a permission check on every row of a
         * list.
         */
        Policy::registerQualifier('attendee', static function (array $user, ?array $context): bool {
            if ($context === null) {
                return false;
            }

            $userId = (int) ($user['id'] ?? 0);

            if ($userId === 0) {
                return false;
            }

            $attendees = $context['attendee_user_ids'] ?? null;

            if (!is_array($attendees)) {
                return false;
            }

            foreach ($attendees as $candidate) {
                if ((int) $candidate === $userId) {
                    return true;
                }
            }

            return false;
        });

        /**
         * delivered (M7) — a client owner reads a deliverable once it has
         * actually been delivered, not while the coach is still drafting it.
         *
         * The caller sets 'delivered' from the document's status. A missing
         * flag denies, so a new screen that forgets to supply it shows the
         * client nothing rather than showing them a draft.
         */
        Policy::registerQualifier('delivered', static fn (array $user, ?array $context): bool
            => ($context['delivered'] ?? false) === true);

        /**
         * shared (M7) — a client team member or sponsor reads a document only
         * once it has been shared out. Same fail-closed shape as 'delivered'.
         *
         * Kept separate from 'delivered' even though they currently resolve
         * identically: they answer different questions, and per-recipient
         * sharing will diverge from firm-wide delivery.
         */
        Policy::registerQualifier('shared', static fn (array $user, ?array $context): bool
            => ($context['shared'] ?? false) === true);

        /**
         * owner (M9) — a client team member may enter a metric value only for
         * a metric they own.
         *
         * A scorecard where anyone can type any number is not a scorecard. The
         * point of naming an owner per metric is that one person is answerable
         * for it; letting the room edit each other's numbers dissolves that.
         *
         * The coach can still enter on someone's behalf — that path is a
         * different permission and is flagged on the value (on_behalf), so a
         * number the coach filled in is distinguishable from one the client
         * did.
         */
        Policy::registerQualifier('owner', static function (array $user, ?array $context): bool {
            if ($context === null) {
                return false;
            }

            $userId = (int) ($user['id'] ?? 0);
            $ownerId = $context['owner_user_id'] ?? null;

            if ($userId === 0 || $ownerId === null) {
                return false;
            }

            return (int) $ownerId === $userId;
        });

        /**
         * until_lock (M4B) — a coach or associate may edit scope only while it
         * is unlocked.
         *
         * After the lock, scope moves only through a reviewed change request.
         * The qualifier makes that structural rather than a convention someone
         * remembers: the edit permission genuinely disappears.
         *
         * A missing flag denies, so a screen that forgets to say whether scope
         * is locked gets the safe answer.
         */
        Policy::registerQualifier('until_lock', static fn (array $user, ?array $context): bool
            => ($context['scope_locked'] ?? true) === false);
    }
}
