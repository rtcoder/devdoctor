<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Monorepo\MonorepoAnalyzer;
use DevDoctor\Modules\Monorepo\MonorepoOptions;
use LaravelZero\Framework\Commands\Command;

final class MonorepoCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'monorepo
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check monorepo tooling, workspace lockfiles, and root package scripts.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::MONOREPO, app(MonorepoAnalyzer::class)->analyze(new MonorepoOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
