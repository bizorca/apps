<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\Auth;

class HomeController extends BaseController
{
    public function index(array $params = []): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->render('home/index', ['title' => 'Every Venue. Every Deadline. One Dashboard.'], 'app');
    }

    public function about(array $params = []): void
    {
        $this->render('home/about', ['title' => 'About'], 'app');
    }

    public function features(array $params = []): void
    {
        $this->render('home/features', ['title' => 'Features'], 'app');
    }

    public function pricing(array $params = []): void
    {
        $this->render('home/pricing', ['title' => 'Pricing'], 'app');
    }
}
