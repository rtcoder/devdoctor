<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Laravel\LaravelAnalyzer;
use DevDoctor\Modules\Laravel\LaravelOptions;
use LaravelZero\Framework\Commands\Command;

final class LaravelCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'laravel
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}';

    protected $description = 'Check Laravel application health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult('laravel', app(LaravelAnalyzer::class)->analyze(new LaravelOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
