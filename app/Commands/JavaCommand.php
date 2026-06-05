<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Java\JavaAnalyzer;
use DevDoctor\Modules\Java\JavaOptions;
use LaravelZero\Framework\Commands\Command;

final class JavaCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'java
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check Java/JVM Maven, Gradle, Ant, and Spring project health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::JAVA, app(JavaAnalyzer::class)->analyze(new JavaOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
