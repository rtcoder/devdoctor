<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Presets\PresetsAnalyzer;
use LaravelZero\Framework\Commands\Command;

final class PresetsCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'presets
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}';

    protected $description = 'Detect supported project framework and tooling presets.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::PRESETS, app(PresetsAnalyzer::class)->analyze((string) $this->option('path'))),
        ]);
    }
}
