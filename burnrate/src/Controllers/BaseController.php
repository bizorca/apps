<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Helpers;

abstract class BaseController
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    protected function render(string $view, array $data = []): void
    {
        echo $this->app->view($view, $data);
    }

    protected function redirect(string $url): never
    {
        Helpers::redirect($url);
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->session->flash($type, $message);
    }

    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function verifyCsrf(): bool
    {
        $token = $_POST['_csrf_token'] ?? '';
        if (!$this->app->session->verifyCsrf($token)) {
            $this->flash('error', 'Invalid form submission. Please try again.');
            return false;
        }
        return true;
    }

    protected function getPlayer(): ?object
    {
        $playerId = $this->app->playerId();
        if (!$playerId) return null;

        $playerModel = new \App\Models\Player($this->app->db);
        return $playerModel->findByOwnerAndId($this->app->ownerId(), $playerId);
    }
}
