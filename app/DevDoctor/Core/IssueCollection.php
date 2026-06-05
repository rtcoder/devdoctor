<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class IssueCollection
{
    /** @var list<Issue> */
    private array $issues = [];

    /**
     * @param  iterable<Issue>  $issues
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
                $left->module?->value ?? '',
                $left->file ?? '',
                $left->line ?? 0,
                $left->code->value,
                $left->key ?? '',
            ] <=> [
                $right->severity->rank(),
                $right->module?->value ?? '',
                $right->file ?? '',
                $right->line ?? 0,
                $right->code->value,
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

    public function isEmpty(): bool
    {
        return $this->issues === [];
    }

    public function count(Severity $severity): int
    {
        return count(array_filter(
            $this->issues,
            static fn (Issue $issue): bool => $issue->severity === $severity && ! $issue->suppressed,
        ));
    }

    /**
     * @return array{errors: int, warnings: int, info: int, suppressed: int}
     */
    public function summary(): array
    {
        return [
            'errors' => $this->count(Severity::ERROR),
            'warnings' => $this->count(Severity::WARNING),
            'info' => $this->count(Severity::INFO),
            'suppressed' => count(array_filter(
                $this->issues,
                static fn (Issue $issue): bool => $issue->suppressed,
            )),
        ];
    }
}
