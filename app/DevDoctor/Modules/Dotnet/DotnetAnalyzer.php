<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Dotnet;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class DotnetAnalyzer
{
    /**
     * @return list<string>
     */
    private function projectFiles(ProjectFiles $files): array
    {
        return [
            ...$files->glob('*.csproj'),
            ...$files->glob('*.fsproj'),
            ...$files->glob('*.vbproj'),
        ];
    }

    public function analyze(DotnetOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $projects = $this->projectFiles($files);
        $solutions = $files->glob('*.sln');

        if ($projects === [] && $solutions === [] && ! $files->exists('global.json') && ! $files->exists('NuGet.config')) {
            $issues->add(new Issue(
                code: IssueCode::DD_DOTNET_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No .NET solution, project, SDK, or NuGet config detected',
                module: ModuleName::DOTNET,
            ));

            return $issues;
        }

        $this->checkSdkPinning($issues, $files, $projects, $solutions);
        $this->checkTargetFrameworks($issues, $files, $projects);
        $this->checkLockMode($issues, $files, $projects);
        $this->checkSolutionState($issues, $solutions);
        $this->checkNuGetSources($issues, $files);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_DOTNET_READY,
                severity: Severity::INFO,
                message: '.NET diagnostics found no actionable issues.',
                module: ModuleName::DOTNET,
            ));
        }

        return $issues;
    }

    /**
     * @param  list<string>  $projects
     * @param  list<string>  $solutions
     */
    private function checkSdkPinning(IssueCollection $issues, ProjectFiles $files, array $projects, array $solutions): void
    {
        if (($projects !== [] || $solutions !== []) && ! $files->exists('global.json')) {
            $issues->add(new Issue(
                code: IssueCode::DD_DOTNET_SDK_NOT_PINNED,
                severity: Severity::WARNING,
                message: '.NET SDK version is not pinned with global.json',
                module: ModuleName::DOTNET,
                file: 'global.json',
            ));
        }
    }

    /**
     * @param  list<string>  $projects
     */
    private function checkTargetFrameworks(IssueCollection $issues, ProjectFiles $files, array $projects): void
    {
        $frameworks = [];

        foreach ($projects as $project) {
            preg_match_all('/<TargetFrameworks?>\s*([^<]+)\s*<\/TargetFrameworks?>/i', $files->contents($project), $matches);

            foreach ($matches[1] as $match) {
                foreach (explode(';', $match) as $framework) {
                    $framework = trim($framework);

                    if ($framework !== '') {
                        $frameworks[$project][] = $framework;
                    }
                }
            }
        }

        $majorVersions = array_unique(array_map(
            fn (string $framework): string => $this->frameworkMajor($framework),
            array_merge(...array_values($frameworks ?: [[]])),
        ));

        $majorVersions = array_values(array_filter($majorVersions));

        if (count($majorVersions) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_DOTNET_TARGET_FRAMEWORK_MISMATCH,
            severity: Severity::WARNING,
            message: '.NET target frameworks span multiple major platform versions',
            module: ModuleName::DOTNET,
            file: array_key_first($frameworks),
            context: ['frameworks' => $frameworks],
        ));
    }

    private function frameworkMajor(string $framework): string
    {
        if (preg_match('/net(?:coreapp|standard)?(\d+)/i', $framework, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    /**
     * @param  list<string>  $projects
     */
    private function checkLockMode(IssueCollection $issues, ProjectFiles $files, array $projects): void
    {
        foreach ($projects as $project) {
            $contents = $files->contents($project);

            if (
                (str_contains($contents, '<RestorePackagesWithLockFile>true</RestorePackagesWithLockFile>')
                    || str_contains($contents, '<RestoreLockedMode>true</RestoreLockedMode>'))
                && ! $files->exists('packages.lock.json')
            ) {
                $issues->add(new Issue(
                    code: IssueCode::DD_DOTNET_LOCK_MISSING,
                    severity: Severity::WARNING,
                    message: '.NET restore lock mode is enabled but packages.lock.json is missing',
                    module: ModuleName::DOTNET,
                    file: $project,
                    key: 'packages.lock.json',
                ));
            }
        }
    }

    /**
     * @param  list<string>  $solutions
     */
    private function checkSolutionState(IssueCollection $issues, array $solutions): void
    {
        if (count($solutions) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_DOTNET_MIXED_SOLUTION_STATE,
            severity: Severity::WARNING,
            message: 'Multiple .NET solution files detected',
            module: ModuleName::DOTNET,
            file: $solutions[0],
            context: ['solutions' => $solutions],
        ));
    }

    private function checkNuGetSources(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (['NuGet.config', 'nuget.config'] as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/<add\b[^>]*value=["\']http:\/\//i', $line) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_DOTNET_RISKY_NUGET_SOURCE,
                        severity: Severity::WARNING,
                        message: 'NuGet config uses an insecure HTTP package source',
                        module: ModuleName::DOTNET,
                        file: $file,
                        line: $lineNumber + 1,
                    ));
                }
            }
        }
    }
}
