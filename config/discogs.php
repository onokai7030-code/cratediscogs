<?php

return [
    'base_url' => 'https://api.discogs.com',
    'token' => env('DISCOGS_TOKEN'),
    'user_agent' => env('DISCOGS_USER_AGENT', 'Crate/0.1'),
    'cache_days' => (int) env('DISCOGS_CACHE_DAYS', 7),
    'max_results' => (int) env('DISCOGS_MAX_RESULTS', 1000),
];
