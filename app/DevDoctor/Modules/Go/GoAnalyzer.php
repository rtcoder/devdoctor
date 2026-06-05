<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Go;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class GoAnalyzer
{
    public function analyze(GoOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;

        if (! $files->exists('go.mod') && ! $files->exists('go.work')) {
            $issues->add(new Issue(
                code: IssueCode::DD_GO_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Go module or workspace detected',
                module: ModuleName::GO,
            ));

            return $issues;
        }

        if ($files->exists('go.mod')) {
            $this->checkGoMod($issues, $files);
        }

        if ($files->exists('go.work')) {
            $this->checkGoWork($issues, $files);
        }

        if ($files->exists('vendor/modules.txt')) {
            $issues->add(new Issue(
                code: IssueCode::DD_GO_VENDOR_PRESENT,
                severity: Severity::INFO,
                message: 'Go vendor directory metadata is present',
                module: ModuleName::GO,
                file: 'vendor/modules.txt',
            ));
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_GO_READY,
                severity: Severity::INFO,
                message: 'Go diagnostics found no actionable issues.',
                module: ModuleName::GO,
            ));
        }

        return $issues;
    }

    private function checkGoMod(IssueCollection $issues, ProjectFiles $files): void
    {
        $contents = $files->contents('go.mod');
        $modulePath = $this->modulePath($contents);

        if ($modulePath === null || $this->modulePathLooksInvalid($modulePath)) {
            $issues->add(new Issue(
                code: IssueCode::DD_GO_MODULE_PATH_INVALID,
                severity: Severity::WARNING,
                message: 'Go module path looks invalid or is missing',
                module: ModuleName::GO,
                file: 'go.mod',
                key: $modulePath,
            ));
        }

        if ($this->hasRequireDirective($contents) && ! $files->exists('go.sum')) {
            $issues->add(new Issue(
                code: IssueCode::DD_GO_SUM_MISSING,
                severity: Severity::WARNING,
                message: 'go.mod declares dependencies but go.sum is missing',
                module: ModuleName::GO,
                file: 'go.mod',
                key: 'go.sum',
            ));
        }

        foreach (explode("\n", $contents) as $lineNumber => $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, 'replace ') && preg_match('/=>\s*(\.{1,2}\/|\/|[A-Za-z]:\\\\)/', $trimmed) === 1) {
                $issues->add(new Issue(
                    code: IssueCode::DD_GO_REPLACE_DIRECTIVE,
                    severity: Severity::WARNING,
                    message: 'Go replace directive points to a local path',
                    module: ModuleName::GO,
                    file: 'go.mod',
                    line: $lineNumber + 1,
                ));
            }

            if (str_starts_with($trimmed, 'toolchain ')) {
                $issues->add(new Issue(
                    code: IssueCode::DD_GO_TOOLCHAIN_DECLARED,
                    severity: Severity::INFO,
                    message: 'Go toolchain directive is declared',
                    module: ModuleName::GO,
                    file: 'go.mod',
                    line: $lineNumber + 1,
                    key: substr($trimmed, strlen('toolchain ')),
                ));
            }
        }
    }

    private function checkGoWork(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach ($this->workspaceUses($files->contents('go.work')) as $lineNumber => $directory) {
            if (! $files->existsIn($directory, 'go.mod')) {
                $issues->add(new Issue(
                    code: IssueCode::DD_GO_WORKSPACE_MODULE_MISSING,
                    severity: Severity::WARNING,
                    message: 'go.work references a directory without go.mod',
                    module: ModuleName::GO,
                    file: 'go.work',
                    line: $lineNumber,
                    key: $directory,
                ));
            }
        }
    }

    private function modulePath(string $contents): ?string
    {
        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'module ')) {
                return trim(substr($line, strlen('module ')));
            }
        }

        return null;
    }

    private function modulePathLooksInvalid(string $modulePath): bool
    {
        return $modulePath === ''
            || str_contains($modulePath, ' ')
            || str_starts_with($modulePath, './')
            || str_starts_with($modulePath, '../')
            || str_starts_with($modulePath, 'http://')
            || str_starts_with($modulePath, 'https://');
    }

    private function hasRequireDirective(string $contents): bool
    {
        foreach (explode("\n", $contents) as $line) {
            if (preg_match('/^\s*require\s+(\(|[^\s]+)/', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function workspaceUses(string $contents): array
    {
        $uses = [];
        $inBlock = false;

        foreach (explode("\n", $contents) as $index => $line) {
            $lineNumber = $index + 1;
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }

            if ($trimmed === 'use (') {
                $inBlock = true;

                continue;
            }

            if ($inBlock && $trimmed === ')') {
                $inBlock = false;

                continue;
            }

            if ($inBlock) {
                $uses[$lineNumber] = trim($trimmed, '"');

                continue;
            }

            if (str_starts_with($trimmed, 'use ')) {
                $uses[$lineNumber] = trim(substr($trimmed, strlen('use ')), '"');
            }
        }

        return $uses;
    }
}
