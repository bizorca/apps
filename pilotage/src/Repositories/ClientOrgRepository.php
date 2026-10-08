<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Repositories;

use Bizorca\Pilotage\Core\Repository;

/**
 * Client organizations — the business being advised (FR-3.1).
 *
 * Statuses: prospect -> active -> archived. A prospect is a real record that
 * can hold intake and proposal material before any engagement exists, so
 * converting does not mean re-keying anything (FR-3.5).
 */
final class ClientOrgRepository extends Repository
{
    public const ENTITY_TYPES = [
        'sole_prop'   => 'Sole proprietorship',
        'partnership' => 'Partnership',
        'llc'         => 'LLC',
        's_corp'      => 'S corporation',
        'c_corp'      => 'C corporation',
        'nonprofit'   => 'Nonprofit',
        'other'       => 'Other',
    ];

    public const REVENUE_BANDS = [
        'under_250k'  => 'Under $250k',
        '250k_1m'     => '$250k–$1M',
        '1m_5m'       => '$1M–$5M',
        '5m_25m'      => '$5M–$25M',
        '25m_plus'    => '$25M+',
        'undisclosed' => 'Prefer not to say',
    ];

    protected function table(): string
    {
        return 'pl_client_orgs';
    }

    /** @return string[] */
    protected function writable(): array
    {
        return [
            'name', 'legal_name', 'entity_type', 'industry_naics', 'employee_count',
            'revenue_band', 'fiscal_year_end', 'website', 'phone',
            'address_line1', 'address_line2', 'city', 'region', 'postal_code', 'country',
            'situation', 'status', 'owner_user_id', 'team_invite_cap',
        ];
    }

    /** @param array<string,mixed> $data */
    public function createOrg(array $data): int
    {
        $problems = self::problems($data);

        if ($problems !== []) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }

        return $this->insert($data);
    }

    /**
     * Validation shared by the create and edit paths.
     *
     * @param array<string,mixed> $data
     * @return string[]
     */
    public static function problems(array $data, bool $partial = false): array
    {
        $problems = [];

        if (!$partial || array_key_exists('name', $data)) {
            if (trim((string) ($data['name'] ?? '')) === '') {
                $problems[] = 'A business name is required.';
            }
        }

        if (!empty($data['entity_type']) && !isset(self::ENTITY_TYPES[$data['entity_type']])) {
            $problems[] = 'Unknown entity type.';
        }

        if (!empty($data['revenue_band']) && !isset(self::REVENUE_BANDS[$data['revenue_band']])) {
            $problems[] = 'Unknown revenue band.';
        }

        if (!empty($data['industry_naics']) && !preg_match('/^\d{2,6}$/', (string) $data['industry_naics'])) {
            $problems[] = 'NAICS code should be two to six digits.';
        }

        // MM-DD. Stored without a year because a fiscal year end is a date in
        // the calendar, not a moment in time.
        if (!empty($data['fiscal_year_end'])) {
            $fye = (string) $data['fiscal_year_end'];
            if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $fye)) {
                $problems[] = 'Fiscal year end should look like MM-DD.';
            }
        }

        if (!empty($data['website'])) {
            $url = (string) $data['website'];
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $problems[] = 'That website address does not look right.';
            }
        }

        if (isset($data['employee_count']) && $data['employee_count'] !== '' && $data['employee_count'] !== null) {
            if (!ctype_digit((string) $data['employee_count'])) {
                $problems[] = 'Employee count should be a whole number.';
            }
        }

        if (isset($data['country']) && $data['country'] !== null && $data['country'] !== '') {
            if (!preg_match('/^[A-Za-z]{2}$/', (string) $data['country'])) {
                $problems[] = 'Country should be a two-letter code.';
            }
        }

        return $problems;
    }

    /**
     * Normalize form input before it is written. Empty strings become null so
     * "not recorded" and "recorded as blank" do not both end up as ''.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalize(array $input): array
    {
        $out = [];

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }
            $out[$key] = $value;
        }

        if (!empty($out['website']) && !preg_match('#^https?://#i', (string) $out['website'])) {
            $out['website'] = 'https://' . $out['website'];
        }

        if (!empty($out['country'])) {
            $out['country'] = strtoupper((string) $out['country']);
        }

        if (isset($out['employee_count']) && $out['employee_count'] !== null) {
            $out['employee_count'] = (int) $out['employee_count'];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return $this->all(['status' => 'active'], 'name asc');
    }

    /** @return array<int,array<string,mixed>> */
    public function prospects(): array
    {
        return $this->all(['status' => 'prospect'], 'name asc');
    }

    /** @return array<int,array<string,mixed>> Everything a given coach owns. */
    public function ownedBy(int $userId): array
    {
        return $this->all(['owner_user_id' => $userId], 'name asc');
    }

    /**
     * Prospect -> active (FR-3.5). Nothing is re-keyed; the record simply
     * changes state and stamps when it happened.
     */
    public function convert(int $orgId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ' . $this->table() . "
             SET status = 'active', converted_at = NOW()
             WHERE id = :id AND tenant_id = :tid AND status = 'prospect'"
        );
        $stmt->execute(['id' => $orgId, 'tid' => $this->tenantId]);

        return $stmt->rowCount() === 1;
    }

    public function archive(int $orgId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ' . $this->table() . "
             SET status = 'archived', archived_at = NOW()
             WHERE id = :id AND tenant_id = :tid AND status <> 'archived'"
        );
        $stmt->execute(['id' => $orgId, 'tid' => $this->tenantId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Search by name or legal name. LIKE is fine at this scale — a firm has
     * tens to low hundreds of client organizations, not millions.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return $this->all([], 'name asc');
        }

        // Distinct placeholders per occurrence: with emulated prepares off,
        // PDO binds natively and a named parameter cannot be reused.
        $stmt = $this->db->prepare(
            'SELECT * FROM ' . $this->table() . '
             WHERE tenant_id = :tid AND (name LIKE :q1 OR legal_name LIKE :q2)
             ORDER BY name ASC LIMIT 100'
        );

        $like = '%' . $term . '%';
        $stmt->execute(['tid' => $this->tenantId, 'q1' => $like, 'q2' => $like]);

        return $stmt->fetchAll();
    }

    /** Counts by status, for the list header. @return array<string,int> */
    public function countsByStatus(): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . $this->table() . '
             WHERE tenant_id = :tid GROUP BY status'
        );
        $stmt->execute(['tid' => $this->tenantId]);

        $out = ['prospect' => 0, 'active' => 0, 'archived' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['status']] = (int) $row['c'];
        }

        return $out;
    }
}
