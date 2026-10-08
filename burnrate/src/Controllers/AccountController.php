<?php

namespace App\Controllers;

use App\Models\Player;
use App\Models\Owner;

class AccountController extends BaseController
{
    public function dashboard(): void
    {
        $playerModel = new Player($this->app->db);
        $players = $playerModel->findByOwner($this->app->ownerId());

        $ownerModel = new Owner($this->app->db);
        $owner = $ownerModel->findById($this->app->ownerId());

        $this->render('account/dashboard', [
            'players' => $players,
            'owner' => $owner,
        ]);
    }

    public function createPlayer(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/account');
            return;
        }

        $name = trim($_POST['player_name'] ?? '');
        if (empty($name)) {
            $this->flash('error', 'Player name is required.');
            $this->redirect('/account');
        }

        $playerModel = new Player($this->app->db);

        $memberLevel = $this->app->memberLevel();
        if ($memberLevel < 1) {
            $existing = $playerModel->findByOwner($this->app->ownerId());
            if (count($existing) >= 1) {
                $this->flash('error', 'Freeloader tier is limited to 1 player slot. Complete a round to unlock multiple slots!');
                $this->redirect('/account');
            }
        }

        $playerId = $playerModel->create($this->app->ownerId(), $name);

        $this->flash('success', "Trust fund baby '{$name}' created with \$1,000,000,000! Click Play to start spending.");
        $this->redirect('/account');
    }

    public function selectPlayer(string $id): void
    {
        $playerId = (int) $id;
        $playerModel = new Player($this->app->db);
        $player = $playerModel->findByOwnerAndId($this->app->ownerId(), $playerId);

        if (!$player) {
            $this->flash('error', 'Player not found.');
            $this->redirect('/account');
        }

        $this->app->session->set('player_id', $playerId);
        $this->app->session->set('player_name', $player->PlayerName);
        $this->redirect('/game');
    }

    public function deletePlayer(string $id): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/account');
            return;
        }

        $playerId = (int) $id;
        $playerModel = new Player($this->app->db);

        if ($playerModel->deleteWithTables($this->app->ownerId(), $playerId)) {
            if ($this->app->playerId() === $playerId) {
                $this->app->session->remove('player_id');
                $this->app->session->remove('player_name');
            }
            $this->flash('success', 'Player deleted. Their fortune has been returned to the void.');
        } else {
            $this->flash('error', 'Could not delete player.');
        }

        $this->redirect('/account');
    }

    public function top10(): void
    {
        $playerModel = new Player($this->app->db);
        $top10 = $playerModel->getTop10();
        $this->render('account/top10', ['players' => $top10]);
    }
}
