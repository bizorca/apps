<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\Api;
use Anglerfish\Services\JobTick;
use Anglerfish\Services\ServerJobs;

/**
 * Page-load job processing. The browser calls this in the background after a
 * page has rendered, so queued server-side work runs without cron and without
 * slowing anything down.
 */
final class TickController
{
    public function tick(): void
    {
        // Long enough for one composition; the tick itself guards to 70s.
        set_time_limit(110);
        ignore_user_abort(true);
        // The shared tools session would stay locked for the whole tick and
        // stall every other page load in this browser; the tick needs none of it.
        session_write_close();

        $res = JobTick::run(
            max: 2,
            force: isset($_POST['force'])
        );

        Api::json($res + [
            'pending_local' => JobTick::pendingLocal(),
            'server_types'  => ServerJobs::types(),
        ]);
    }
}
