<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

final class StatusController
{
    public function ping(): array
    {
        return ['status' => 'ok', 'framework' => 'NaluzPHP'];
    }
}
