<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PostController;
use Naluz\Routing\Router;

/** @var Router $router  — mounted under /api, stateless, rate limited */
$router->get('/ping', fn () => ['status' => 'ok', 'framework' => 'NaluzPHP'])->name('ping');

$router->apiResource('posts', PostController::class);
