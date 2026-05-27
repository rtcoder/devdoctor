<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\RendersDiagnostics;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Severity;
use LaravelZero\Framework\Commands\Command;

final class PortsCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'ports
        {--path=. : Project path to inspect}
        {--port=* : Specific port to inspect}
        {--format=table : Output format: table or json}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}';

    protected $description = 'Check local development port conflicts.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult('ports', new IssueCollection([
                new Issue('DD_PORTS_NOT_IMPLEMENTED', Severity::WARNING, 'Port diagnostics are not implemented yet.', 'ports'),
            ])),
        ]);
    }
}
