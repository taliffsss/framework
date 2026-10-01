<?php

declare(strict_types=1);

namespace Naluz\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/** PSR-3 logger writing one file per day, with {placeholder} interpolation. */
final class FileLogger extends AbstractLogger
{
    private const LEVELS = [
        LogLevel::DEBUG => 0, LogLevel::INFO => 1, LogLevel::NOTICE => 2, LogLevel::WARNING => 3,
        LogLevel::ERROR => 4, LogLevel::CRITICAL => 5, LogLevel::ALERT => 6, LogLevel::EMERGENCY => 7,
    ];

    public function __construct(private readonly string $directory, private readonly string $minLevel = LogLevel::DEBUG)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException("Unknown log level [{$level}].");
        }
        if (self::LEVELS[$level] < self::LEVELS[$this->minLevel]) {
            return;
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            return; // logging must never take the app down
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->interpolate((string) $message, $context),
            isset($context['exception']) && $context['exception'] instanceof \Throwable
                ? ' ' . $this->describe($context['exception']) : ''
        );
        @file_put_contents($this->directory . '/naluz-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable || $value === null) {
                // strip newlines so user-controlled values cannot forge log lines
                $replace['{' . $key . '}'] = str_replace(["\r", "\n"], ' ', (string) $value);
            }
        }
        return strtr(str_replace(["\r", "\n"], ' ', $message), $replace);
    }

    private function describe(\Throwable $e): string
    {
        return sprintf('(%s: %s at %s:%d)', $e::class, str_replace("\n", ' ', $e->getMessage()), $e->getFile(), $e->getLine());
    }
}
