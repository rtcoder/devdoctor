<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Monorepo;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class MonorepoAnalyzer
{
    public function analyze(MonorepoOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $tools = $this->detectedTools($files);
        $package = $files->json('package.json');

        if ($tools === [] && ! $this->hasPackageWorkspaces($package)) {
            $issues->add(new Issue(
                code: IssueCode::DD_MONOREPO_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No supported monorepo tooling detected',
                module: ModuleName::MONOREPO,
            ));

            return $issues;
        }

        $this->checkMixedTools($issues, $tools);
        $this->checkLockfiles($issues, $files, $package, $options);
        $this->checkRootScripts($issues, $package);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_MONOREPO_READY,
                severity: Severity::INFO,
                message: 'Monorepo diagnostics found no actionable issues.',
                module: ModuleName::MONOREPO,
            ));
        }

        return $issues;
    }

    /**
     * @return array<string, string>
     */
    private function detectedTools(ProjectFiles $files): array
    {
        $tools = [];

        foreach ([
            'nx' => 'nx.json',
            'turbo' => 'turbo.json',
            'lerna' => 'lerna.json',
            'pnpm' => 'pnpm-workspace.yaml',
            'rush' => 'rush.json',
            'bazel' => 'WORKSPACE',
            'bazel-module' => 'MODULE.bazel',
            'pants' => 'pants.toml',
        ] as $tool => $file) {
            if ($files->exists($file)) {
                $tools[$tool] = $file;
            }
        }

        return $tools;
    }

    /**
     * @param  array<string, string>  $tools
     */
    private function checkMixedTools(IssueCollection $issues, array $tools): void
    {
        $primaryTools = array_intersect(array_keys($tools), ['nx', 'turbo', 'lerna', 'rush', 'bazel', 'bazel-module', 'pants']);

        if (count($primaryTools) < 2) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_MONOREPO_MIXED_TOOLS,
            severity: Severity::INFO,
            message: 'Multiple monorepo orchestration tools were detected',
            module: ModuleName::MONOREPO,
            file: $tools[array_values($primaryTools)[0]],
            context: ['tools' => array_values($primaryTools)],
        ));
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function checkLockfiles(IssueCollection $issues, ProjectFiles $files, array $package, MonorepoOptions $options): void
    {
        if (! $files->exists('pnpm-workspace.yaml') && ! $this->hasPackageWorkspaces($package)) {
            return;
        }

        if ($files->exists('package-lock.json') || $files->exists('npm-shrinkwrap.json') || $files->exists('yarn.lock') || $files->exists('pnpm-lock.yaml') || $files->exists('bun.lock') || $files->exists('bun.lockb')) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_MONOREPO_LOCK_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'Workspace metadata exists but no JavaScript package manager lockfile was found',
            module: ModuleName::MONOREPO,
            file: $files->exists('pnpm-workspace.yaml') ? 'pnpm-workspace.yaml' : 'package.json',
        ));
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function checkRootScripts(IssueCollection $issues, array $package): void
    {
        $scripts = $package['scripts'] ?? [];

        if (! is_array($scripts)) {
            return;
        }

        foreach ($scripts as $name => $script) {
            if (! is_string($name) || ! is_string($script) || preg_match('/\b(curl|wget|bash|sh)\b.*https?:\/\//i', $script) !== 1) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_MONOREPO_RISKY_ROOT_SCRIPT,
                severity: Severity::WARNING,
                message: 'Root package script downloads or executes remote shell content',
                module: ModuleName::MONOREPO,
                file: 'package.json',
                key: $name,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function hasPackageWorkspaces(array $package): bool
    {
        return is_array($package['workspaces'] ?? null);
    }
}
