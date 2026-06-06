<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Flutter\FlutterAnalyzer;
use DevDoctor\Modules\Flutter\FlutterOptions;
use LaravelZero\Framework\Commands\Command;

final class FlutterCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'flutter
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check Flutter and Dart pubspec, lockfile, SDK constraints, dependency sources, and platform markers.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::FLUTTER, app(FlutterAnalyzer::class)->analyze(new FlutterOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
