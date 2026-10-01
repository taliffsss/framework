<?php

declare(strict_types=1);

return [
    // Override or disable (null) any default security header.
    'headers' => [
        // 'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:",
    ],

    'cors' => [
        'allowed_origins' => [],           // e.g. ['https://app.example.com']; empty disables CORS
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'supports_credentials' => false,
        'max_age' => 600,
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'issuer' => env('APP_URL'),
        'leeway' => 10,
    ],
];
