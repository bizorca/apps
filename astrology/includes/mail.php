<?php
/**
 * Email. The only mail Astrology still sends is the lead notification from
 * "Request a spot" on work-with-jillian.php: verification and password-reset
 * mail belong to the shared /account pages now. Sent through the shared
 * tl_mail() (SMTP2GO, plain text), with Reply-To set to the person.
 */

require_once TL_PRIVATE . '/includes/mailer.php';

function sendOfferInterestEmail(string $offerName, array $user, ?array $lineage = null): bool {
    // PLACEHOLDER recipient in the original too — swap AS_LEAD_EMAIL for
    // Jillian's address when she takes over lead follow-up.
    $to = ADMIN_EMAIL;

    $lineageText = '';
    if ($lineage && !empty($lineage['primary_lineage'])) {
        $lineageText = ucfirst($lineage['primary_lineage']);
        if (!empty($lineage['secondary_lineage'])) {
            $lineageText .= ' / ' . ucfirst($lineage['secondary_lineage']);
        }
    }

    $text = "New offer request\n\n"
          . "Someone just clicked \"Request a Spot\" on the Work with Jillian page.\n\n"
          . "Offer:   {$offerName}\n"
          . "Name:    {$user['name']}\n"
          . "Email:   {$user['email']}\n"
          . ($lineageText !== '' ? "Lineage: {$lineageText}\n" : '')
          . "\nAll leads: " . APP_URL . url('/admin-interest.php') . "\n\n"
          . "They were told you'll reach out personally to find the right fit and timing.\n";

    return tl_mail($to, 'New lead: ' . $offerName, $text, (string) $user['email']);
}
