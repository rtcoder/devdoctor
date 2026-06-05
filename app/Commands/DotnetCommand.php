<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Dotnet\DotnetAnalyzer;
use DevDoctor\Modules\Dotnet\DotnetOptions;
use LaravelZero\Framework\Commands\Command;

final class DotnetCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'dotnet
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check .NET solution, project, SDK, lockfile, and NuGet health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::DOTNET, app(DotnetAnalyzer::class)->analyze(new DotnetOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
