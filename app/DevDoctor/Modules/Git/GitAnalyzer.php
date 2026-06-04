<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Git;

use App\DevDoctor\Core\CommandAvailability;
use App\DevDoctor\Core\CommandAvailabilityInterface;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\PathResolver;
use App\DevDoctor\Core\Severity;

final readonly class GitAnalyzer
{
    public function __construct(
        private GitRunnerInterface $git = new ProcessGitRunner,
        private CommandAvailabilityInterface $commands = new CommandAvailability,
    ) {}

    public function analyze(GitOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;

        if (! $this->commands->available('git')) {
            $issues->add(new Issue(
                code: 'DD_GIT_BINARY_MISSING',
                severity: Severity::WARNING,
                message: 'Git binary was not found.',
                module: 'git',
            ));

            return $issues;
        }

        if (! $this->isRepository($options->path)) {
            $issues->add(new Issue(
                code: 'DD_GIT_NOT_REPOSITORY',
                severity: Severity::INFO,
                message: 'Path is not inside a Git repository',
                module: 'git',
            ));

            return $issues;
        }

        $this->checkStatus($issues, $options);
        $this->checkHead($issues, $options->path);
        $this->checkUpstream($issues, $options);
        $this->checkIgnoredEnv($issues, $options->path);

        if ($options->scanSensitive) {
            $this->checkSensitiveFiles($issues, $options);
        }

        if ($options->scanLargeFiles) {
            $this->checkLargeUntrackedFiles($issues, $paths, $options);
        }

        if ($issues->summary() === ['errors' => 0, 'warnings' => 0, 'info' => 0]) {
            $issues->add(new Issue(
                code: 'DD_GIT_READY',
                severity: Severity::INFO,
                message: 'Git diagnostics found no issues.',
                module: 'git',
            ));
        }

        return $issues;
    }

    private function isRepository(string $path): bool
    {
        return trim($this->git->run(['rev-parse', '--is-inside-work-tree'], $path)->stdout) === 'true';
    }

    private function checkStatus(IssueCollection $issues, GitOptions $options): void
    {
        $result = $this->git->run(['status', '--porcelain=v1'], $options->path);
        $status = trim($result->stdout);

        if ($status === '') {
            return;
        }

        if ($this->hasConflicts($status)) {
            $issues->add(new Issue(
                code: 'DD_GIT_CONFLICTS',
                severity: Severity::ERROR,
                message: 'Repository has unresolved merge conflicts',
                module: 'git',
            ));
        }

        $issues->add(new Issue(
            code: 'DD_GIT_DIRTY_WORKTREE',
            severity: $options->requireClean || $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'Repository has uncommitted changes',
            module: 'git',
            context: ['changed_files' => count($this->statusLines($status))],
        ));
    }

    private function checkHead(IssueCollection $issues, string $path): void
    {
        $result = $this->git->run(['rev-parse', '--abbrev-ref', 'HEAD'], $path);

        if (trim($result->stdout) !== 'HEAD') {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_GIT_DETACHED_HEAD',
            severity: Severity::WARNING,
            message: 'Repository is currently on a detached HEAD',
            module: 'git',
        ));
    }

    private function checkUpstream(IssueCollection $issues, GitOptions $options): void
    {
        $upstream = $this->git->run(['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}'], $options->path);

        if (! $upstream->successful() || trim($upstream->stdout) === '') {
            $issues->add(new Issue(
                code: 'DD_GIT_NO_UPSTREAM',
                severity: $options->requireUpstream || $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'Current branch has no upstream configured',
                module: 'git',
            ));

            return;
        }

        $aheadBehind = $this->git->run(['rev-list', '--left-right', '--count', 'HEAD...@{u}'], $options->path);

        if (! $aheadBehind->successful()) {
            return;
        }

        $parts = preg_split('/\s+/', trim($aheadBehind->stdout));
        $ahead = (int) ($parts[0] ?? 0);
        $behind = (int) ($parts[1] ?? 0);

        if ($ahead === 0 && $behind === 0) {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_GIT_AHEAD_BEHIND',
            severity: Severity::WARNING,
            message: 'Current branch differs from upstream',
            module: 'git',
            context: ['ahead' => $ahead, 'behind' => $behind],
        ));
    }

    private function checkIgnoredEnv(IssueCollection $issues, string $path): void
    {
        if ($this->git->run(['check-ignore', '.env'], $path)->successful()) {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_GIT_ENV_NOT_IGNORED',
            severity: Severity::WARNING,
            message: '.env is not ignored by Git',
            module: 'git',
            file: '.env',
        ));
    }

    private function checkSensitiveFiles(IssueCollection $issues, GitOptions $options): void
    {
        foreach ($this->splitPaths($this->git->run(['ls-files', '-z'], $options->path)->stdout) as $file) {
            if (! $this->isSensitivePath($file)) {
                continue;
            }

            $issues->add(new Issue(
                code: 'DD_GIT_TRACKED_SENSITIVE_FILE',
                severity: Severity::ERROR,
                message: 'Sensitive file is tracked by Git',
                module: 'git',
                file: $file,
            ));
        }

        foreach ($this->untrackedFiles($options->path) as $file) {
            if (! $this->isSensitivePath($file)) {
                continue;
            }

            $issues->add(new Issue(
                code: 'DD_GIT_UNTRACKED_SENSITIVE_FILE',
                severity: Severity::WARNING,
                message: 'Sensitive file is present but untracked',
                module: 'git',
                file: $file,
            ));
        }
    }

    private function checkLargeUntrackedFiles(IssueCollection $issues, PathResolver $paths, GitOptions $options): void
    {
        $threshold = $this->parseBytes($options->largeFileThreshold);

        foreach ($this->untrackedFiles($options->path) as $file) {
            $absolute = $paths->absolute($file);

            if (! is_file($absolute) || filesize($absolute) <= $threshold) {
                continue;
            }

            $issues->add(new Issue(
                code: 'DD_GIT_LARGE_UNTRACKED_FILE',
                severity: Severity::WARNING,
                message: 'Large untracked file detected',
                module: 'git',
                file: $file,
                context: [
                    'bytes' => filesize($absolute),
                    'threshold' => $threshold,
                ],
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function untrackedFiles(string $path): array
    {
        return $this->splitPaths($this->git->run(['ls-files', '--others', '--exclude-standard', '-z'], $path)->stdout);
    }

    private function hasConflicts(string $status): bool
    {
        foreach ($this->statusLines($status) as $line) {
            $state = substr($line, 0, 2);

            if (str_contains($state, 'U') || in_array($state, ['AA', 'DD'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function statusLines(string $status): array
    {
        return array_values(array_filter(
            preg_split('/\R/', trim($status)) ?: [],
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @return list<string>
     */
    private function splitPaths(string $paths): array
    {
        return array_values(array_filter(
            explode("\0", $paths),
            static fn (string $path): bool => $path !== '',
        ));
    }

    private function isSensitivePath(string $path): bool
    {
        $basename = basename($path);

        return $basename === '.env'
            || preg_match('/^\.env\.(?!example$|dist$|sample$).+/', $basename) === 1
            || preg_match('/\.(pem|key|p12|pfx)$/i', $basename) === 1
            || in_array($basename, ['id_rsa', 'id_dsa', 'id_ecdsa', 'id_ed25519'], true);
    }

    private function parseBytes(string $value): int
    {
        if (preg_match('/^\s*(\d+)\s*([KMG])?B?\s*$/i', $value, $matches) !== 1) {
            return 10 * 1024 * 1024;
        }

        $bytes = (int) $matches[1];

        return match (strtoupper($matches[2] ?? '')) {
            'K' => $bytes * 1024,
            'M' => $bytes * 1024 * 1024,
            'G' => $bytes * 1024 * 1024 * 1024,
            default => $bytes,
        };
    }
}
