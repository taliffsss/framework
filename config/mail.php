<?php

declare(strict_types=1);

return [
    // log (write to storage/logs, development) | smtp | array (tests)
    'default' => env('MAIL_MAILER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'NaluzPHP')),
    ],

    'smtp' => [
        'host' => env('MAIL_HOST', '127.0.0.1'),
        'port' => (int) env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),   // tls (STARTTLS) | ssl | none
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'timeout' => 10,
    ],
];
