<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface;

final class HomeController
{
    public function index(): ResponseInterface
    {
        return view('home', ['name' => config('app.name')]);
    }
}
