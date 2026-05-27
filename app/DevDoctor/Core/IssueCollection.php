<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

final class IssueCollection
{
    /** @var list<Issue> */
    private array $issues = [];

    /**
     * @param iterable<Issue> $issues
     */
    public function __construct(iterable $issues = [])
    {
        foreach ($issues as $issue) {
            $this->add($issue);
        }
    }

    public function add(Issue $issue): void
    {
        $this->issues[] = $issue;
    }

    /**
     * @return list<Issue>
     */
    public function all(): array
    {
        $issues = $this->issues;

        usort($issues, static function (Issue $left, Issue $right): int {
            return [
                    $left->severity->rank(),
                    $left->module ?? '',
                    $left->file ?? '',
                    $left->line ?? 0,
                    $left->code,
                    $left->key ?? '',
                ] <=> [
                    $right->severity->rank(),
                    $right->module ?? '',
                    $right->file ?? '',
                    $right->line ?? 0,
                    $right->code,
                    $right->key ?? '',
                ];
        });

        return $issues;
    }

    public function hasErrors(): bool
    {
        return $this->count(Severity::ERROR) > 0;
    }

    public function hasWarnings(): bool
    {
        return $this->count(Severity::WARNING) > 0;
    }

    public function count(Severity $severity): int
    {
        return count(array_filter(
            $this->issues,
            static fn(Issue $issue): bool => $issue->severity === $severity,
        ));
    }

    /**
     * @return array{errors: int, warnings: int, info: int}
     */
    public function summary(): array
    {
        return [
            'errors' => $this->count(Severity::ERROR),
            'warnings' => $this->count(Severity::WARNING),
            'info' => $this->count(Severity::INFO),
        ];
    }
}
