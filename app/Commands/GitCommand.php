<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Git\GitAnalyzer;
use DevDoctor\Modules\Git\GitOptions;
use LaravelZero\Framework\Commands\Command;

final class GitCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'git
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}
        {--require-clean : Return an error when the worktree is dirty}
        {--require-upstream : Return an error when the current branch has no upstream}
        {--scan-sensitive : Scan tracked and untracked sensitive files}
        {--scan-large-files : Scan untracked files above the configured size threshold}
        {--large-file-threshold=10M : Size threshold for --scan-large-files}';

    protected $description = 'Check Git repository hygiene.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::GIT, app(GitAnalyzer::class)->analyze(new GitOptions(
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
