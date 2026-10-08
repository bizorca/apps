<?php

namespace App\Controllers;

use App\Models\Owner;
use App\Models\Player;
use App\Models\GameLog;
use App\Models\SiteSettings;

class AdminController extends BaseController
{
    public function dashboard(): void
    {
        $ownerModel = new Owner($this->app->db);
        $playerModel = new Player($this->app->db);

        $this->render('admin/dashboard', [
            'totalOwners' => $ownerModel->count(),
            'totalPlayers' => $playerModel->count(),
            'recentOwners' => $ownerModel->getAll(10),
        ]);
    }

    public function owners(): void
    {
        $ownerModel = new Owner($this->app->db);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $this->render('admin/owners', [
            'owners' => $ownerModel->getAll($perPage, $offset),
            'total' => $ownerModel->count(),
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function players(): void
    {
        $players = $this->app->db->fetchAll(
            "SELECT p.*, u.email AS Email,
                    SUBSTRING_INDEX(u.name, ' ', 1) AS FirstName,
                    TRIM(SUBSTRING(u.name, LENGTH(SUBSTRING_INDEX(u.name, ' ', 1)) + 1)) AS LastName
             FROM br_players p JOIN users u ON p.OwnerID = u.id
             ORDER BY p.LastPlayedDate DESC LIMIT 50"
        );

        $this->render('admin/players', ['players' => $players]);
    }

    public function gameLogs(): void
    {
        $playerId = (int) ($_GET['player_id'] ?? 0);
        $logs = [];

        if ($playerId > 0) {
            $gameLog = new GameLog($this->app->db);
            $logs = $gameLog->getByPlayer($playerId, 100);
        }

        $this->render('admin/game_logs', [
            'logs' => $logs,
            'playerId' => $playerId,
        ]);
    }

    public function settings(): void
    {
        $settingsModel = new SiteSettings($this->app->db);
        $this->render('admin/settings', [
            'settings' => $settingsModel->getAll(),
        ]);
    }

    public function saveSettings(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/admin/settings');
            return;
        }

        $settingsModel = new SiteSettings($this->app->db);

        $settingsModel->set('registration_open', isset($_POST['registration_open']) ? '1' : '0');
        $settingsModel->set('stripe_enabled', isset($_POST['stripe_enabled']) ? '1' : '0');

        // Stripe keys used to be saved here, in plain text in the database.
        // They come from .env.php now (see includes/app.php), so only the
        // tagline is a free-text setting.
        $textFields = ['site_tagline'];
        foreach ($textFields as $field) {
            if (isset($_POST[$field])) {
                $settingsModel->set($field, trim($_POST[$field]));
            }
        }

        $this->flash('success', 'Settings saved successfully.');
        $this->redirect('/admin/settings');
    }

    public function createUserForm(): void
    {
        $this->render('admin/create_user', [
            'membershipTiers' => $this->app->config['membership'],
        ]);
    }

    public function createUser(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/admin/users/create');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $memberLevel = (int) ($_POST['member_level'] ?? 0);

        $errors = [];
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (empty($firstName)) {
            $errors[] = 'First name is required.';
        }
        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        $existing = tl_db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $existing->execute([strtolower($email)]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $hasOwner = (new Owner($this->app->db))->findById((int) $existingId);
            if ($hasOwner) {
                $errors[] = 'An account with this email already exists.';
            }
        }

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/admin/users/create');
            return;
        }

        // A Burn Rate player is a shared tools account plus a br_owners row.
        // An existing tools account (from another tool) just gets the row and
        // keeps its own password; otherwise the account is created here.
        if ($existingId) {
            $ownerId = (int) $existingId;
        } else {
            tl_db()->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)')
                   ->execute([strtolower($email), password_hash($password, PASSWORD_DEFAULT), trim($firstName . ' ' . $lastName)]);
            $ownerId = (int) tl_db()->lastInsertId();
        }
        $ownerModel = new Owner($this->app->db);
        $ownerModel->ensure($ownerId);
        $ownerModel->update($ownerId, ['MemberLevel' => $memberLevel]);

        $this->flash('success', "User created (ID: {$ownerId}). Email: {$email}" . ($existingId ? ' (existing tools account; their password is unchanged)' : ''));
        $this->redirect('/admin/owners');
    }
}
