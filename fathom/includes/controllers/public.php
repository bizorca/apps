<?php
/** PublicBoardController, PublicCardController, QrCodeController. No sign-in, no cookie. */

declare(strict_types=1);

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

function public_board(array $p): never
{
    $board = Board::firstWhere('share_token = ?', [$p['board']]) ?? abort(404);
    if (!$board->is_public) {
        abort(404);
    }
    foreach ($board->columns as $column) {
        $column->setRelation('cards', Card::where('column_id = ? AND closed_at IS NULL AND is_draft = 0', [$column->id], 'ORDER BY position'));
    }
    view('public.board', compact('board'));
}

function public_card(array $p): never
{
    $card = Card::firstWhere('share_token = ?', [$p['card']]) ?? abort(404);
    if (!$card->board?->is_public || $card->is_draft) {
        abort(404);
    }
    view('public.card', compact('card'));
}

function qr_code(array $p): never
{
    $id = $p['id'];
    $board = Board::firstWhere('share_token = ? AND is_public = 1', [$id]);
    $card  = $board ? null : Card::firstWhere(
        'share_token = ? AND board_id IN (SELECT id FROM fm_boards WHERE is_public = 1 AND deleted_at IS NULL)',
        [$id]
    );
    if (!$board && !$card) {
        abort(404);
    }
    $url = fm_absolute($board ? route('public.boards.show', $board->share_token) : route('public.cards.show', $card->share_token));
    $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
    header('Content-Type: image/svg+xml');
    echo $writer->writeString($url);
    throw new FmHalt();
}
