<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final readonly class ModuleResult
{
    public function __construct(
        public ModuleName $name,
        public IssueCollection $issues,
    ) {
    }

    public function status(): ModuleStatus
    {
        return ModuleStatus::fromSummary($this->issues->summary());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name->value,
            'status' => $this->status()->value,
            'summary' => $this->issues->summary(),
            'issues' => array_map(
                static fn (Issue $issue): array => $issue->toArray(),
                $this->issues->all(),
            ),
        ];
    }
}
