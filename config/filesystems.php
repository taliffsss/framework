<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        // private files: never reachable from the web
        'local' => ['driver' => 'local', 'root' => 'storage/app'],
        // web-accessible uploads (symlink or serve public/storage): URL is built from `url`
        'public' => ['driver' => 'local', 'root' => 'public/storage', 'url' => '/storage'],
    ],
];
