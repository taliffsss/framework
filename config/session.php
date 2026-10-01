<?php

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'cookie' => 'naluz_session',
    'lifetime' => 7200,
    // null = Secure flag is set automatically on HTTPS requests; force with SESSION_SECURE=true.
    'secure' => ($v = env('SESSION_SECURE')) === null || $v === '' ? null : (bool) $v,
    'same_site' => 'Lax',
];
