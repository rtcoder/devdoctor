<?php

declare(strict_types=1);

namespace DevDoctor\Core;

use DevDoctor\Core\Config\ConfigLoader;
use DevDoctor\Core\Config\InvalidDevDoctorConfig;
use DevDoctor\Modules\Composer\ComposerAnalyzer;
use DevDoctor\Modules\Composer\ComposerOptions;
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
use DevDoctor\Modules\Security\SecurityAnalyzer;
use DevDoctor\Modules\Security\SecurityOptions;

final class DiagnosticModuleRunner
{
    /**
     * @return list<string>
     */
    public function knownModules(): array
    {
        return ['env', 'php', 'node', 'laravel', 'composer', 'git', 'docker', 'ports', 'presets', 'security'];
    }

    /**
     * @return ModuleResult|array{results: list<ModuleResult>, exitCode: ExitCode}
     */
    public function run(string $module, DiagnosticRunOptions $options): ModuleResult|array
    {
        return match ($module) {
            'env' => $this->runEnv($options),
            'php' => new ModuleResult('php', app(PhpAnalyzer::class)->analyze(new PhpOptions(
                path: $options->path,
                ci: $options->ci,
                strict: $options->strict,
            ))),
            'node' => new ModuleResult('node', app(NodeAnalyzer::class)->analyze(new NodeOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            'laravel' => new ModuleResult('laravel', app(LaravelAnalyzer::class)->analyze(new LaravelOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            'composer' => new ModuleResult('composer', app(ComposerAnalyzer::class)->analyze(new ComposerOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            'git' => new ModuleResult('git', app(GitAnalyzer::class)->analyze(new GitOptions(
                path: $options->path,
                strict: $options->strict,
                requireClean: $options->gitRequireClean,
                requireUpstream: $options->gitRequireUpstream,
                scanSensitive: $options->gitScanSensitive,
                scanLargeFiles: $options->gitScanLargeFiles,
            ))),
            'docker' => new ModuleResult('docker', app(DockerAnalyzer::class)->analyze(new DockerOptions(
                path: $options->path,
                strict: $options->strict,
            ))),
            'ports' => new ModuleResult('ports', app(PortsAnalyzer::class)->analyze(new PortsOptions(
                path: $options->path,
                common: $options->portsCommon,
                strict: $options->strict,
            ))),
            'presets' => new ModuleResult('presets', app(PresetsAnalyzer::class)->analyze($options->path)),
            'security' => new ModuleResult('security', app(SecurityAnalyzer::class)->analyze(new SecurityOptions(
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
                    new ModuleResult('env', new IssueCollection([
                        new Issue(
                            code: 'DD_ENV_INVALID_CONFIG',
                            severity: Severity::ERROR,
                            message: $exception->getMessage(),
                            module: 'env',
                            file: $paths->display($options->configFile),
                        ),
                    ])),
                ],
                'exitCode' => ExitCode::INVALID_CONFIG,
            ];
        }

        return new ModuleResult('env', app(EnvAnalyzer::class)->analyze(new EnvAnalysisOptions(
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
