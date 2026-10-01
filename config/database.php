<?php

declare(strict_types=1);

// Read / write splitting. Set DB_READ_HOST (one host or a comma-separated list of replicas) to send SELECTs to replicas;
// writes, transactions and anything that must see fresh data stay on DB_WRITE_HOST (default: DB_HOST).
// Leave DB_READ_HOST empty and nothing changes: one connection for everything.

$split = static function (): array {
    $readHosts = array_values(array_filter(array_map('trim', explode(',', (string) env('DB_READ_HOST', '')))));
    $config = [
        'sticky' => filter_var(env('DB_STICKY', true), FILTER_VALIDATE_BOOLEAN),           // read-your-writes after a write
        'read_fallback' => filter_var(env('DB_READ_FALLBACK', true), FILTER_VALIDATE_BOOLEAN), // replicas down -> use primary
        'read_strategy' => env('DB_READ_STRATEGY', 'random'),                               // random | ordered (failover order)
    ];
    if ($readHosts !== []) {
        $config['read'] = ['host' => $readHosts];
        foreach (['username' => 'DB_READ_USERNAME', 'password' => 'DB_READ_PASSWORD', 'port' => 'DB_READ_PORT'] as $key => $var) {
            if (($v = env($var)) !== null && $v !== '') {
                $config['read'][$key] = $v;
            }
        }
    }
    if (($write = trim((string) env('DB_WRITE_HOST', ''))) !== '') {
        $config['write'] = ['host' => $write];
    }
    return $config;
};

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => (static function (): string {
                $db = (string) env('DB_DATABASE', 'storage/database.sqlite');
                return $db === ':memory:' || str_starts_with($db, '/') ? $db : dirname(__DIR__) . '/' . $db;
            })(),
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'naluz'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ] + $split(),
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'naluz'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
        ] + $split(),
        // Microsoft SQL Server / Azure SQL (needs the pdo_sqlsrv extension + Microsoft ODBC driver)
        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 1433),
            'database' => env('DB_DATABASE', 'naluz'),
            'username' => env('DB_USERNAME', 'sa'),
            'password' => env('DB_PASSWORD', ''),
            'encrypt' => filter_var(env('DB_ENCRYPT', true), FILTER_VALIDATE_BOOLEAN),
            'trust_server_certificate' => filter_var(env('DB_TRUST_SERVER_CERTIFICATE', false), FILTER_VALIDATE_BOOLEAN),
        ] + $split(),
    ],
];
