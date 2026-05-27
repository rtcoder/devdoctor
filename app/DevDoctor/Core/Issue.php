<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

final readonly class Issue
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string   $code,
        public Severity $severity,
        public string   $message,
        public ?string  $module = null,
        public ?string  $file = null,
        public ?int     $line = null,
        public ?string  $key = null,
        public array    $context = [],
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'module' => $this->module,
            'file' => $this->file,
            'line' => $this->line,
            'key' => $this->key,
            'context' => $this->context,
        ], static fn(mixed $value): bool => $value !== null && $value !== []);
    }
}
