<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\RendersDiagnostics;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Modules\Git\GitAnalyzer;
use App\DevDoctor\Modules\Git\GitOptions;
use LaravelZero\Framework\Commands\Command;

final class GitCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'git
        {--path=. : Project path to inspect}
        {--format=table : Output format: table or json}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--require-clean : Return an error when the worktree is dirty}
        {--require-upstream : Return an error when the current branch has no upstream}
        {--scan-sensitive : Scan tracked and untracked sensitive files}
        {--scan-large-files : Scan untracked files above the configured size threshold}
        {--large-file-threshold=10M : Size threshold for --scan-large-files}';

    protected $description = 'Check Git repository hygiene.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult('git', app(GitAnalyzer::class)->analyze(new GitOptions(
                path: (string) $this->option('path'),
                strict: (bool) $this->option('strict'),
                requireClean: (bool) $this->option('require-clean'),
                requireUpstream: (bool) $this->option('require-upstream'),
                scanSensitive: (bool) $this->option('scan-sensitive') || ! (bool) $this->option('ci'),
                scanLargeFiles: (bool) $this->option('scan-large-files'),
                largeFileThreshold: (string) $this->option('large-file-threshold'),
            ))),
        ]);
    }
}
