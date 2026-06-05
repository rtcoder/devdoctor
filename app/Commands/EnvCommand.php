<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\Config\ConfigLoader;
use DevDoctor\Core\Config\InvalidDevDoctorConfig;
use DevDoctor\Core\ExitCode;
use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvAnalysisOptions;
use DevDoctor\Modules\Env\EnvAnalyzer;
use LaravelZero\Framework\Commands\Command;

final class EnvCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'env
        {--path=. : Project path to inspect}
        {--env-file=.env : Env file name}
        {--example=.env.example : Example env file name}
        {--config=devdoctor.yml : DevDoctor config file name}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}
        {--no-secrets : Disable secret scanning for this run}';

    protected $description = 'Check dotenv files and DevDoctor env rules.';

    public function handle(): int
    {
        $paths = PathResolver::fromBasePath((string) $this->option('path'));

        try {
            $config = app(ConfigLoader::class)->load($paths->absolute((string) $this->option('config')));
        } catch (InvalidDevDoctorConfig $exception) {
            return $this->renderDiagnostics([
                new ModuleResult(ModuleName::ENV, new IssueCollection([
                    new Issue(
                        code: IssueCode::DD_ENV_INVALID_CONFIG,
                        severity: Severity::ERROR,
                        message: $exception->getMessage(),
                        module: ModuleName::ENV,
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
            new ModuleResult(ModuleName::ENV, $issues),
        ]);
    }
}
