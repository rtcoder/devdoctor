<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Database\DatabaseAnalyzer;
use DevDoctor\Modules\Database\DatabaseOptions;
use LaravelZero\Framework\Commands\Command;

final class DatabaseCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'db
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--connect : Attempt a read-only PDO connection check}';

    protected $description = 'Check database environment configuration.';

    public function handle(DatabaseAnalyzer $analyzer): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::DATABASE, $analyzer->analyze(new DatabaseOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
                connect: (bool) $this->option('connect'),
            ))),
        ]);
    }
}
