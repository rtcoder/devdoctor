<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\Baseline\BaselineManager;
use DevDoctor\Core\Baseline\InvalidBaseline;
use DevDoctor\Core\Config\ConfigLoader;
use DevDoctor\Core\Config\InvalidDevDoctorConfig;
use DevDoctor\Core\ExitCode;
use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Composer\ComposerAnalyzer;
use DevDoctor\Modules\Composer\ComposerOptions;
use DevDoctor\Modules\Docker\DockerAnalyzer;
use DevDoctor\Modules\Docker\DockerOptions;
use DevDoctor\Modules\Env\EnvAnalysisOptions;
use DevDoctor\Modules\Env\EnvAnalyzer;
use DevDoctor\Modules\Git\GitAnalyzer;
use DevDoctor\Modules\Git\GitOptions;
use DevDoctor\Modules\Node\NodeAnalyzer;
use DevDoctor\Modules\Node\NodeOptions;
use DevDoctor\Modules\Php\PhpAnalyzer;
use DevDoctor\Modules\Php\PhpOptions;
use DevDoctor\Modules\Ports\PortsAnalyzer;
use DevDoctor\Modules\Ports\PortsOptions;
use DevDoctor\Modules\Presets\PresetsAnalyzer;
use LaravelZero\Framework\Commands\Command;

final class CiCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'ci
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--modules= : Comma-separated modules to run}
        {--exclude= : Comma-separated modules to exclude}
        {--fail-on-warnings : Return a non-zero exit code for warnings}
        {--no-fail-on-warnings : Return zero when diagnostics only contain warnings}
        {--config=devdoctor.yml : DevDoctor config file name}
        {--baseline= : Baseline file to apply}
        {--write-baseline= : Write warning and error fingerprints to a baseline file}
        {--force : Allow replacing an existing baseline file}';

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

        $baselineResult = $this->applyBaseline($path, $results);

        if (array_key_exists('exitCode', $baselineResult)) {
            return $this->renderDiagnostics($baselineResult['results'], $baselineResult['exitCode']);
        }

        $results = $baselineResult;

        $writeResult = $this->writeBaseline($path, $results);

        if ($writeResult !== null) {
            return $this->renderDiagnostics($writeResult['results'], $writeResult['exitCode']);
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
        $modules = $this->stringList((string) ($this->option('modules') ?: 'env,php,node,composer,git,docker'));
        $exclude = $this->stringList((string) ($this->option('exclude') ?: ''));

        return array_values(array_diff($modules, $exclude));
    }

    /**
     * @return list<string>
     */
    private function knownModules(): array
    {
        return ['env', 'php', 'node', 'composer', 'git', 'docker', 'ports', 'presets'];
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
            'php' => new ModuleResult('php', app(PhpAnalyzer::class)->analyze(new PhpOptions(
                path: $path,
                ci: true,
                strict: (bool) $this->option('strict'),
            ))),
            'node' => new ModuleResult('node', app(NodeAnalyzer::class)->analyze(new NodeOptions(
                path: $path,
                strict: (bool) $this->option('strict'),
            ))),
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
            'presets' => new ModuleResult('presets', app(PresetsAnalyzer::class)->analyze($path)),
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

    /**
     * @param  list<ModuleResult>  $results
     * @return list<ModuleResult>|array{results: list<ModuleResult>, exitCode: ExitCode}
     */
    private function applyBaseline(string $path, array $results): array
    {
        $baselineFile = (string) ($this->option('baseline') ?: '');

        if ($baselineFile === '') {
            return $results;
        }

        $baselinePath = PathResolver::fromBasePath($path)->absolute($baselineFile);

        if (! is_file($baselinePath)) {
            return $this->baselineError('DD_CI_BASELINE_MISSING', 'Baseline file does not exist: '.$baselineFile, ExitCode::MISSING_DEPENDENCY);
        }

        try {
            $baseline = app(BaselineManager::class)->load($baselinePath);
        } catch (InvalidBaseline $exception) {
            return $this->baselineError('DD_CI_BASELINE_INVALID', $exception->getMessage(), ExitCode::INVALID_CONFIG);
        }

        return app(BaselineManager::class)->apply($baseline, $results);
    }

    /**
     * @param  list<ModuleResult>  $results
     * @return array{results: list<ModuleResult>, exitCode: ExitCode}|null
     */
    private function writeBaseline(string $path, array $results): ?array
    {
        $baselineFile = (string) ($this->option('write-baseline') ?: '');

        if ($baselineFile === '') {
            return null;
        }

        $baselinePath = PathResolver::fromBasePath($path)->absolute($baselineFile);

        if (is_file($baselinePath) && ! (bool) $this->option('force')) {
            return $this->baselineError(
                'DD_CI_BASELINE_EXISTS',
                'Baseline file already exists: '.$baselineFile.'. Use --force to replace it.',
                ExitCode::INVALID_CONFIG,
            );
        }

        $directory = dirname($baselinePath);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        app(BaselineManager::class)->write($baselinePath, $results);

        return null;
    }

    /**
     * @return array{results: list<ModuleResult>, exitCode: ExitCode}
     */
    private function baselineError(string $code, string $message, ExitCode $exitCode): array
    {
        return [
            'results' => [
                new ModuleResult('ci', new IssueCollection([
                    new Issue(
                        code: $code,
                        severity: Severity::ERROR,
                        message: $message,
                        module: 'ci',
                    ),
                ])),
            ],
            'exitCode' => $exitCode,
        ];
    }
}
