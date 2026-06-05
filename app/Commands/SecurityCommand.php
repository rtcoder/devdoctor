<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Security\SecurityAnalyzer;
use DevDoctor\Modules\Security\SecurityOptions;
use LaravelZero\Framework\Commands\Command;

final class SecurityCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'security
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}';

    protected $description = 'Check project security posture.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::SECURITY, app(SecurityAnalyzer::class)->analyze(new SecurityOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
            ))),
        ]);
    }
}
