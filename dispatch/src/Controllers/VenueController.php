<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;
use Dispatch\Core\Request;
use Dispatch\Models\Venue;
use Dispatch\Models\VenueSubmission;

class VenueController extends BaseController
{
    public function index(array $params = []): void
    {
        $venues = Venue::allActive();
        $types  = Venue::types();

        // Group by type
        $grouped = [];
        foreach ($venues as $v) {
            $grouped[$v['type']][] = $v;
        }

        $this->render('venues/index', [
            'title'   => 'Venue Library',
            'grouped' => $grouped,
            'types'   => $types,
            'venues'  => $venues,
        ]);
    }

    public function showSuggest(array $params = []): void
    {
        $types = Venue::types();
        $this->render('venues/suggest', [
            'title' => 'Suggest a Venue',
            'types' => $types,
        ]);
    }

    public function suggest(array $params = []): void
    {
        if (!Auth::verifyCsrf()) {
            $this->flash('error', 'Invalid request.');
            $this->redirect('/venues/suggest');
        }

        $name = trim(Request::post('venue_name', ''));
        if (empty($name)) {
            $this->flash('error', 'Venue name is required.');
            $this->redirect('/venues/suggest');
        }

        VenueSubmission::create([
            'user_id'             => Auth::userId(),
            'venue_name'          => $name,
            'submission_url'      => Request::post('submission_url'),
            'submission_email'    => Request::post('submission_email'),
            'venue_type'          => Request::post('venue_type'),
            'perceived_lead_time' => Request::post('perceived_lead_time'),
            'asset_requirements'  => Request::post('asset_requirements'),
            'justification'       => Request::post('justification'),
        ]);

        $this->flash('success', 'Venue submitted! Our team will review it and add it to the library if it meets our standards. Thank you!');
        $this->redirect('/venues');
    }
}
