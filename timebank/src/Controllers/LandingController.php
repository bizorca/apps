<?php

declare(strict_types=1);

namespace TimeBank\Controllers;

use TimeBank\Core\DB;
use TimeBank\Core\View;

class LandingController extends BaseController
{
    public function index(): void
    {
        // Pull live stats from all tenants combined for social proof
        $stats = DB::fetch(
            "SELECT
                (SELECT COUNT(*) FROM `tm_tenants` WHERE is_active = 1) AS total_timebanks,
                (SELECT COUNT(*) FROM `tm_members` WHERE is_active = 1 AND is_approved = 1) AS total_members,
                (SELECT COALESCE(SUM(hours), 0) FROM `tm_transactions` WHERE status = 'confirmed') AS total_hours,
                (SELECT COUNT(*) FROM `tm_offers` WHERE is_active = 1) AS total_offers"
        ) ?: ['total_timebanks' => 0, 'total_members' => 0, 'total_hours' => 0, 'total_offers' => 0];

        // Active timebanks for the directory listing
        // Members and hours come from separate subqueries. The original joined
        // members and transactions together and summed hours across that
        // product, so every community's "hours exchanged" was multiplied by
        // its member count.
        $timebanks = DB::fetchAll(
            "SELECT t.subdomain, t.name, t.tagline,
                    (SELECT COUNT(*) FROM `tm_members` m
                      WHERE m.tenant_id = t.id AND m.is_active = 1 AND m.is_approved = 1) AS member_count,
                    (SELECT COALESCE(SUM(tx.hours), 0) FROM `tm_transactions` tx
                      WHERE tx.tenant_id = t.id AND tx.status = 'confirmed') AS hours_exchanged
             FROM `tm_tenants` t
             WHERE t.is_active = 1
             ORDER BY member_count DESC, t.name ASC"
        );

        View::render('landing/index', [
            'pageTitle' => 'TimeBank — Exchange Skills, Build Community',
            'stats'     => $stats,
            'timebanks' => $timebanks,
        ], 'layout/landing');
    }
}
