<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\RendersDiagnostics;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Modules\Ports\PortsAnalyzer;
use App\DevDoctor\Modules\Ports\PortsOptions;
use LaravelZero\Framework\Commands\Command;

final class PortsCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'ports
        {--path=. : Project path to inspect}
        {--port=* : Specific port to inspect}
        {--ports= : Comma-separated ports to inspect}
        {--common : Check built-in common development ports}
        {--include-docker : Attempt to correlate Docker port usage when available}
        {--format=table : Output format: table or json}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}';

    protected $description = 'Check local development port conflicts.';

    public function handle(): int
    {
        $ports = array_merge(
            (array) $this->option('port'),
            $this->portsFromCsv((string) $this->option('ports')),
        );

        return $this->renderDiagnostics([
            new ModuleResult('ports', app(PortsAnalyzer::class)->analyze(new PortsOptions(
                path: (string) $this->option('path'),
                ports: $ports,
                common: (bool) $this->option('common'),
                strict: (bool) $this->option('strict'),
                includeDocker: (bool) $this->option('include-docker'),
            ))),
        ]);
    }

    /**
     * @return list<string>
     */
    private function portsFromCsv(string $ports): array
    {
        if ($ports === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $port): string => trim($port),
            explode(',', $ports),
        )));
    }
}
