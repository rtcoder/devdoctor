<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Go\GoAnalyzer;
use DevDoctor\Modules\Go\GoOptions;
use LaravelZero\Framework\Commands\Command;

final class GoCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'go
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check Go module and workspace manifest health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::GO, app(GoAnalyzer::class)->analyze(new GoOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
