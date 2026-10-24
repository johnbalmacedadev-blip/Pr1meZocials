<?php

return [
    'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'redirect_uri' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/posting/facebook/callback'),
    'scopes' => [
        'pages_show_list',
        'pages_manage_posts',
        'pages_read_engagement',
    ],
];
