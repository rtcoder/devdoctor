<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Frontend\FrontendAnalyzer;
use DevDoctor\Modules\Frontend\FrontendOptions;
use LaravelZero\Framework\Commands\Command;

final class FrontendCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'frontend
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check frontend project presets and build readiness.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::FRONTEND, app(FrontendAnalyzer::class)->analyze(new FrontendOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
