<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\RendersDiagnostics;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Severity;
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
        $path = rtrim((string)$this->option('path'), DIRECTORY_SEPARATOR);
        $issues = new IssueCollection;

        $env = (string)$this->option('env-file');
        $example = (string)$this->option('example');

        if (!is_file($path . DIRECTORY_SEPARATOR . $env)) {
            $issues->add(new Issue(
                code: 'DD_ENV_FILE_MISSING',
                severity: Severity::ERROR,
                message: $env . ' does not exist',
                module: 'env',
                file: $env,
            ));
        }

        if (!is_file($path . DIRECTORY_SEPARATOR . $example)) {
            $issues->add(new Issue(
                code: 'DD_ENV_EXAMPLE_MISSING',
                severity: $this->option('strict') ? Severity::ERROR : Severity::WARNING,
                message: $example . ' does not exist',
                module: 'env',
                file: $example,
            ));
        }

        if ($issues->summary() === ['errors' => 0, 'warnings' => 0, 'info' => 0]) {
            $issues->add(new Issue(
                code: 'DD_ENV_READY',
                severity: Severity::INFO,
                message: 'Env files are present. Detailed analysis is next.',
                module: 'env',
            ));
        }

        return $this->renderDiagnostics([
            new ModuleResult('env', $issues),
        ]);
    }
}
