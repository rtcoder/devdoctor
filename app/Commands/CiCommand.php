<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\RendersDiagnostics;
use App\DevDoctor\Core\Config\ConfigLoader;
use App\DevDoctor\Core\Config\InvalidDevDoctorConfig;
use App\DevDoctor\Core\ExitCode;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\PathResolver;
use App\DevDoctor\Core\Severity;
use App\DevDoctor\Modules\Composer\ComposerAnalyzer;
use App\DevDoctor\Modules\Composer\ComposerOptions;
use App\DevDoctor\Modules\Docker\DockerAnalyzer;
use App\DevDoctor\Modules\Docker\DockerOptions;
use App\DevDoctor\Modules\Env\EnvAnalysisOptions;
use App\DevDoctor\Modules\Env\EnvAnalyzer;
use App\DevDoctor\Modules\Git\GitAnalyzer;
use App\DevDoctor\Modules\Git\GitOptions;
use App\DevDoctor\Modules\Ports\PortsAnalyzer;
use App\DevDoctor\Modules\Ports\PortsOptions;
use LaravelZero\Framework\Commands\Command;

final class CiCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'ci
        {--path=. : Project path to inspect}
        {--format=table : Output format: table or json}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--modules= : Comma-separated modules to run}
        {--exclude= : Comma-separated modules to exclude}
        {--fail-on-warnings : Return a non-zero exit code for warnings}
        {--no-fail-on-warnings : Return zero when diagnostics only contain warnings}
        {--config=devdoctor.yml : DevDoctor config file name}';

    protected $description = 'Run CI-safe DevDoctor diagnostics.';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        $modules = $this->selectedModules();
        $unknown = array_values(array_diff($modules, $this->knownModules()));

        if ($unknown !== []) {
            return $this->renderDiagnostics([
                new ModuleResult('ci', new IssueCollection([
                    new Issue(
                        code: 'DD_CI_UNKNOWN_MODULE',
                        severity: Severity::ERROR,
                        message: 'Unknown CI module: '.implode(', ', $unknown),
                        module: 'ci',
                    ),
                ])),
            ], ExitCode::INVALID_CONFIG);
        }

        $results = [];

        foreach ($modules as $module) {
            $result = $this->runModule($module, $path);

            if ($result instanceof ModuleResult) {
                $results[] = $result;
            } else {
                return $this->renderDiagnostics($result['results'], $result['exitCode']);
            }
        }

        return $this->renderDiagnostics(
            $results,
            $this->failOnWarnings() || $this->hasErrors($results) ? null : ExitCode::OK,
        );
    }

    /**
     * @return list<string>
     */
    private function selectedModules(): array
    {
        $modules = $this->stringList((string) ($this->option('modules') ?: 'env,composer,git,docker'));
        $exclude = $this->stringList((string) ($this->option('exclude') ?: ''));

        return array_values(array_diff($modules, $exclude));
    }

    /**
     * @return list<string>
     */
    private function knownModules(): array
    {
        return ['env', 'composer', 'git', 'docker', 'ports'];
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

    /**
     * @return ModuleResult|array{results: list<ModuleResult>, exitCode: ExitCode}
     */
    private function runModule(string $module, string $path): ModuleResult|array
    {
        return match ($module) {
            'env' => $this->runEnv($path),
            'composer' => new ModuleResult('composer', app(ComposerAnalyzer::class)->analyze(new ComposerOptions(
                path: $path,
                strict: (bool) $this->option('strict'),
            ))),
            'git' => new ModuleResult('git', app(GitAnalyzer::class)->analyze(new GitOptions(
                path: $path,
                strict: (bool) $this->option('strict'),
                requireClean: true,
                requireUpstream: false,
                scanSensitive: true,
                scanLargeFiles: true,
            ))),
            'docker' => new ModuleResult('docker', app(DockerAnalyzer::class)->analyze(new DockerOptions(
                path: $path,
                strict: (bool) $this->option('strict'),
            ))),
            'ports' => new ModuleResult('ports', app(PortsAnalyzer::class)->analyze(new PortsOptions(
                path: $path,
                common: true,
                strict: (bool) $this->option('strict'),
            ))),
        };
    }

    /**
     * @return ModuleResult|array{results: list<ModuleResult>, exitCode: ExitCode}
     */
    private function runEnv(string $path): ModuleResult|array
    {
        $paths = PathResolver::fromBasePath($path);

        try {
            $config = app(ConfigLoader::class)->load($paths->absolute((string) $this->option('config')));
        } catch (InvalidDevDoctorConfig $exception) {
            return [
                'results' => [
                    new ModuleResult('env', new IssueCollection([
                        new Issue(
                            code: 'DD_ENV_INVALID_CONFIG',
                            severity: Severity::ERROR,
                            message: $exception->getMessage(),
                            module: 'env',
                            file: $paths->display((string) $this->option('config')),
                        ),
                    ])),
                ],
                'exitCode' => ExitCode::INVALID_CONFIG,
            ];
        }

        return new ModuleResult('env', app(EnvAnalyzer::class)->analyze(new EnvAnalysisOptions(
            path: $path,
            envFile: $config->envFile,
            exampleFile: $config->exampleFile,
            strict: (bool) $this->option('strict'),
            rules: $config->envRules,
            ignoreMissingInEnv: $config->ignoreMissingInEnv,
            ignoreMissingInExample: $config->ignoreMissingInExample,
        )));
    }

    private function failOnWarnings(): bool
    {
        if ((bool) $this->option('no-fail-on-warnings')) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<ModuleResult>  $results
     */
    private function hasErrors(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->issues->hasErrors()) {
                return true;
            }
        }

        return false;
    }
}
