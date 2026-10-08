<?php
/** MarketingController and the /up health check. */

declare(strict_types=1);

function marketing_home(array $p): never
{
    $u = fm_user();
    if ($u && !$u->account?->isCancelled()) {
        redirect_to(route('boards.index'));
    }
    view('marketing.home');
}

function marketing_features(array $p): never { view('marketing.features'); }
function marketing_about(array $p): never { view('marketing.about'); }
function marketing_services(array $p): never { view('marketing.services'); }

function health(array $p): never
{
    json_response(['status' => 'ok']);
}
