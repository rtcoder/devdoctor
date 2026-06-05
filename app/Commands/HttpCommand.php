<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Http\HttpAnalyzer;
use DevDoctor\Modules\Http\HttpOptions;
use LaravelZero\Framework\Commands\Command;

final class HttpCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'http
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}
        {--url=* : Explicit URL target to validate}';

    protected $description = 'Check HTTP URL configuration.';

    public function handle(HttpAnalyzer $analyzer): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::HTTP, $analyzer->analyze(new HttpOptions(
                path: (string) $this->option('path'),
                urls: array_values(array_map('strval', (array) $this->option('url'))),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
