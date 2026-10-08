<?php

namespace App\Controllers;

use App\Services\GameEngine;
use App\Services\LuxuryPropertyService;
use App\Services\ToyService;
use App\Services\InvestmentService;
use App\Services\EntourageService;
use App\Services\BankService;
use App\Services\EconomyService;
use App\Services\QuotesService;
use App\Models\RealEstate;
use App\Models\Stock;
use App\Models\Business;
use App\Models\GameLog;

class GameController extends BaseController
{
    public function index(): void
    {
        $player = $this->getPlayer();
        if (!$player) {
            $this->flash('error', 'Please select a player.');
            $this->redirect('/account');
        }

        $engine = new GameEngine($this->app->db);

        // Process the current turn
        $result = $engine->processTurn($player);

        if ($result['gameOver']) {
            $tierUpgrade = $result['tierUpgrade'] ?? null;
            $this->render('game/end_game', [
                'player'      => $player,
                'data'        => $result['messages'],
                'bankrupt'    => $result['bankrupt'],
                'tierUpgrade' => $tierUpgrade,
            ]);
            return;
        }

        // Reload player data after processing
        $playerModel = new \App\Models\Player($this->app->db);
        $player = $playerModel->findById($player->PlayerID);

        // Get full dashboard data
        $data = $engine->getDashboardData($player);
        $data['messages'] = $result['messages'];

        // Check for negative bank balance — force asset sale
        $data['forcedSale'] = false;
        if ($data['bankBalance'] < 0) {
            $data['forcedSale'] = true;
            $data['forcedSaleItems'] = $this->getForcedSaleOptions($player);
        }

        // Determine which tab to show
        $tab = $_GET['tab'] ?? $player->ShowTab ?? 'summary';
        $data['activeTab'] = $tab;

        // Update ShowTab in database
        if ($tab !== ($player->ShowTab ?? '')) {
            $playerModel->update($player->PlayerID, ['ShowTab' => $tab]);
        }

        $this->render('game/menu', $data);
    }

    /**
     * Get up to 3 sellable assets for forced sale when balance is negative.
     */
    private function getForcedSaleOptions(object $player): array
    {
        $playerId = $player->PlayerID;
        $items = [];

        // Owned properties
        $reModel = new RealEstate($this->app->db);
        foreach ($reModel->getOwned($playerId) as $prop) {
            $value = (float) $prop->CurrentValue;
            $items[] = [
                'type' => 'property',
                'name' => $prop->Description,
                'value' => $value,
                'saleProceeds' => round($value * 0.90, 2), // 10% realtor fee
                'id' => $prop->REID,
                'formAction' => url('/game/re/sell'),
                'fieldName' => 're_id',
            ];
        }

        // Owned toys
        $stockModel = new Stock($this->app->db);
        foreach ($stockModel->getOwned($playerId) as $toy) {
            $value = (float) $toy->StockPrice;
            $items[] = [
                'type' => 'toy',
                'name' => $toy->StockSymbol,
                'value' => $value,
                'saleProceeds' => round($value * 0.85, 2), // 15% commission
                'id' => $toy->StockID,
                'formAction' => url('/game/stock/sell'),
                'fieldName' => 'stock_id',
            ];
        }

        // Active investments
        $bizModel = new Business($this->app->db);
        foreach ($bizModel->getAll($playerId) as $biz) {
            $value = (float) ($biz->CurrentValue ?? 0);
            if ($value <= 0) continue;
            $items[] = [
                'type' => 'investment',
                'name' => $biz->ShortDescription,
                'value' => $value,
                'saleProceeds' => round($value * 0.95, 2), // 5% penalty
                'id' => $biz->BusinessID,
                'formAction' => url('/game/biz/liquidate'),
                'fieldName' => 'biz_id',
            ];
        }

        // Sort by sale proceeds descending (most valuable first)
        usort($items, fn($a, $b) => $b['saleProceeds'] <=> $a['saleProceeds']);

        return array_slice($items, 0, 3);
    }

    public function action(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/game');
            return;
        }

        $player = $this->getPlayer();
        if (!$player) {
            $this->redirect('/account');
        }

        $action = (int) ($_POST['action'] ?? $_GET['do'] ?? 0);
        if ($action === 0) {
            $this->redirect('/game');
        }

        $engine = new GameEngine($this->app->db);
        $engine->setAction($player, $action);
        $result = $engine->processTurnAction($player, $action);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } elseif (isset($result['title'])) {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=actions');
    }

    public function doAction(string $code): void
    {
        // These are plain links, so the original let any other site spend a
        // signed-in player's money with an <img src>. The links now carry the
        // session's CSRF token.
        $token = (string) ($_GET['t'] ?? '');
        if (!$this->app->session->verifyCsrf($token)) {
            $this->flash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/game?tab=actions');
        }

        $player = $this->getPlayer();
        if (!$player) {
            $this->redirect('/account');
        }

        $action = (int) $code;
        $engine = new GameEngine($this->app->db);
        $engine->setAction($player, $action);
        $result = $engine->processTurnAction($player, $action);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } elseif (isset($result['title'])) {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=actions');
    }

    // Property Actions (Mansions & Islands)
    public function propertyBuy(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/game?tab=realestate');
            return;
        }

        $player = $this->getPlayer();
        $reId = (int) ($_POST['re_id'] ?? 0);
        $offerPrice = (float) ($_POST['offer_price'] ?? 0);

        $propertyService = new LuxuryPropertyService($this->app->db);
        $result = $propertyService->makeOffer($player, $reId, $offerPrice);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } else {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=realestate');
    }

    public function propertySell(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/game?tab=realestate');
            return;
        }

        $player = $this->getPlayer();
        $reId = (int) ($_POST['re_id'] ?? 0);

        $propertyService = new LuxuryPropertyService($this->app->db);
        $result = $propertyService->sellProperty($player, $reId);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } else {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=realestate');
    }

    // Toy Actions (Supercars, Yachts, Jets, Art)
    public function toyBuy(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/game?tab=stocks');
            return;
        }

        $player = $this->getPlayer();
        $toyId = (int) ($_POST['stock_id'] ?? 0);

        $toyService = new ToyService($this->app->db);
        $result = $toyService->buyToy($player, $toyId);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } else {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=stocks');
    }

    public function toySell(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/game?tab=stocks');
            return;
        }

        $player = $this->getPlayer();
        $toyId = (int) ($_POST['stock_id'] ?? 0);

        $toyService = new ToyService($this->app->db);
        $result = $toyService->sellToy($player, $toyId);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } else {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=stocks');
    }

    // Investment Actions (Bad Investments)
    public function investmentMake(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/game?tab=businesses');
            return;
        }

        $player = $this->getPlayer();
        $dealId = (int) ($_POST['deal_id'] ?? 0);

        $investService = new InvestmentService($this->app->db);
        $result = $investService->makeInvestment($player, $dealId);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } else {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=businesses');
    }

    public function investmentLiquidate(): void
    {
        if (!$this->verifyCsrf()) {
            $this->redirect('/game?tab=businesses');
            return;
        }

        $player = $this->getPlayer();
        $bizId = (int) ($_POST['biz_id'] ?? 0);

        $investService = new InvestmentService($this->app->db);
        $result = $investService->liquidate($player, $bizId);

        if (isset($result['error'])) {
            $this->flash('error', $result['error']);
        } else {
            $this->app->session->set('game_message', $result);
        }

        $this->redirect('/game?tab=businesses');
    }

    // Chart Data API
    public function chartData(string $type): void
    {
        $player = $this->getPlayer();
        if (!$player) {
            $this->json(['error' => 'Not logged in'], 401);
            return;
        }

        $gameLog = new GameLog($this->app->db);

        switch ($type) {
            case 'networth':
                $history = $gameLog->getNetWorthHistory($player->PlayerID);
                $this->json([
                    'labels' => array_column($history, 'Turn'),
                    'netWorth' => array_column($history, 'NetWorth'),
                    'bankBalance' => array_column($history, 'BankBalance'),
                    'propertyNetWorth' => array_column($history, 'PropertyNetWorth'),
                    'toyNetWorth' => array_column($history, 'ToyNetWorth'),
                ]);
                break;

            case 'economy':
                $history = $gameLog->getNetWorthHistory($player->PlayerID);
                $this->json([
                    'labels' => array_column($history, 'Turn'),
                    'interestRate' => array_column($history, 'InterestRate'),
                    'inflationRate' => array_column($history, 'InflationRate'),
                ]);
                break;

            case 'bank':
                $bankService = new BankService($this->app->db);
                $history = $bankService->getBalanceHistory($player->PlayerID, 100);
                $this->json([
                    'labels' => array_reverse(array_column($history, 'Turn')),
                    'balance' => array_reverse(array_column($history, 'Balance')),
                ]);
                break;

            default:
                $this->json(['error' => 'Unknown chart type'], 400);
        }
    }
}
