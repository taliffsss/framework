<?php

declare(strict_types=1);

return [
    // sync (run immediately) | database | redis
    'default' => env('QUEUE_CONNECTION', 'sync'),
    // Seconds before a job reserved by a crashed worker becomes available again. Keep it above your slowest job.
    'retry_after' => 90,
];
