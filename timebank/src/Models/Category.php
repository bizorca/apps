<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class Category extends BaseModel
{
    protected string $table = 'tm_categories';

    /**
     * All active categories for the tenant, sorted by sort_order.
     */
    public function getActive(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT * FROM `tm_categories`
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY sort_order ASC, name ASC",
            [$tenantId]
        );
    }

    /**
     * All active categories with a count of active offers attached to each.
     */
    public function getWithCounts(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT
                c.*,
                COUNT(o.id) AS offer_count
             FROM `tm_categories` c
             LEFT JOIN `tm_offers` o
                ON o.category_id = c.id
               AND o.is_active   = 1
               AND o.tenant_id   = c.tenant_id
             WHERE c.tenant_id = ? AND c.is_active = 1
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC",
            [$tenantId]
        );
    }

    /**
     * Reorder categories. Pass an ordered array of category IDs;
     * each category receives a sort_order equal to its position in the array.
     *
     * @param int[] $ids Ordered array of category IDs
     */
    public function reorder(array $ids): void
    {
        foreach ($ids as $position => $id) {
            DB::update(
                $this->table,
                ['sort_order' => $position + 1],
                ['id' => (int) $id, 'tenant_id' => $this->tenantId]
            );
        }
    }
}
