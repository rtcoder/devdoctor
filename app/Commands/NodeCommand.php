<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Node\NodeAnalyzer;
use DevDoctor\Modules\Node\NodeOptions;
use LaravelZero\Framework\Commands\Command;

final class NodeCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'node
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}';

    protected $description = 'Check Node.js project and package manager health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::NODE, app(NodeAnalyzer::class)->analyze(new NodeOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
