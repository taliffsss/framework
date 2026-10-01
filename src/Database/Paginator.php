<?php

declare(strict_types=1);

namespace Naluz\Database;

use Naluz\Support\Collection;

final class Paginator implements \JsonSerializable, \IteratorAggregate, \Countable
{
    public function __construct(
        public readonly Collection $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function getIterator(): \Traversable
    {
        return $this->items->getIterator();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function jsonSerialize(): array
    {
        return [
            'data' => $this->items->toArray(),
            'meta' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage(),
            ],
        ];
    }
}
