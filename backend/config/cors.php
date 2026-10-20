<?php

/*
| Cross-Origin Resource Sharing
|
| The SPA is served from the same origin as the API (Vite proxy in development,
| one domain in production), so no cross-origin access is needed by default.
| Allow specific origins only through CORS_ALLOWED_ORIGINS (comma-separated,
| exact origins such as https://example.com). Never use "*".
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ), fn (string $origin) => $origin !== '' && $origin !== '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
