<?php

declare(strict_types=1);

namespace DevDoctor\Commands\Concerns;

use DevDoctor\Core\ExitCode;
use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\Output\JsonRenderer;
use DevDoctor\Core\Output\OutputFormat;
use DevDoctor\Core\Output\SarifRenderer;
use DevDoctor\Core\Output\TableRenderer;
use DevDoctor\Core\Severity;

trait RendersDiagnostics
{
    /**
     * @param  list<ModuleResult>  $results
     */
    protected function renderDiagnostics(array $results, ?ExitCode $overrideExitCode = null): int
    {
        $renderResults = $this->applyOutputOptions($results);
        $summaryOnly = (bool) ($this->option('summary-only') ?? false);
        $format = OutputFormat::tryFrom((string) $this->option('format'));

        if ($format === null) {
            $this->output->writeln('Invalid --format value. Expected "table", "json", or "sarif".');

            return ExitCode::INVALID_CONFIG->value;
        }

        $output = match ($format) {
            OutputFormat::JSON => app(JsonRenderer::class)->render($renderResults, $summaryOnly),
            OutputFormat::SARIF => app(SarifRenderer::class)->render($renderResults, $summaryOnly),
            OutputFormat::TABLE => app(TableRenderer::class)->render($renderResults, $summaryOnly),
        };

        $this->output->write($output);

        $exitCode = ExitCode::OK;

        foreach ($results as $result) {
            $resultExitCode = ExitCode::fromIssues($result->issues);

            if ($resultExitCode->value > $exitCode->value) {
                $exitCode = $resultExitCode;
            }
        }

        return ($overrideExitCode ?? $exitCode)->value;
    }

    /**
     * @param  list<ModuleResult>  $results
     * @return list<ModuleResult>
     */
    private function applyOutputOptions(array $results): array
    {
        $only = $this->onlySeverities((string) ($this->option('only') ?? ''));
        $noHints = (bool) ($this->option('no-hints') ?? false);

        if ($only === [] && ! $noHints) {
            return $results;
        }

        return array_map(function (ModuleResult $result) use ($only, $noHints): ModuleResult {
            $issues = array_filter(
                $result->issues->all(),
                static fn (Issue $issue): bool => $only === [] || in_array($issue->severity, $only, true),
            );

            if ($noHints) {
                $issues = array_map(static fn (Issue $issue): Issue => $issue->withoutHints(), $issues);
            }

            return new ModuleResult($result->name, new IssueCollection($issues));
        }, $results);
    }

    /**
     * @return list<Severity>
     */
    private function onlySeverities(string $value): array
    {
        $items = array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $value),
        ));

        $severities = [];

        foreach ($items as $item) {
            $severity = Severity::tryFrom($item);

            if ($severity !== null) {
                $severities[] = $severity;
            }
        }

        return array_values(array_unique($severities, SORT_REGULAR));
    }
}
