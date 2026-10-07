<?php
/*
 * Copy to private_html/.env.php on the server (mode 640) and fill in.
 * Never committed; deploy.sh never overwrites or deletes it.
 */
return [
    'DB_HOST'  => 'localhost',
    'DB_NAME'  => '',          // Cloudways: the app folder name
    'DB_USER'  => '',          // same as DB_NAME on Cloudways
    'DB_PASS'  => '',

    'SMTP2GO_API_KEY' => '',   // shared bizorca.com SMTP2GO account; empty = log to data/mail.log
    'MAIL_FROM'       => 'Bizorca Tools <tools@bizorca.com>',

    // Proforma
    'ANTHROPIC_API_KEY'     => '',   // Market Intel playbooks (Claude Haiku)
    'GOOGLE_PLACES_API_KEY' => '',   // optional geocoding
    'PF_CRON_SECRET'        => '',   // monthly digest: /proforma/cron/digest.php?secret=...
];
