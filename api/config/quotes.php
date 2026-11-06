<?php

return [
    'cache_enabled' => env('QUOTE_CACHE_ENABLED', false),
    'cache_store' => env('QUOTE_CACHE_STORE', 'quote_redis'),
    'cache_ttl' => 3600,
];
