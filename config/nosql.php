<?php

declare(strict_types=1);

return [
    // file | memory | mongodb   (or any name below)
    'default' => env('NOSQL_CONNECTION', 'file'),

    'connections' => [
        // JSON files under storage/nosql: zero infrastructure, file-locked and atomic; fine for small/medium data
        'file' => ['driver' => 'file', 'path' => 'storage/nosql'],

        // in-process, not persisted (tests, prototyping)
        'memory' => ['driver' => 'memory'],

        // MongoDB: composer require mongodb/mongodb (needs ext-mongodb)
        'mongodb' => [
            'driver' => 'mongodb',
            'uri' => env('NOSQL_MONGODB_URI', 'mongodb://127.0.0.1:27017'),
            'database' => env('NOSQL_DATABASE', 'naluz'),
            'options' => [],
        ],
    ],
];
