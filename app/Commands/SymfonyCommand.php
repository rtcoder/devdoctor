<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Symfony\SymfonyAnalyzer;
use DevDoctor\Modules\Symfony\SymfonyOptions;
use LaravelZero\Framework\Commands\Command;

final class SymfonyCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'symfony
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check Symfony application env, runtime directories, recipes, and Composer scripts.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::SYMFONY, app(SymfonyAnalyzer::class)->analyze(new SymfonyOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
