<?php

declare(strict_types=1);

namespace Dispatch\Controllers;

use Dispatch\Core\View;
use Dispatch\Core\Response;
use Dispatch\Core\Session;

abstract class BaseController
{
    protected function render(string $view, array $data = [], string $layout = 'dashboard'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $url): never
    {
        Response::redirect($url);
    }

    protected function flash(string $type, string $message): void
    {
        Session::setFlash($type, $message);
    }

    protected function notFound(): never
    {
        Response::notFound();
    }

    protected function forbidden(): never
    {
        Response::forbidden();
    }
}
