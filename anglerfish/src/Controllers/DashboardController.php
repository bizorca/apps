<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Database;
use Anglerfish\Core\View;

final class DashboardController
{
    public function index(): void
    {
        $counts = Database::one("
            SELECT
              (SELECT COUNT(*) FROM af_books)                                    AS books,
              (SELECT COUNT(*) FROM af_books WHERE scope='press')                AS books_press,
              (SELECT COUNT(*) FROM af_books WHERE scope_confirmed=0)            AS intake,
              (SELECT COUNT(*) FROM af_chapters)                                 AS chapters,
              (SELECT COUNT(*) FROM af_concepts)                                 AS concepts,
              (SELECT COUNT(*) FROM af_artifacts)                                AS artifacts,
              (SELECT COUNT(*) FROM af_artifacts WHERE review_status='flagged')  AS flagged,
              (SELECT COUNT(*) FROM af_assets WHERE status='ready')              AS assets,
              (SELECT COUNT(*) FROM af_posts)                                    AS posts,
              (SELECT COUNT(*) FROM af_posts WHERE status='published')           AS published,
              (SELECT COUNT(*) FROM af_triage WHERE mark='write_about')          AS queue,
              (SELECT COUNT(*) FROM af_jobs WHERE status IN ('queued','leased','running')) AS jobs_open,
              (SELECT COUNT(*) FROM af_jobs WHERE status='dead')                 AS jobs_dead
        ") ?: [];

        $spend = Database::value(
            "SELECT COALESCE(SUM(cost_usd),0) FROM af_api_calls
              WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
        );

        View::render('dashboard', ['counts' => $counts, 'spend' => (float) $spend], 'Dashboard');
    }
}
