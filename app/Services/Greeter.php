<?php

declare(strict_types=1);

namespace App\Services;

/** A tiny example service, bound in AppServiceProvider so any controller can type-hint it. */
final class Greeter
{
    public function __construct(private readonly string $appName = 'NaluzPHP')
    {
    }

    public function greet(string $name): string
    {
        return "Hello, {$name}! Welcome to {$this->appName}.";
    }
}
