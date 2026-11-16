<?php

return [
    // Links shown on the registration form. Point these at the pages on the main website.
    'terms_url' => env('PORTAL_TERMS_URL', 'https://www.daisydigitalagency.com/terms'),
    'privacy_url' => env('PORTAL_PRIVACY_URL', 'https://www.daisydigitalagency.com/privacy'),

    // Main marketing website, used for "back to website" links.
    'website_url' => env('PORTAL_WEBSITE_URL', 'https://www.daisydigitalagency.com'),
];
