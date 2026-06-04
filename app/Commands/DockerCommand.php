<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Docker\DockerAnalyzer;
use DevDoctor\Modules\Docker\DockerOptions;
use LaravelZero\Framework\Commands\Command;

final class DockerCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'docker
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--compose-file= : Compose file to inspect}
        {--no-daemon : Skip Docker daemon checks}
        {--no-containers : Skip Compose container status checks}';

    protected $description = 'Check Docker and Docker Compose project health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult('docker', app(DockerAnalyzer::class)->analyze(new DockerOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
                composeFile: $this->option('compose-file') !== null ? (string) $this->option('compose-file') : null,
                daemon: ! (bool) $this->option('no-daemon'),
                containers: ! (bool) $this->option('no-containers'),
            ))),
        ]);
    }
}
