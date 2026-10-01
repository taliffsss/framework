<?php

declare(strict_types=1);

namespace Naluz\View;

/**
 * Plain-PHP template engine with layouts and sections. Output is NOT auto-escaped:
 * wrap every dynamic value in `e()` / `$this->e()`.
 */
final class Factory
{
    /** @var array<string,mixed> */
    private array $shared = [];

    public function __construct(private readonly string $path)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function exists(string $name): bool
    {
        return is_file($this->file($name));
    }

    public function render(string $name, array $data = []): string
    {
        return (new Template($this, $this->file($name)))->render($data + $this->shared);
    }

    public function file(string $name): string
    {
        // Template names come from code, but guard anyway: no traversal, no stream wrappers.
        if (!preg_match('#^[A-Za-z0-9_\-./]+$#', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException("Invalid view name [{$name}].");
        }
        return rtrim($this->path, '/\\') . '/' . str_replace('.', '/', preg_replace('/\.php$/', '', $name) ?? $name) . '.php';
    }
}
