<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Queue\QueueAnalyzer;
use DevDoctor\Modules\Queue\QueueOptions;
use LaravelZero\Framework\Commands\Command;

final class QueueCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'queue
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check queue environment configuration.';

    public function handle(QueueAnalyzer $analyzer): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::QUEUE, $analyzer->analyze(new QueueOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
