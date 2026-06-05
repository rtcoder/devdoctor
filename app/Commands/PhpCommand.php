<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Php\PhpAnalyzer;
use DevDoctor\Modules\Php\PhpOptions;
use LaravelZero\Framework\Commands\Command;

final class PhpCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'php
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--minimum-memory=128 : Minimum expected PHP memory_limit in megabytes}';

    protected $description = 'Check PHP runtime and platform health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult('php', app(PhpAnalyzer::class)->analyze(new PhpOptions(
                path: (string) $this->option('path'),
                ci: (bool) $this->option('ci'),
                strict: (bool) $this->option('strict'),
                minimumMemoryMb: (int) $this->option('minimum-memory'),
            ))),
        ]);
    }
}
