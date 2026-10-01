<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\StatusController;
use Naluz\GraphQL\GraphQLController;
use Naluz\Routing\Router;

/** @var Router $router  — mounted under /api, stateless, rate limited */
$router->get('/ping', [StatusController::class, 'ping'])->name('ping');

$router->apiResource('posts', PostController::class);

// GraphQL: POST (queries + mutations) or GET (queries only). See docs/graphql.md.
$router->match(['GET', 'POST'], '/graphql', [GraphQLController::class, 'handle'])->name('graphql')->middleware('jwt.optional');
