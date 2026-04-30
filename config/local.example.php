<?php

declare(strict_types=1);

// Copy this file to config/local.php on the server and fill actual values.
// Do not commit config/local.php.
return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => 'your_db_name',
    'DB_USER' => 'your_db_user',
    'DB_PASS' => 'your_db_password',

    'ERP_BASE_URL' => 'https://erppro.lk/public',
    'ERP_CLIENT_ID' => 3,
    'ERP_CLIENT_SECRET' => 'your_client_secret',
    'ERP_USERNAME' => 'your_erp_username',
    'ERP_PASSWORD' => 'your_erp_password',
    'ERP_LOCATION_ID' => 5,
    'ERP_ORDER_ENDPOINT' => '/connector/api/sell',

    'SYNC_WEBHOOK_KEY' => 'change-me-strong-key',

    // Optional: Google Business Profile review integration.
    'GOOGLE_PLACE_ID' => '',
    'GOOGLE_PLACES_API_KEY' => '',
    'GOOGLE_REVIEWS_URL' => '',

    // Public site URL used for absolute links in emails and OAuth redirects.
    'SITE_URL'  => 'https://watercolor.lk',
    'SITE_NAME' => 'Watercolor.LK',

    // Outgoing email.
    'MAIL_FROM'       => 'no-reply@watercolor.lk',
    'MAIL_FROM_NAME'  => 'Watercolor.LK',
    'MAIL_REPLY_TO'   => 'support@watercolor.lk',
    'MAIL_TRANSPORT'  => 'smtp', // mail | smtp | log
    'SMTP_HOST'       => 'mail.example.com',
    'SMTP_PORT'       => 587,
    'SMTP_USER'       => '',
    'SMTP_PASS'       => '',
    'SMTP_ENCRYPTION' => 'tls', // tls | ssl | ''

    // Google Sign-In (OAuth Web application). Redirect URI must match Google Console exactly.
    'GOOGLE_OAUTH_CLIENT_ID'     => '',
    'GOOGLE_OAUTH_CLIENT_SECRET' => '',
    'GOOGLE_OAUTH_REDIRECT'      => 'https://watercolor.lk/account/google-callback.php',
];
