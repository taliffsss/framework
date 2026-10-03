<?php

declare(strict_types=1);

return [
    // Optional event streaming between services. memory (default; tests, local) | redis | rabbitmq | kafka
    'default' => env('MESSAGING_CONNECTION', 'memory'),

    // Consumer group: every group receives every event; instances sharing a group share the work.
    'group' => env('MESSAGING_GROUP', 'naluzphp'),

    // Topic (or `orders.*` pattern) => Subscriber classes that handle it (see `make:subscriber`).
    // Subscribers must be idempotent: delivery is at-least-once.
    'subscribers' => [
        // 'orders.placed' => [\App\Subscribers\SendReceipt::class],
    ],

    // Sign every message with HMAC-SHA256 and reject unsigned or tampered ones. Set the same key on every service
    // that publishes or consumes. Rotate by moving the old key to `previous_signing_keys`.
    'signing_key' => env('MESSAGING_SIGNING_KEY', ''),
    'previous_signing_keys' => [],

    // Largest accepted message in bytes.
    'max_bytes' => 1_048_576,

    'connections' => [
        'memory' => ['driver' => 'memory'],

        // Redis Streams (Redis 6.2+). Uses config/redis.php unless you set `host` here.
        'redis' => [
            'driver' => 'redis',
            'prefix' => 'naluz:stream:',
            'max_length' => 100_000,       // approximate cap per stream (older entries are trimmed)
            'visibility_timeout' => 60,    // seconds before a message a crashed consumer never acked is re-claimed
            'start_id' => '0',             // '0' = a new group replays the stream, '$' = only new messages
        ],

        // RabbitMQ: composer require php-amqplib/php-amqplib
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => (int) env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'ssl' => false,
            'exchange' => 'naluz.events',
            'prefetch' => 1,
        ],

        // Apache Kafka: needs ext-rdkafka. `options` are raw librdkafka settings (security.protocol, sasl.*, ssl.*).
        'kafka' => [
            'driver' => 'kafka',
            'brokers' => env('KAFKA_BROKERS', '127.0.0.1:9092'),
            'offset_reset' => 'earliest',
            'options' => [],
        ],
    ],
];
