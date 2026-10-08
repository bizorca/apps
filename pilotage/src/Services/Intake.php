<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

use Bizorca\Pilotage\Auth\RateLimiter;
use Bizorca\Pilotage\Core\ClientIp;
use Bizorca\Pilotage\Core\Database;
use Bizorca\Pilotage\Repositories\ClientContactRepository;
use Bizorca\Pilotage\Repositories\ClientOrgRepository;

/**
 * Public intake (ported in spirit from Foundry's applications form).
 *
 * A visitor with no advisor describes their situation and becomes a prospect
 * of a firm. Accepting a submission creates the client organization and its
 * first contact, so nothing is re-keyed — the same principle as FR-3.5's
 * prospect-to-active conversion.
 *
 * The whole surface is UNAUTHENTICATED, which makes spam the design
 * constraint rather than an afterthought. The one row that ever existed in
 * Foundry's applications table was spam: randomised field values, a throwaway
 * sender domain, a placeholder URL. So this checks three cheap signals before
 * accepting anything, and none of them requires a CAPTCHA:
 *
 *   1. A honeypot field a human never sees and a bot fills in.
 *   2. Time to fill. A form completed in under three seconds was not read.
 *   3. Rate limiting per IP, shared with the rest of the auth surface.
 *
 * None is individually strong. Together they stop the volume that actually
 * shows up, and a real person is never asked to identify a traffic light.
 */
final class Intake
{
    /** Faster than this and nobody read the questions. */
    public const MIN_SECONDS = 3;

    /** The honeypot input's name. Looks plausible enough for a bot to fill. */
    public const HONEYPOT_FIELD = 'company_website_url';

    /**
     * Is a public form open for this tenant?
     *
     * @param array<string,mixed> $tenant
     */
    public static function isOpen(array $tenant): bool
    {
        return (int) ($tenant['intake_enabled'] ?? 0) === 1;
    }

    /**
     * Record a submission.
     *
     * @param array<string,mixed> $input  Raw POST.
     * @param array<string,mixed> $server Usually $_SERVER.
     * @return array{id:?int, problems:array<int,string>, silent:bool}
     *         `silent` means it was rejected as spam — the caller should show
     *         the same thank-you page regardless, because telling a bot it was
     *         caught only helps it try again differently.
     */
    public static function submit(int $tenantId, array $input, array $server): array
    {
        $ip = ClientIp::resolve($server);

        // Honeypot. A human never sees this field, so anything in it is a bot.
        if (trim((string) ($input[self::HONEYPOT_FIELD] ?? '')) !== '') {
            self::record($tenantId, $input, $server, $ip, 'spam');
            return ['id' => null, 'problems' => [], 'silent' => true];
        }

        // Time to fill.
        $renderedAt = (int) ($input['_t'] ?? 0);
        $elapsed = $renderedAt > 0 ? time() - $renderedAt : null;

        if ($elapsed !== null && $elapsed < self::MIN_SECONDS) {
            self::record($tenantId, $input, $server, $ip, 'spam', $elapsed);
            return ['id' => null, 'problems' => [], 'silent' => true];
        }

        if ($ip !== null && RateLimiter::tooManyAttempts('intake', $ip, $ip)) {
            return ['id' => null, 'problems' => [], 'silent' => true];
        }

        $problems = self::problems($input);

        if ($problems !== []) {
            return ['id' => null, 'problems' => $problems, 'silent' => false];
        }

        if ($ip !== null) {
            RateLimiter::record('intake', $ip, $ip, false);
        }

        $id = self::record($tenantId, $input, $server, $ip, 'new', $elapsed);

        // A lead is the one thing in this product that is never batched. A
        // enquiry sitting in a digest until tomorrow morning is a enquiry that
        // has already booked with someone else.
        Notifications::queueMany(
            $tenantId,
            Notifications::firmStaff($tenantId),
            'intake.received',
            'New enquiry from ' . (trim((string) ($input['name'] ?? '')) ?: 'someone'),
            trim((string) ($input['message'] ?? '')) !== ''
                ? mb_substr(trim((string) $input['message']), 0, 200)
                : null,
            '/enquiries',
            ['object_type' => 'intake_submission', 'object_id' => $id]
        );

        return ['id' => $id, 'problems' => [], 'silent' => false];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<int,string>
     */
    public static function problems(array $input): array
    {
        $problems = [];

        if (trim((string) ($input['name'] ?? '')) === '') {
            $problems[] = 'Please tell us your name.';
        }

        $email = trim((string) ($input['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $problems[] = 'We need a working email address to reply to.';
        }

        if (trim((string) ($input['company_name'] ?? '')) === '') {
            $problems[] = 'Please tell us the name of your business.';
        }

        if (trim((string) ($input['situation'] ?? '')) === '') {
            $problems[] = 'Please say a little about what is going on — it is the part we actually read.';
        }

        if (!empty($input['revenue_band'])
            && !isset(ClientOrgRepository::REVENUE_BANDS[$input['revenue_band']])) {
            $problems[] = 'Unknown revenue band.';
        }

        return $problems;
    }

    /**
     * Accept a submission: create the prospect organization, its first
     * contact, and link the two back to the submission.
     *
     * @return int The new client organization id.
     */
    public static function accept(int $tenantId, int $submissionId, int $reviewerId, ?int $assignTo = null): int
    {
        $submission = self::find($tenantId, $submissionId);

        if ($submission === null) {
            throw new \RuntimeException('No such submission.');
        }

        if ((string) $submission['status'] === 'accepted') {
            return (int) $submission['client_org_id'];
        }

        $db = Database::conn();
        $db->beginTransaction();

        try {
            $orgs = new ClientOrgRepository($tenantId);

            $orgId = $orgs->createOrg([
                'name'           => (string) $submission['company_name'],
                'website'        => $submission['website'],
                'revenue_band'   => $submission['revenue_band'],
                'employee_count' => $submission['employee_count'],
                'situation'      => self::situationSummary($submission),
                'status'         => 'prospect',
                'owner_user_id'  => $assignTo ?? $reviewerId,
            ]);

            // The person who filled the form becomes the primary contact, with
            // no portal access yet — that is a decision for after a conversation.
            $contacts = new ClientContactRepository($tenantId);
            $contacts->createContact([
                'client_org_id' => $orgId,
                'name'          => (string) $submission['name'],
                'email'         => (string) $submission['email'],
                'phone'         => $submission['phone'],
                'portal_access' => 'none',
                'is_primary'    => 1,
            ]);

            $db->prepare(
                "UPDATE pl_intake_submissions
                 SET status = 'accepted', reviewed_by = :by, reviewed_at = NOW(), client_org_id = :org
                 WHERE tenant_id = :tid AND id = :id"
            )->execute(['by' => $reviewerId, 'org' => $orgId, 'tid' => $tenantId, 'id' => $submissionId]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        Timeline::record(
            $tenantId,
            $orgId,
            'intake.accepted',
            $submission['company_name'] . ' came in through the enquiry form',
            null,
            'intake',
            $submissionId,
            false
        );

        return $orgId;
    }

    public static function decline(int $tenantId, int $submissionId, int $reviewerId, ?string $note = null): bool
    {
        $stmt = Database::conn()->prepare(
            "UPDATE pl_intake_submissions
             SET status = 'declined', reviewed_by = :by, reviewed_at = NOW(), review_note = :note
             WHERE tenant_id = :tid AND id = :id AND status IN ('new','reviewing')"
        );
        $stmt->execute(['by' => $reviewerId, 'note' => $note, 'tid' => $tenantId, 'id' => $submissionId]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $tenantId, int $submissionId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM pl_intake_submissions WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $submissionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Submissions worth a human's attention. Spam is excluded by default —
     * it is kept for pattern-spotting, not for reading.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function pending(int $tenantId, bool $includeSpam = false): array
    {
        $sql = "SELECT * FROM pl_intake_submissions WHERE tenant_id = :tid";

        if (!$includeSpam) {
            $sql .= " AND status IN ('new','reviewing')";
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        $stmt = Database::conn()->prepare($sql);
        $stmt->execute(['tid' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function countNew(int $tenantId): int
    {
        $stmt = Database::conn()->prepare(
            "SELECT COUNT(*) AS c FROM pl_intake_submissions WHERE tenant_id = :tid AND status = 'new'"
        );
        $stmt->execute(['tid' => $tenantId]);

        return (int) $stmt->fetch()['c'];
    }

    // ------------------------------------------------------------- internals

    /** @param array<string,mixed> $submission */
    private static function situationSummary(array $submission): string
    {
        $parts = [];

        if (!empty($submission['situation'])) {
            $parts[] = "What they said is going on:\n" . $submission['situation'];
        }
        if (!empty($submission['desired_outcome'])) {
            $parts[] = "What good looks like to them:\n" . $submission['desired_outcome'];
        }
        if (!empty($submission['timeline'])) {
            $parts[] = 'Timeline: ' . $submission['timeline'];
        }

        $parts[] = 'Came in through the enquiry form on ' . date('j F Y', strtotime((string) $submission['created_at']));

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $server
     */
    private static function record(
        int $tenantId,
        array $input,
        array $server,
        ?string $ip,
        string $status,
        ?int $elapsed = null
    ): int {
        $db = Database::conn();

        $employees = trim((string) ($input['employee_count'] ?? ''));

        $db->prepare(
            'INSERT INTO pl_intake_submissions
                (tenant_id, name, email, phone, company_name, website, revenue_band, employee_count,
                 situation, desired_outcome, timeline, status, ip, user_agent, seconds_to_fill, referrer)
             VALUES (:tid, :name, :email, :phone, :company, :website, :band, :emp,
                     :situation, :outcome, :timeline, :status, :ip, :ua, :secs, :ref)'
        )->execute([
            'tid'       => $tenantId,
            'name'      => mb_substr(trim((string) ($input['name'] ?? '')), 0, 255),
            'email'     => mb_substr(mb_strtolower(trim((string) ($input['email'] ?? ''))), 0, 255),
            'phone'     => mb_substr(trim((string) ($input['phone'] ?? '')), 0, 50) ?: null,
            'company'   => mb_substr(trim((string) ($input['company_name'] ?? '')), 0, 255),
            'website'   => mb_substr(trim((string) ($input['website'] ?? '')), 0, 255) ?: null,
            'band'      => !empty($input['revenue_band']) && isset(ClientOrgRepository::REVENUE_BANDS[$input['revenue_band']])
                ? $input['revenue_band'] : null,
            'emp'       => ctype_digit($employees) ? (int) $employees : null,
            'situation' => trim((string) ($input['situation'] ?? '')) ?: null,
            'outcome'   => trim((string) ($input['desired_outcome'] ?? '')) ?: null,
            'timeline'  => mb_substr(trim((string) ($input['timeline'] ?? '')), 0, 120) ?: null,
            'status'    => $status,
            'ip'        => $ip === null ? null : RateLimiter::packIp($ip),
            'ua'        => mb_substr((string) ($server['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            'secs'      => $elapsed,
            'ref'       => mb_substr((string) ($server['HTTP_REFERER'] ?? ''), 0, 255) ?: null,
        ]);

        return (int) $db->lastInsertId();
    }
}
