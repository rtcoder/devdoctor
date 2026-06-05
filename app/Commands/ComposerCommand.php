<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Composer\ComposerAnalyzer;
use DevDoctor\Modules\Composer\ComposerOptions;
use LaravelZero\Framework\Commands\Command;

final class ComposerCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'composer
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}
        {--no-scripts : Skip Composer script inspection}
        {--no-platform-check : Skip local platform checks}
        {--no-validate : Skip composer validate}';

    protected $description = 'Check Composer project health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::COMPOSER, app(ComposerAnalyzer::class)->analyze(new ComposerOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
                validate: ! (bool) $this->option('no-validate'),
                platformCheck: ! (bool) $this->option('no-platform-check'),
                scripts: ! (bool) $this->option('no-scripts'),
            ))),
        ]);
    }
}
