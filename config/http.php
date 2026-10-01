<?php

declare(strict_types=1);

return [
    'timeout' => 10,            // seconds, whole request
    'connect_timeout' => 5,
    'max_redirects' => 3,
    'user_agent' => env('APP_NAME', 'NaluzPHP'),
    // SSRF protection: requests to private / loopback / link-local / metadata addresses are refused.
    // Only enable for trusted internal integrations.
    'allow_private_networks' => false,
];
