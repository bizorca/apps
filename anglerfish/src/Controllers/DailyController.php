<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\View;
use Anglerfish\Models\DailyIdea;

/** The Daily Idea roster, the idea page each email links to, and the plates. */
final class DailyController
{
    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        View::render('daily', [
            'rows'   => DailyIdea::roster($status),
            'counts' => DailyIdea::counts(),
            'status' => $status,
            'today'  => DailyIdea::today(),
        ], 'Daily Idea');
    }

    public function show(string $id): void
    {
        $idea = DailyIdea::find((int) $id);
        if (!$idea) {
            http_response_code(404);
            View::render('error', ['code' => 404, 'message' => 'No such idea.'], 'Not found');
            return;
        }
        View::render('daily-idea', [
            'idea' => $idea,
            'send' => DailyIdea::sendFor((int) $idea['id']),
        ], DailyIdea::niceTitle($idea['title']));
    }

    /** A roster row's plate; ?w=1 for the 1456px copy the email carries. */
    public function plate(string $id): void
    {
        $row = DailyIdea::sendRow((int) $id);
        $file = $row && $row['image_path'] ? af_file((string) $row['image_path']) : null;
        if (!$file || !is_file($file)) {
            http_response_code(404);
            exit('No plate.');
        }
        if (isset($_GET['w'])) {
            try {
                $file = \Anglerfish\Services\Resize::forWeb($file);
            } catch (\Throwable $e) {
                error_log('plate resize failed: ' . $e->getMessage());
            }
        }
        header('Content-Type: ' . (str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=86400');
        readfile($file);
        exit;
    }
}
