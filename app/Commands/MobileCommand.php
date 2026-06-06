<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Mobile\MobileAnalyzer;
use DevDoctor\Modules\Mobile\MobileOptions;
use LaravelZero\Framework\Commands\Command;

final class MobileCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'mobile
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check native Android and iOS project markers, wrappers, debug flags, and lockfiles.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::MOBILE, app(MobileAnalyzer::class)->analyze(new MobileOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
