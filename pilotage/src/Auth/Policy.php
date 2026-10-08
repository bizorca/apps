<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Auth;

/**
 * The permission matrix from SPEC.md §8, as data.
 *
 * FR-2.5: every authorization decision resolves against this matrix. There is
 * no "is admin, therefore yes" shortcut anywhere in the codebase.
 *
 * A firm owner holds every OPERATIONAL permission a coach does — scope,
 * sessions, tasks, documents, goals, metrics, issues, threads. That is not a
 * weakening of the separation; it is what makes a solo practice usable. Most
 * firms buying this are one to three people, and in a solo firm the owner IS
 * the coach. An earlier reading of §8 gave the owner read-only access to all
 * of it, which left a one-person practice unable to run an engagement at all
 * — caught by logging in as an owner and finding every button return 403.
 *
 * The one thing an owner still cannot reach is `session_private_note`. Owning
 * the subscription is not the same as being party to a particular coaching
 * conversation, and a coach's private read on a client should not be visible
 * to their boss. That distinction survives; the operational lockout did not
 * deserve to.
 *
 * Two layers:
 *
 *   1. The role grid below. Coarse: may a coach ever update a task?
 *   2. Qualifiers. Fine: may this coach update THIS task? A cell like
 *      "CRUD own" grants the action only if the 'own' qualifier resolves true
 *      for the given context.
 *
 * Qualifier resolvers are registered by the module that owns the concept —
 * engagement membership arrives with M3/M4, attendance with M5. Until a
 * resolver is registered, its qualifier DENIES. Failing closed is the whole
 * point: an unimplemented check must never read as permission.
 */
final class Policy
{
    public const CREATE = 'create';
    public const READ   = 'read';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    /**
     * object => role => [actions, qualifier]
     *
     * Actions use CRUD shorthand plus any named extras. A dash means no access.
     * Transcribed directly from SPEC.md §8 — keep the two in step.
     */
    private const MATRIX = [
        'tenant_settings' => [
            'firm_owner' => ['CRUD', null],
        ],
        /**
         * Firm seats: adding, disabling, and re-roling the firm's own staff.
         * Firm-owner only, because every seat is a billing decision.
         */
        'user' => [
            'firm_owner' => ['CRUD', null],
        ],

        /**
         * Portal access for people at a CLIENT organization. A separate object
         * from 'user' on purpose.
         *
         * The §8 matrix originally had one "Users & seats" row, which made a
         * coach unable to invite their own client's CFO to the portal — the
         * single most ordinary act in the product. Merging the two the other
         * way would have let any coach mint firm seats. They are different
         * decisions with different blast radius, so they are different objects.
         *
         * Client owners may invite their own team, capped per organization
         * (FR-3.3).
         */
        'client_portal_access' => [
            'firm_owner'   => ['CRUD', null],
            'coach'        => ['CRUD', null],
            'associate'    => ['CR', null],
            'client_owner' => ['C', 'own_org'],
        ],
        'playbook_template' => [
            'firm_owner' => ['CRUD', null],
            'coach'      => ['CRU', null],
            'associate'  => ['R', null],
        ],
        'engagement' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', 'own'],
            'associate'     => ['R', 'assigned'],
            'client_owner'  => ['R', 'own'],
            'client_member' => ['R', 'own'],
            'sponsor'       => ['R', 'own'],
        ],
        'playbook_instance' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['RU', 'assigned'],
            'client_owner'  => ['R', 'client_facing'],
            'client_member' => ['R', 'client_facing'],
            'sponsor'       => ['R', 'progress_only'],
        ],
        'scope_item' => [
            'firm_owner'    => ['CRUD', 'until_lock'],
            'coach'         => ['CRUD', 'until_lock'],
            'associate'     => ['CRU', 'until_lock'],
            'client_owner'  => ['R accept', null],
            'client_member' => ['R', null],
            'sponsor'       => ['R', null],
        ],
        'change_request' => [
            'firm_owner'   => ['CRU review', null],
            'coach'        => ['CRU review', null],
            'associate'    => ['CR', null],
            'client_owner' => ['CR', null],
        ],
        'session' => [                            // shared notes
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['RU', 'assigned'],
            'client_owner'  => ['R', null],
            'client_member' => ['R', 'attendee'],
        ],
        'session_private_note' => [
            'coach' => ['CRUD', 'own'],           // nobody else, ever
        ],
        'task' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['CRU', 'assigned'],
            'client_owner'  => ['CRU', 'own_org'],
            'client_member' => ['RU', 'own'],
        ],
        'document_deliverable' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['CRU', null],
            'client_owner'  => ['R', 'delivered'],
            'client_member' => ['R', 'shared'],
            'sponsor'       => ['R', 'shared'],
        ],
        'document_client_file' => [
            'firm_owner'    => ['R', null],
            'coach'         => ['R', null],
            'associate'     => ['R', null],
            'client_owner'  => ['CRUD', null],
            'client_member' => ['CRU', 'own'],
        ],
        'document_request' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['CRU', null],
            'client_owner'  => ['R fulfill', null],
            'client_member' => ['R fulfill', null],
        ],
        'message_thread' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['CRU', 'assigned'],
            'client_owner'  => ['CRUD', null],
            'client_member' => ['CRU', 'own'],
        ],
        'goal' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['CRU', null],
            'client_owner'  => ['CRU', null],
            'client_member' => ['R', null],
            'sponsor'       => ['R', null],
        ],
        'metric_definition' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['CRU', null],
            'client_owner'  => ['R', null],
            'client_member' => ['R', null],
            'sponsor'       => ['R', null],
        ],
        'metric_value' => [
            'firm_owner'    => ['CRU', null],
            'coach'         => ['CRU', null],
            'associate'     => ['CRU', null],
            'client_owner'  => ['CRU', null],
            'client_member' => ['CRU', 'owner'],
            'sponsor'       => ['R', null],
        ],
        'issue' => [
            'firm_owner'    => ['CRUD', null],
            'coach'         => ['CRUD', null],
            'associate'     => ['CRU', null],
            'client_owner'  => ['CRU', null],
            'client_member' => ['CRU', 'own'],
        ],
        'worksheet_template' => [
            'firm_owner' => ['CRUD', null],
            'coach'      => ['CRUD', null],
            'associate'  => ['CRU', null],
        ],
        'worksheet_response' => [
            'firm_owner'    => ['R', null],
            'coach'         => ['R', null],
            'associate'     => ['R', null],
            'client_owner'  => ['CRU', 'own_org'],
            'client_member' => ['CRU', 'own'],
        ],
        'audit_log' => [
            'firm_owner' => ['R', null],
        ],
        'billing' => [
            'firm_owner' => ['CRUD', null],
        ],
    ];

    /** @var array<string, callable(array, ?array): bool> */
    private static array $qualifiers = [];

    /**
     * Register a resolver for a qualifier.
     *
     * @param callable(array $user, ?array $context): bool $resolver
     */
    public static function registerQualifier(string $name, callable $resolver): void
    {
        self::$qualifiers[$name] = $resolver;
    }

    public static function resetQualifiers(): void
    {
        self::$qualifiers = [];
    }

    /** @return string[] Qualifiers used by the matrix that have no resolver yet. */
    public static function unresolvedQualifiers(): array
    {
        $needed = [];

        foreach (self::MATRIX as $roles) {
            foreach ($roles as [$actions, $qualifier]) {
                if ($qualifier !== null) {
                    $needed[$qualifier] = true;
                }
            }
        }

        return array_values(array_diff(array_keys($needed), array_keys(self::$qualifiers)));
    }

    /**
     * The decision.
     *
     * @param array $user    A pl_users row. Must carry 'role'.
     * @param array|null $context Whatever the qualifier needs — typically the
     *                            object row plus its engagement.
     */
    public static function can(array $user, string $action, string $objectType, ?array $context = null): bool
    {
        $role = $user['role'] ?? null;

        if (!is_string($role) || $role === '') {
            return false;
        }

        // A disabled or merely-invited user has no permissions at all.
        if (($user['status'] ?? 'active') !== 'active') {
            return false;
        }

        if (!isset(self::MATRIX[$objectType])) {
            // Unknown object type: deny. Adding a new object without adding it
            // to the matrix should lock it down, not open it up.
            return false;
        }

        if (!isset(self::MATRIX[$objectType][$role])) {
            return false;
        }

        [$actions, $qualifier] = self::MATRIX[$objectType][$role];

        if (!in_array($action, self::expand($actions), true)) {
            return false;
        }

        if ($qualifier === null) {
            return true;
        }

        // Fail closed on an unregistered qualifier.
        if (!isset(self::$qualifiers[$qualifier])) {
            return false;
        }

        return (bool) (self::$qualifiers[$qualifier])($user, $context);
    }

    /**
     * Same decision, but throws. Use at the top of any controller action so a
     * missing check is a crash rather than a silent leak.
     *
     * @throws \Bizorca\Pilotage\Core\HttpException
     */
    public static function authorize(array $user, string $action, string $objectType, ?array $context = null): void
    {
        if (!self::can($user, $action, $objectType, $context)) {
            // 404 rather than 403 for reads: revealing that an object exists
            // but is forbidden is itself a leak across the client/coach wall.
            $status = $action === self::READ ? 404 : 403;
            throw new \Bizorca\Pilotage\Core\HttpException($status, 'Not permitted: ' . $action . ' ' . $objectType);
        }
    }

    /** The qualifier attached to a cell, or null. Useful for building UI hints. */
    public static function qualifierFor(string $role, string $objectType): ?string
    {
        return self::MATRIX[$objectType][$role][1] ?? null;
    }

    /** @return string[] All object types in the matrix. */
    public static function objectTypes(): array
    {
        return array_keys(self::MATRIX);
    }

    /** @return string[] */
    private static function expand(string $actions): array
    {
        $out = [];
        $extras = '';

        // Leading CRUD letters, then any space-separated named actions.
        if (preg_match('/^([CRUD]*)\s*(.*)$/', trim($actions), $m) === 1) {
            $letters = $m[1];
            $extras = trim($m[2]);

            $map = ['C' => self::CREATE, 'R' => self::READ, 'U' => self::UPDATE, 'D' => self::DELETE];
            for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
                $out[] = $map[$letters[$i]];
            }
        }

        if ($extras !== '') {
            foreach (preg_split('/\s+/', $extras) ?: [] as $extra) {
                if ($extra !== '') {
                    $out[] = $extra;
                }
            }
        }

        return $out;
    }
}
