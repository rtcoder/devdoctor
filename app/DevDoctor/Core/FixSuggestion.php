<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final readonly class FixSuggestion
{
    public function __construct(
        public string $description,
        public ?string $command = null,
        public bool $destructive = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(Redactor $redactor): array
    {
        return array_filter([
            'description' => $redactor->redactText($this->description),
            'command' => $this->command === null ? null : $redactor->redactText($this->command),
            'destructive' => $this->destructive,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
