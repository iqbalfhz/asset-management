<?php

// REST API /api/v1 dipakai server-ke-server dengan token, bukan dari browser lintas situs.
// Jadi izin lintas asal dibatasi ke domain aplikasi sendiri, bukan "*" bawaan Laravel.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('APP_URL', 'http://localhost')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
