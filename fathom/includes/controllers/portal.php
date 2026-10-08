<?php
/**
 * Client portal: PortalSessionController, PortalDashboardController,
 * PortalCardController, PortalStepController, PortalCommentController.
 *
 * Clients are not tools-account users. They are the board owner's clients,
 * identified by email within one Fathom account, signing in by a 15-minute
 * magic link; their session key is fm_client_id.
 */

declare(strict_types=1);

function fm_client_card(string $id): Card
{
    $client = fm_client();
    if (!$client->hasCard($id)) {
        abort(403, 'You do not have access to this card.');
    }
    return Card::find($id) ?? abort(404);
}

function portal_login(array $p): never
{
    view('portal.login');
}

function portal_login_store(array $p): never
{
    $v = validate(['email_address' => 'required|email']);
    $key = 'portal-magic-link:' . FmRequest::ip();
    if (fm_too_many($key, 10)) {
        back(['_errors' => ['email_address' => ['Too many sign-in attempts. Please wait a moment before trying again.']]]);
    }
    fm_hit($key, 60);

    // Every client row with that address (a client can be on several
    // accounts). The original sent one link for the first match only.
    $email = strtolower(trim($v['email_address']));
    foreach (Client::where('email_address = ?', [$email]) as $client) {
        $link = MagicLink::createForClient($client);
        $url  = fm_absolute(route('magic_links.show', $link->token));
        fm_mail(
            $client->email_address,
            'Sign in to Fathom',
            "Hi {$client->name},\n\nUse this link to sign in to Fathom. It expires in "
            . FM_MAGIC_LINK_MINUTES . " minutes:\n\n{$url}\n\n"
            . "If you didn't request this, you can safely ignore this email.\n"
        );
    }
    back(['success' => "If that email is registered, you'll receive a sign-in link shortly."]);
}

function portal_logout(array $p): never
{
    if (tl_session()) {
        unset($_SESSION['fm_client_id']);
        session_regenerate_id(true);
    }
    redirect_to(route('portal.login'));
}

function portal_dashboard(array $p): never
{
    $client = fm_client();
    $cards  = $client->cards->groupBy(fn($card) => $card->board?->name ?? 'Unknown Board');
    view('portal.dashboard', compact('client', 'cards'));
}

function portal_card(array $p): never
{
    $card   = fm_client_card($p['card']);
    $client = fm_client();
    view('portal.card', compact('card', 'client'));
}

function fm_portal_step(Card $card, string $id): Step
{
    $step = Step::find($id) ?? abort(404);
    if ($step->card_id !== $card->id) {
        abort(403);
    }
    return $step;
}

/**
 * A client ticking a step. The original set only `completed`, leaving
 * completed_at empty; the timestamp is recorded now (no member did it, so
 * completed_by stays empty).
 */
function portal_step_complete(array $p): never
{
    $card = fm_client_card($p['card']);
    fm_portal_step($card, $p['step'])->update(['completed' => 1, 'completed_at' => now_sql()]);
    flash('success', 'Step marked complete.');
    redirect_to(route('portal.cards.show', $card));
}

function portal_step_incomplete(array $p): never
{
    $card = fm_client_card($p['card']);
    fm_portal_step($card, $p['step'])->update(['completed' => 0, 'completed_at' => null, 'completed_by' => null]);
    flash('success', 'Step marked incomplete.');
    redirect_to(route('portal.cards.show', $card));
}

function portal_comment_store(array $p): never
{
    $card = fm_client_card($p['card']);
    $v = validate(['body' => 'required|string|max:10000']);
    Comment::create(['card_id' => $card->id, 'client_id' => fm_client()->id, 'body' => $v['body']]);
    flash('success', 'Comment added.');
    redirect_to(route('portal.cards.show', $card));
}
