<?php

declare(strict_types=1);

namespace DevDoctor\Core\Output;

use DevDoctor\Core\Issue;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\Redactor;
use DevDoctor\Core\Severity;

final class TableRenderer
{
    /**
     * @param  list<ModuleResult>  $results
     */
    public function render(array $results): string
    {
        $redactor = new Redactor;
        $lines = ['DevDoctor', ''];
        $lines[] = sprintf('%-10s %-8s %6s %8s %5s %10s', 'Module', 'Status', 'Errors', 'Warnings', 'Info', 'Suppressed');

        foreach ($results as $result) {
            $summary = $result->issues->summary();
            $lines[] = sprintf(
                '%-10s %-8s %6d %8d %5d %10d',
                $result->name->value,
                $result->status()->value,
                $summary['errors'],
                $summary['warnings'],
                $summary['info'],
                $summary['suppressed'],
            );
        }

        foreach ([[Severity::ERROR, 'Errors'], [Severity::WARNING, 'Warnings'], [Severity::INFO, 'Info']] as [$severity, $heading]) {
            $issues = $this->issuesWithSeverity($results, $severity);

            if ($issues === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = $heading;

            foreach ($issues as $issue) {
                $lines[] = '  '.$this->formatIssue($issue);

                if ($issue->hint !== null) {
                    $lines[] = '    Hint: '.$redactor->redactText($issue->hint);
                }

                if ($issue->fix !== null) {
                    $lines[] = '    Fix: '.$redactor->redactText($issue->fix->description);

                    if ($issue->fix->command !== null) {
                        $lines[] = '    Suggested command: '.$redactor->redactText($issue->fix->command);
                    }
                }
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param  list<ModuleResult>  $results
     * @return list<Issue>
     */
    private function issuesWithSeverity(array $results, Severity $severity): array
    {
        $issues = [];

        foreach ($results as $result) {
            foreach ($result->issues->all() as $issue) {
                if ($issue->severity === $severity) {
                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    private function formatIssue(Issue $issue): string
    {
        $location = $issue->file ?? '';

        if ($issue->line !== null) {
            $location .= ':'.$issue->line;
        }

        if ($issue->key !== null) {
            $location .= ($location === '' ? '' : ' ').$issue->key;
        }

        $prefix = $location === '' ? '' : $location.' ';

        return sprintf('[%s]%s %s%s', $issue->code->value, $issue->suppressed ? ' [suppressed]' : '', $prefix, $issue->message);
    }
}
