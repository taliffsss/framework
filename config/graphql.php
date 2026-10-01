<?php

declare(strict_types=1);

return [
    /** A class with `public static function build(): Naluz\GraphQL\Schema` (or a Closure returning a Schema). */
    'schema' => App\GraphQL\AppSchema::class,

    /** Limits that stop one request from doing unbounded work. */
    'max_depth' => (int) env('GRAPHQL_MAX_DEPTH', 10),
    'max_nodes' => (int) env('GRAPHQL_MAX_NODES', 500),
    'max_query_length' => (int) env('GRAPHQL_MAX_QUERY_LENGTH', 20000),

    /** Schema introspection (`__schema`): null = enabled only when APP_DEBUG is on. Tools like GraphiQL need it. */
    'introspection' => env('GRAPHQL_INTROSPECTION') === null ? null : filter_var(env('GRAPHQL_INTROSPECTION'), FILTER_VALIDATE_BOOLEAN),
];
