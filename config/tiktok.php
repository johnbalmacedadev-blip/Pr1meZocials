<?php

return [
    'client_key' => env('TIKTOK_CLIENT_KEY'),
    'client_secret' => env('TIKTOK_CLIENT_SECRET'),
    'redirect_uri' => env('TIKTOK_REDIRECT_URI', env('APP_URL').'/posting/tiktok/callback'),
    'scopes' => [
        'user.info.basic',
        'video.publish',
    ],
];
