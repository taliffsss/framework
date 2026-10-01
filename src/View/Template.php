<?php

declare(strict_types=1);

namespace Naluz\View;

/** The `$this` inside a template file. */
final class Template
{
    private ?string $layout = null;
    private array $layoutData = [];
    /** @var array<string,string> */
    private array $sections = [];
    /** @var list<string> */
    private array $stack = [];

    public function __construct(private readonly Factory $factory, private readonly string $file)
    {
    }

    public function render(array $data): string
    {
        if (!is_file($this->file)) {
            throw new \InvalidArgumentException('View not found: ' . basename($this->file));
        }
        $content = $this->capture($this->file, $data);
        if ($this->layout !== null) {
            $layout = new self($this->factory, $this->factory->file($this->layout));
            $layout->sections = $this->sections + ['content' => $content];
            return $layout->render($this->layoutData + $data);
        }
        return $content;
    }

    private function capture(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public function e(mixed $value): string
    {
        return e($value);
    }

    public function extend(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function section(string $name): void
    {
        $this->stack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->stack) ?? throw new \LogicException('endSection() without section().');
        $this->sections[$name] = (string) ob_get_clean();
    }

    /** Output a section (unescaped: sections are rendered template output). */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function include(string $name, array $data = []): string
    {
        return $this->factory->render($name, $data);
    }
}
