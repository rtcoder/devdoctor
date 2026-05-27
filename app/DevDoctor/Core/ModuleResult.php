<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

final readonly class ModuleResult
{
    public function __construct(
        public string $name,
        public IssueCollection $issues,
    ) {}

    public function status(): string
    {
        if ($this->issues->hasErrors()) {
            return 'failed';
        }

        if ($this->issues->hasWarnings()) {
            return 'warning';
        }

        return 'passed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status(),
            'summary' => $this->issues->summary(),
            'issues' => array_map(
                static fn (Issue $issue): array => $issue->toArray(),
                $this->issues->all(),
            ),
        ];
    }
}
