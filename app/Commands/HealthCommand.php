<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\DiagnosticModuleRunner;
use DevDoctor\Core\DiagnosticRunOptions;
use DevDoctor\Core\ExitCode;
use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\Severity;
use LaravelZero\Framework\Commands\Command;

final class HealthCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'health
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}
        {--modules= : Comma-separated modules to run}
        {--exclude= : Comma-separated modules to exclude}
        {--config=devdoctor.yml : DevDoctor config file name}
        {--include-ports : Include common local port checks}';

    protected $description = 'Run a broad local project health check.';

    public function handle(DiagnosticModuleRunner $runner): int
    {
        $modules = $this->selectedModules();
        $unknown = array_values(array_diff($modules, $runner->knownModules()));

        if ($unknown !== []) {
            return $this->renderDiagnostics([
                new ModuleResult(ModuleName::HEALTH, new IssueCollection([
                    new Issue(
                        code: IssueCode::DD_HEALTH_UNKNOWN_MODULE,
                        severity: Severity::ERROR,
                        message: 'Unknown health module: '.implode(', ', $unknown),
                        module: ModuleName::HEALTH,
                    ),
                ])),
            ], ExitCode::INVALID_CONFIG);
        }

        $results = [];
        $options = new DiagnosticRunOptions(
            path: (string) $this->option('path'),
            strict: (bool) $this->option('strict'),
            ci: (bool) $this->option('ci'),
            configFile: (string) $this->option('config'),
            portsCommon: true,
            gitRequireClean: false,
            gitRequireUpstream: false,
            gitScanSensitive: true,
            gitScanLargeFiles: true,
        );

        foreach ($modules as $module) {
            $result = $runner->run($module, $options);

            if ($result instanceof ModuleResult) {
                $results[] = $result;
            } else {
                return $this->renderDiagnostics($result['results'], $result['exitCode']);
            }
        }

        return $this->renderDiagnostics($results);
    }

    /**
     * @return list<string>
     */
    private function selectedModules(): array
    {
        $modules = $this->stringList((string) ($this->option('modules') ?: $this->defaultModules()));
        $exclude = $this->stringList((string) ($this->option('exclude') ?: ''));

        return array_values(array_diff($modules, $exclude));
    }

    private function defaultModules(): string
    {
        $modules = ['presets', 'env', 'cache', 'http', 'php', 'node', 'laravel', 'composer', 'db', 'queue', 'git', 'docker', 'security'];

        if ((bool) $this->option('include-ports')) {
            array_splice($modules, 8, 0, 'ports');
        }

        return implode(',', $modules);
    }

    /**
     * @return list<string>
     */
    private function stringList(string $value): array
    {
        return array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
