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
use App\DevDoctor\Modules\Env\EnvAnalysisOptions;
use App\DevDoctor\Modules\Env\EnvAnalyzer;
use LaravelZero\Framework\Commands\Command;

final class EnvCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'env
        {--path=. : Project path to inspect}
        {--env-file=.env : Env file name}
        {--example=.env.example : Example env file name}
        {--config=devdoctor.yml : DevDoctor config file name}
        {--format=table : Output format: table or json}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--no-secrets : Disable secret scanning for this run}';

    protected $description = 'Check dotenv files and DevDoctor env rules.';

    public function handle(): int
    {
        $paths = PathResolver::fromBasePath((string) $this->option('path'));

        try {
            $config = app(ConfigLoader::class)->load($paths->absolute((string) $this->option('config')));
        } catch (InvalidDevDoctorConfig $exception) {
            return $this->renderDiagnostics([
                new ModuleResult('env', new IssueCollection([
                    new Issue(
                        code: 'DD_ENV_INVALID_CONFIG',
                        severity: Severity::ERROR,
                        message: $exception->getMessage(),
                        module: 'env',
                        file: $paths->display((string) $this->option('config')),
                    ),
                ])),
            ], ExitCode::INVALID_CONFIG);
        }

        $issues = app(EnvAnalyzer::class)->analyze(new EnvAnalysisOptions(
            path: (string) $this->option('path'),
            envFile: $this->option('env-file') !== '.env' ? (string) $this->option('env-file') : $config->envFile,
            exampleFile: $this->option('example') !== '.env.example' ? (string) $this->option('example') : $config->exampleFile,
            strict: (bool) $this->option('strict'),
            scanSecrets: ! (bool) $this->option('no-secrets'),
            rules: $config->envRules,
            ignoreMissingInEnv: $config->ignoreMissingInEnv,
            ignoreMissingInExample: $config->ignoreMissingInExample,
        ));

        return $this->renderDiagnostics([
            new ModuleResult('env', $issues),
        ]);
    }
}
