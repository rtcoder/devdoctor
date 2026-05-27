<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\DevDoctor\Core\ExitCode;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Output\JsonRenderer;
use App\DevDoctor\Core\Output\TableRenderer;

trait RendersDiagnostics
{
    /**
     * @param  list<ModuleResult>  $results
     */
    protected function renderDiagnostics(array $results): int
    {
        $format = (string) $this->option('format');
        $output = $format === 'json'
            ? app(JsonRenderer::class)->render($results)
            : app(TableRenderer::class)->render($results);

        $this->output->write($output);

        $exitCode = ExitCode::OK;

        foreach ($results as $result) {
            $exitCode = max($exitCode, ExitCode::fromIssues($result->issues));
        }

        return $exitCode;
    }
}
