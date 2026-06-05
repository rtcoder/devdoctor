<?php

declare(strict_types=1);

namespace DevDoctor\Core;

use DevDoctor\Core\Config\ConfigLoader;
use DevDoctor\Core\Config\InvalidDevDoctorConfig;
use DevDoctor\Modules\Cache\CacheAnalyzer;
use DevDoctor\Modules\Cache\CacheOptions;
use DevDoctor\Modules\Composer\ComposerAnalyzer;
use DevDoctor\Modules\Composer\ComposerOptions;
use DevDoctor\Modules\Database\DatabaseAnalyzer;
use DevDoctor\Modules\Database\DatabaseOptions;
use DevDoctor\Modules\Docker\DockerAnalyzer;
use DevDoctor\Modules\Docker\DockerOptions;
use DevDoctor\Modules\Env\EnvAnalysisOptions;
use DevDoctor\Modules\Env\EnvAnalyzer;
use DevDoctor\Modules\Git\GitAnalyzer;
use DevDoctor\Modules\Git\GitOptions;
use DevDoctor\Modules\Laravel\LaravelAnalyzer;
use DevDoctor\Modules\Laravel\LaravelOptions;
use DevDoctor\Modules\Node\NodeAnalyzer;
use DevDoctor\Modules\Node\NodeOptions;
use DevDoctor\Modules\Php\PhpAnalyzer;
use DevDoctor\Modules\Php\PhpOptions;
use DevDoctor\Modules\Ports\PortsAnalyzer;
use DevDoctor\Modules\Ports\PortsOptions;
use DevDoctor\Modules\Presets\PresetsAnalyzer;
use DevDoctor\Modules\Queue\QueueAnalyzer;
use DevDoctor\Modules\Queue\QueueOptions;
use DevDoctor\Modules\Security\SecurityAnalyzer;
use DevDoctor\Modules\Security\SecurityOptions;

final class DiagnosticModuleRunner
{
    /**
     * @return list<string>
     */
    public function knownModules(): array
    {
        return array_map(static fn (ModuleName $module): string => $module->value, [
            ModuleName::ENV,
            ModuleName::CACHE,
            ModuleName::PHP,
            ModuleName::NODE,
            ModuleName::LARAVEL,
            ModuleName::COMPOSER,
            ModuleName::DATABASE,
            ModuleName::GIT,
            ModuleName::DOCKER,
            ModuleName::PORTS,
            ModuleName::PRESETS,
            ModuleName::QUEUE,
            ModuleName::SECURITY,
        ]);
    }

    /**
     * @return ModuleResult|array{results: list<ModuleResult>, exitCode: ExitCode}
     */
    public function run(string $module, DiagnosticRunOptions $options): ModuleResult|array
    {
        return match ($module) {
            ModuleName::ENV->value => $this->runEnv($options),
            ModuleName::CACHE->value => new ModuleResult(ModuleName::CACHE, app(CacheAnalyzer::class)->analyze(new CacheOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            ModuleName::PHP->value => new ModuleResult(ModuleName::PHP, app(PhpAnalyzer::class)->analyze(new PhpOptions(
                path: $options->path,
                ci: $options->ci,
                strict: $options->strict,
            ))),
            ModuleName::NODE->value => new ModuleResult(ModuleName::NODE, app(NodeAnalyzer::class)->analyze(new NodeOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            ModuleName::LARAVEL->value => new ModuleResult(ModuleName::LARAVEL, app(LaravelAnalyzer::class)->analyze(new LaravelOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            ModuleName::COMPOSER->value => new ModuleResult(ModuleName::COMPOSER, app(ComposerAnalyzer::class)->analyze(new ComposerOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            ModuleName::DATABASE->value => new ModuleResult(ModuleName::DATABASE, app(DatabaseAnalyzer::class)->analyze(new DatabaseOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            ModuleName::GIT->value => new ModuleResult(ModuleName::GIT, app(GitAnalyzer::class)->analyze(new GitOptions(
                path: $options->path,
                strict: $options->strict,
                requireClean: $options->gitRequireClean,
                requireUpstream: $options->gitRequireUpstream,
                scanSensitive: $options->gitScanSensitive,
                scanLargeFiles: $options->gitScanLargeFiles,
            ))),
            ModuleName::DOCKER->value => new ModuleResult(ModuleName::DOCKER, app(DockerAnalyzer::class)->analyze(new DockerOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            ModuleName::PORTS->value => new ModuleResult(ModuleName::PORTS, app(PortsAnalyzer::class)->analyze(new PortsOptions(
                path: $options->path,
                common: $options->portsCommon,
                strict: $options->strict,
            ))),
            ModuleName::PRESETS->value => new ModuleResult(ModuleName::PRESETS, app(PresetsAnalyzer::class)->analyze($options->path)),
            ModuleName::QUEUE->value => new ModuleResult(ModuleName::QUEUE, app(QueueAnalyzer::class)->analyze(new QueueOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            ModuleName::SECURITY->value => new ModuleResult(ModuleName::SECURITY, app(SecurityAnalyzer::class)->analyze(new SecurityOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
        };
    }

    /**
     * @return ModuleResult|array{results: list<ModuleResult>, exitCode: ExitCode}
     */
    private function runEnv(DiagnosticRunOptions $options): ModuleResult|array
    {
        $paths = PathResolver::fromBasePath($options->path);

        try {
            $config = app(ConfigLoader::class)->load($paths->absolute($options->configFile));
        } catch (InvalidDevDoctorConfig $exception) {
            return [
                'results' => [
                    new ModuleResult(ModuleName::ENV, new IssueCollection([
                        new Issue(
                            code: IssueCode::DD_ENV_INVALID_CONFIG,
                            severity: Severity::ERROR,
                            message: $exception->getMessage(),
                            module: ModuleName::ENV,
                            file: $paths->display($options->configFile),
                        ),
                    ])),
                ],
                'exitCode' => ExitCode::INVALID_CONFIG,
            ];
        }

        return new ModuleResult(ModuleName::ENV, app(EnvAnalyzer::class)->analyze(new EnvAnalysisOptions(
            path: $options->path,
            envFile: $config->envFile,
            exampleFile: $config->exampleFile,
            strict: $options->strict,
            rules: $config->envRules,
            ignoreMissingInEnv: $config->ignoreMissingInEnv,
            ignoreMissingInExample: $config->ignoreMissingInExample,
        )));
    }
}
