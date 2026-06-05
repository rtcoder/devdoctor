<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Composer\ComposerAnalyzer;
use DevDoctor\Modules\Composer\ComposerOptions;
use DevDoctor\Modules\Node\NodeAnalyzer;
use DevDoctor\Modules\Node\NodeOptions;
use LaravelZero\Framework\Commands\Command;

final class DepsCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'deps
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check Composer and Node dependency health.';

    public function handle(ComposerAnalyzer $composer, NodeAnalyzer $node): int
    {
        $path = (string) $this->option('path');
        $strict = (bool) $this->option('strict');

        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::COMPOSER, $composer->analyze(new ComposerOptions(
                path: $path,
                strict: $strict,
            ))),
            new ModuleResult(ModuleName::NODE, $node->analyze(new NodeOptions(
                path: $path,
                strict: $strict,
            ))),
        ]);
    }
}
