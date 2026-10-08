<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\View;
use Anglerfish\Models\Video;

/** The video production queue (migration 019). */
final class VideoController
{
    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        $part   = (int) ($_GET['part'] ?? 0);
        $q      = trim((string) ($_GET['q'] ?? ''));

        View::render('videos', [
            'videos' => Video::all($status, $part, $q),
            'counts' => Video::counts(),
            'parts'  => Video::parts(),
            'status' => $status,
            'part'   => $part,
            'q'      => $q,
        ], 'Videos');
    }

    public function show(string $number): void
    {
        $v = Video::find((int) $number);
        if (!$v) {
            http_response_code(404);
            View::render('error', ['code' => 404, 'message' => 'No such video.'], 'Not found');
            return;
        }
        View::render('video', [
            'v'          => $v,
            'spoken'     => Video::spoken((string) $v['script']),
            'markers'    => Video::markers((string) $v['script'] . "\n" . (string) $v['notes']),
            'neighbours' => Video::neighbours((int) $v['number']),
        ], sprintf('#%d %s', $v['number'], $v['title'] ?: $v['topic']));
    }

    public function update(string $number): void
    {
        try {
            Video::updateTracking((int) $number, $_POST);
            flash('ok', 'Saved.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/videos/' . (int) $number);
    }

    /** Plain text for a teleprompter app. */
    public function download(string $number): void
    {
        $v = Video::find((int) $number);
        if (!$v || !$v['script']) {
            http_response_code(404);
            exit('No script yet.');
        }
        header('Content-Type: text/plain; charset=utf-8');
        header(sprintf('Content-Disposition: attachment; filename="%03d-%s.txt"',
            $v['number'], $v['slug']));
        echo ($v['title'] ?: $v['topic']), "\n\n", Video::spoken((string) $v['script']), "\n";
    }
}
