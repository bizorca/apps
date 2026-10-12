<?php

namespace Anglerfish\Controllers;

use Anglerfish\Core\View;
use Anglerfish\Models\DailyIdea;

/** The Daily Idea log and the idea page each email links to. */
final class DailyController
{
    public function index(): void
    {
        View::render('daily', [
            'sends'  => DailyIdea::sends(),
            'counts' => DailyIdea::counts(),
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
}
