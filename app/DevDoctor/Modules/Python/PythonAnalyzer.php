<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Python;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class PythonAnalyzer
{
    public function analyze(PythonOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $managers = $this->managers($files);

        if (! $this->isPythonProject($files, $managers)) {
            $issues->add(new Issue(
                code: IssueCode::DD_PYTHON_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Python project detected',
                module: ModuleName::PYTHON,
            ));

            return $issues;
        }

        $this->checkMixedManagers($issues, $managers);
        $this->checkMissingLockfiles($issues, $files, $managers);
        $this->checkSuspiciousSources($issues, $files);
        $this->checkVersionConflicts($issues, $files);
        $this->checkVirtualEnvironment($issues, $files, $options);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_PYTHON_READY,
                severity: Severity::INFO,
                message: 'Python diagnostics found no actionable issues.',
                module: ModuleName::PYTHON,
            ));
        }

        return $issues;
    }

    /**
     * @param  array<string, string>  $managers
     */
    private function isPythonProject(ProjectFiles $files, array $managers): bool
    {
        return $managers !== []
            || $files->exists('pyproject.toml')
            || $files->glob('requirements*.txt') !== [];
    }

    /**
     * @return array<string, string>
     */
    private function managers(ProjectFiles $files): array
    {
        $managers = [];

        if ($files->glob('requirements*.txt') !== []) {
            $managers['pip'] = $files->glob('requirements*.txt')[0];
        }

        foreach ([
            'poetry' => 'poetry.lock',
            'pipenv' => 'Pipfile',
            'uv' => 'uv.lock',
            'conda' => 'environment.yml',
        ] as $manager => $file) {
            if ($files->exists($file)) {
                $managers[$manager] = $file;
            }
        }

        if ($files->exists('conda-lock.yml')) {
            $managers['conda'] = 'conda-lock.yml';
        }

        return $managers;
    }

    /**
     * @param  array<string, string>  $managers
     */
    private function checkMixedManagers(IssueCollection $issues, array $managers): void
    {
        if (count($managers) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_PYTHON_MIXED_MANAGERS,
            severity: Severity::WARNING,
            message: 'Multiple Python dependency managers detected: '.implode(', ', array_keys($managers)),
            module: ModuleName::PYTHON,
            file: reset($managers) ?: null,
            context: ['managers' => array_keys($managers)],
        ));
    }

    /**
     * @param  array<string, string>  $managers
     */
    private function checkMissingLockfiles(IssueCollection $issues, ProjectFiles $files, array $managers): void
    {
        if ($files->contains('pyproject.toml', '[tool.poetry]') && ! $files->exists('poetry.lock')) {
            $issues->add($this->missingLock('poetry.lock', 'pyproject.toml'));
        }

        if ($files->contains('pyproject.toml', '[tool.uv]') && ! $files->exists('uv.lock')) {
            $issues->add($this->missingLock('uv.lock', 'pyproject.toml'));
        }

        if (array_key_exists('pipenv', $managers) && ! $files->exists('Pipfile.lock')) {
            $issues->add($this->missingLock('Pipfile.lock', 'Pipfile'));
        }
    }

    private function missingLock(string $lockfile, string $manifest): Issue
    {
        return new Issue(
            code: IssueCode::DD_PYTHON_LOCK_MISSING,
            severity: Severity::WARNING,
            message: $manifest.' is present but '.$lockfile.' is missing',
            module: ModuleName::PYTHON,
            file: $manifest,
            key: $lockfile,
        );
    }

    private function checkSuspiciousSources(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (['requirements.txt', ...$files->glob('requirements*.txt')] as $file) {
            if (! $files->exists($file)) {
                continue;
            }

            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (preg_match('/(^|\\s)(--index-url|-i|--extra-index-url)\\s+http:|git\\+|https?:\\/\\//i', $line) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_PYTHON_SUSPICIOUS_SOURCE,
                        severity: Severity::WARNING,
                        message: 'Python dependency source should be reviewed before install',
                        module: ModuleName::PYTHON,
                        file: $file,
                        line: $lineNumber + 1,
                    ));
                }
            }
        }
    }

    private function checkVersionConflicts(IssueCollection $issues, ProjectFiles $files): void
    {
        $versions = [];

        if (preg_match('/requires-python\\s*=\\s*["\']([^"\']+)["\']/', $files->contents('pyproject.toml'), $match) === 1) {
            $versions['pyproject.toml'] = trim($match[1]);
        }

        if (preg_match('/python_version\\s*=\\s*["\']([^"\']+)["\']/', $files->contents('Pipfile'), $match) === 1) {
            $versions['Pipfile'] = trim($match[1]);
        }

        if (preg_match('/python\\s*=\\s*([0-9.]+)/', $files->contents('environment.yml'), $match) === 1) {
            $versions['environment.yml'] = trim($match[1]);
        }

        if (count(array_unique($versions)) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_PYTHON_VERSION_CONFLICT,
            severity: Severity::WARNING,
            message: 'Python version constraints disagree across project files',
            module: ModuleName::PYTHON,
            file: array_key_first($versions),
            context: ['versions' => $versions],
        ));
    }

    private function checkVirtualEnvironment(IssueCollection $issues, ProjectFiles $files, PythonOptions $options): void
    {
        if ($files->exists('.venv/pyvenv.cfg') || $files->exists('venv/pyvenv.cfg') || $files->exists('environment.yml')) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_PYTHON_VENV_MISSING,
            severity: $options->strict ? Severity::WARNING : Severity::INFO,
            message: 'No local Python virtual environment marker was detected',
            module: ModuleName::PYTHON,
            file: '.venv',
        ));
    }
}
