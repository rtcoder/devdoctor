<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\RendersDiagnostics;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Severity;
use LaravelZero\Framework\Commands\Command;

final class ComposerCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'composer
        {--path=. : Project path to inspect}
        {--format=table : Output format: table or json}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}';

    protected $description = 'Check Composer project health.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult('composer', new IssueCollection([
                new Issue('DD_COMPOSER_NOT_IMPLEMENTED', Severity::WARNING, 'Composer diagnostics are not implemented yet.', 'composer'),
            ])),
        ]);
    }
}
