<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Cache\CacheAnalyzer;
use DevDoctor\Modules\Cache\CacheOptions;
use LaravelZero\Framework\Commands\Command;

final class CacheCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'cache
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}
        {--max-size=512 : Maximum expected cache directory size in MiB}';

    protected $description = 'Check framework and tool cache health.';

    public function handle(CacheAnalyzer $analyzer): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::CACHE, $analyzer->analyze(new CacheOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
                maxSizeMb: (int) $this->option('max-size'),
            ))),
        ]);
    }
}
