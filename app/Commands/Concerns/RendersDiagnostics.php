<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\DevDoctor\Core\ExitCode;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Output\JsonRenderer;
use App\DevDoctor\Core\Output\OutputFormat;
use App\DevDoctor\Core\Output\SarifRenderer;
use App\DevDoctor\Core\Output\TableRenderer;

trait RendersDiagnostics
{
    /**
     * @param  list<ModuleResult>  $results
     */
    protected function renderDiagnostics(array $results, ?ExitCode $overrideExitCode = null): int
    {
        $format = OutputFormat::tryFrom((string) $this->option('format'));

        if ($format === null) {
            $this->output->writeln('Invalid --format value. Expected "table", "json", or "sarif".');

            return ExitCode::INVALID_CONFIG->value;
        }

        $output = match ($format) {
            OutputFormat::JSON => app(JsonRenderer::class)->render($results),
            OutputFormat::SARIF => app(SarifRenderer::class)->render($results),
            OutputFormat::TABLE => app(TableRenderer::class)->render($results),
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
}
