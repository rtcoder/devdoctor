<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Rust;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class RustAnalyzer
{
    public function analyze(RustOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;

        if (! $files->exists('Cargo.toml') && ! $files->exists('Cargo.lock') && ! $files->exists('rust-toolchain.toml')) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUST_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Rust Cargo project detected',
                module: ModuleName::RUST,
            ));

            return $issues;
        }

        if ($files->exists('Cargo.toml')) {
            $this->checkCargoToml($issues, $files);
        }

        if ($files->exists('rust-toolchain.toml')) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUST_TOOLCHAIN_DECLARED,
                severity: Severity::INFO,
                message: 'Rust toolchain file is declared',
                module: ModuleName::RUST,
                file: 'rust-toolchain.toml',
            ));
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUST_READY,
                severity: Severity::INFO,
                message: 'Rust diagnostics found no actionable issues.',
                module: ModuleName::RUST,
            ));
        }

        return $issues;
    }

    private function checkCargoToml(IssueCollection $issues, ProjectFiles $files): void
    {
        $contents = $files->contents('Cargo.toml');

        if ($this->looksLikeBinaryPackage($contents, $files) && ! $files->exists('Cargo.lock')) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUST_LOCK_MISSING,
                severity: Severity::WARNING,
                message: 'Rust application appears to be missing Cargo.lock',
                module: ModuleName::RUST,
                file: 'Cargo.toml',
                key: 'Cargo.lock',
            ));
        }

        if ($this->hasPackageSection($contents) && ! $this->hasEdition($contents)) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUST_EDITION_MISSING,
                severity: Severity::INFO,
                message: 'Cargo package does not declare an edition',
                module: ModuleName::RUST,
                file: 'Cargo.toml',
            ));
        }

        $this->checkWorkspaceMembers($issues, $files, $contents);
        $this->checkDependencySources($issues, $contents);
        $this->checkReleaseProfile($issues, $contents);
    }

    private function looksLikeBinaryPackage(string $contents, ProjectFiles $files): bool
    {
        return $files->exists('src/main.rs')
            || str_contains($contents, '[[bin]]')
            || preg_match('/^\s*crate-type\s*=.*bin/m', $contents) === 1;
    }

    private function hasPackageSection(string $contents): bool
    {
        return preg_match('/^\s*\[package]\s*$/m', $contents) === 1;
    }

    private function hasEdition(string $contents): bool
    {
        return preg_match('/^\s*edition\s*=\s*["\']\d{4}["\']/m', $contents) === 1;
    }

    private function checkWorkspaceMembers(IssueCollection $issues, ProjectFiles $files, string $contents): void
    {
        foreach ($this->workspaceMembers($contents) as $lineNumber => $member) {
            $member = trim($member, " \t\n\r\0\x0B\"'");

            if ($member === '' || str_contains($member, '*')) {
                continue;
            }

            if (! $files->existsIn($member, 'Cargo.toml')) {
                $issues->add(new Issue(
                    code: IssueCode::DD_RUST_WORKSPACE_MEMBER_MISSING,
                    severity: Severity::WARNING,
                    message: 'Cargo workspace member is missing Cargo.toml',
                    module: ModuleName::RUST,
                    file: 'Cargo.toml',
                    line: $lineNumber,
                    key: $member,
                ));
            }
        }
    }

    private function checkDependencySources(IssueCollection $issues, string $contents): void
    {
        foreach (explode("\n", $contents) as $index => $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/\bpath\s*=/', $trimmed) === 1) {
                $issues->add(new Issue(
                    code: IssueCode::DD_RUST_PATH_DEPENDENCY,
                    severity: Severity::WARNING,
                    message: 'Rust dependency uses a local path source',
                    module: ModuleName::RUST,
                    file: 'Cargo.toml',
                    line: $index + 1,
                ));
            }

            if (preg_match('/\bgit\s*=/', $trimmed) === 1) {
                $issues->add(new Issue(
                    code: IssueCode::DD_RUST_GIT_DEPENDENCY,
                    severity: Severity::WARNING,
                    message: 'Rust dependency uses a Git source',
                    module: ModuleName::RUST,
                    file: 'Cargo.toml',
                    line: $index + 1,
                ));
            }
        }
    }

    private function checkReleaseProfile(IssueCollection $issues, string $contents): void
    {
        if (preg_match('/^\s*\[profile\.release]\s*$(.*?)(^\s*\[|\z)/ms', $contents, $match) !== 1) {
            return;
        }

        $profile = $match[1];

        if (preg_match('/^\s*debug\s*=\s*true\s*$/m', $profile) === 1 || preg_match('/^\s*opt-level\s*=\s*["\']?0["\']?\s*$/m', $profile) === 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUST_RELEASE_PROFILE_DEBUG,
                severity: Severity::WARNING,
                message: 'Rust release profile keeps debug-like settings',
                module: ModuleName::RUST,
                file: 'Cargo.toml',
            ));
        }
    }

    /**
     * @return array<int, string>
     */
    private function workspaceMembers(string $contents): array
    {
        if (preg_match('/^\s*members\s*=\s*\[(.*?)\]/ms', $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $members = [];
        $block = $match[1][0];
        $startLine = substr_count(substr($contents, 0, $match[1][1]), "\n") + 1;

        foreach (explode("\n", $block) as $offset => $line) {
            foreach (explode(',', $line) as $candidate) {
                $candidate = trim($candidate);

                if ($candidate !== '') {
                    $members[$startLine + $offset] = $candidate;
                }
            }
        }

        return $members;
    }
}
