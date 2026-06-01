<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Composer;

use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\PathResolver;
use App\DevDoctor\Core\ProcessRunner;
use App\DevDoctor\Core\Severity;
use JsonException;

final readonly class ComposerAnalyzer
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
    ) {}

    public function analyze(ComposerOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $composerJson = $paths->absolute('composer.json');

        if (! is_file($composerJson)) {
            $issues->add(new Issue(
                code: 'DD_COMPOSER_NOT_PROJECT',
                severity: Severity::INFO,
                message: 'No composer.json detected',
                module: 'composer',
            ));

            return $issues;
        }

        try {
            $data = json_decode((string) file_get_contents($composerJson), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $issues->add(new Issue(
                code: 'DD_COMPOSER_JSON_INVALID',
                severity: Severity::ERROR,
                message: $exception->getMessage(),
                module: 'composer',
                file: 'composer.json',
            ));

            return $issues;
        }

        if (! is_array($data)) {
            $issues->add(new Issue(
                code: 'DD_COMPOSER_JSON_INVALID',
                severity: Severity::ERROR,
                message: 'composer.json must contain a JSON object',
                module: 'composer',
                file: 'composer.json',
            ));

            return $issues;
        }

        $this->checkLockFile($issues, $paths, $data);
        $this->checkVendor($issues, $paths, $options);
        if ($options->platformCheck) {
            $this->checkPhpVersion($issues, $data);
            $this->checkExtensions($issues, $data);
        }
        $this->checkAbandonedPackages($issues, $paths);

        if ($options->scripts) {
            $this->checkRiskyScripts($issues, $data);
        }

        if ($options->validate) {
            $this->runComposerValidate($issues, $options->path);
        }

        if ($issues->summary() === ['errors' => 0, 'warnings' => 0, 'info' => 0]) {
            $issues->add(new Issue(
                code: 'DD_COMPOSER_READY',
                severity: Severity::INFO,
                message: 'Composer diagnostics found no issues.',
                module: 'composer',
            ));
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkLockFile(IssueCollection $issues, PathResolver $paths, array $data): void
    {
        if (is_file($paths->absolute('composer.lock')) || ! $this->hasDependencies($data)) {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_COMPOSER_LOCK_MISSING',
            severity: Severity::WARNING,
            message: 'composer.lock is missing while composer.json declares dependencies',
            module: 'composer',
            file: 'composer.lock',
        ));
    }

    private function checkVendor(IssueCollection $issues, PathResolver $paths, ComposerOptions $options): void
    {
        if (is_dir($paths->absolute('vendor'))) {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_COMPOSER_VENDOR_MISSING',
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'vendor directory is missing',
            module: 'composer',
            file: 'vendor',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkPhpVersion(IssueCollection $issues, array $data): void
    {
        $require = $this->requirements($data);
        $constraint = $require['php'] ?? null;

        if (! is_string($constraint) || $this->phpVersionSatisfies(PHP_VERSION, $constraint)) {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_COMPOSER_PHP_VERSION_MISMATCH',
            severity: Severity::ERROR,
            message: 'Current PHP '.PHP_VERSION.' does not satisfy composer requirement '.$constraint,
            module: 'composer',
            file: 'composer.json',
            key: 'php',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkExtensions(IssueCollection $issues, array $data): void
    {
        foreach ($this->requirements($data) as $package => $constraint) {
            if (! is_string($package) || ! str_starts_with($package, 'ext-')) {
                continue;
            }

            $extension = substr($package, 4);

            if (extension_loaded($extension)) {
                continue;
            }

            $issues->add(new Issue(
                code: 'DD_COMPOSER_EXTENSION_MISSING',
                severity: Severity::ERROR,
                message: 'Required PHP extension '.$package.' is not loaded',
                module: 'composer',
                file: 'composer.json',
                key: $package,
                context: ['constraint' => $constraint],
            ));
        }
    }

    private function checkAbandonedPackages(IssueCollection $issues, PathResolver $paths): void
    {
        $installedJson = $paths->absolute('vendor/composer/installed.json');

        if (! is_file($installedJson)) {
            return;
        }

        try {
            $data = json_decode((string) file_get_contents($installedJson), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        $packages = $data['packages'] ?? $data;

        if (! is_array($packages)) {
            return;
        }

        foreach ($packages as $package) {
            if (! is_array($package) || ! array_key_exists('abandoned', $package) || ($package['abandoned'] ?? false) === false) {
                continue;
            }

            $name = is_string($package['name'] ?? null) ? $package['name'] : 'unknown package';
            $replacement = is_string($package['abandoned']) ? ' Use '.$package['abandoned'].' instead.' : '';

            $issues->add(new Issue(
                code: 'DD_COMPOSER_PACKAGE_ABANDONED',
                severity: Severity::WARNING,
                message: $name.' is marked as abandoned.'.$replacement,
                module: 'composer',
                file: 'vendor/composer/installed.json',
                key: $name,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkRiskyScripts(IssueCollection $issues, array $data): void
    {
        $scripts = $data['scripts'] ?? [];

        if (! is_array($scripts)) {
            return;
        }

        foreach (['post-install-cmd', 'post-update-cmd'] as $event) {
            $commands = $scripts[$event] ?? [];
            $commands = is_array($commands) ? $commands : [$commands];

            foreach ($commands as $command) {
                if (! is_string($command) || ! $this->isRiskyScript($command)) {
                    continue;
                }

                $issues->add(new Issue(
                    code: 'DD_COMPOSER_SCRIPT_RISKY',
                    severity: Severity::WARNING,
                    message: $event.' contains risky shell execution',
                    module: 'composer',
                    file: 'composer.json',
                    key: $event,
                ));
            }
        }
    }

    private function runComposerValidate(IssueCollection $issues, string $path): void
    {
        if (! $this->processRunner->run(['which', 'composer'], $path)->successful()) {
            $issues->add(new Issue(
                code: 'DD_COMPOSER_BINARY_MISSING',
                severity: Severity::WARNING,
                message: 'Composer binary was not found; skipped composer validate',
                module: 'composer',
            ));

            return;
        }

        $result = $this->processRunner->run(['composer', 'validate', '--no-check-publish', '--no-interaction'], $path);

        if ($result->successful()) {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_COMPOSER_VALIDATE_FAILED',
            severity: Severity::ERROR,
            message: trim($result->stderr) !== '' ? trim($result->stderr) : 'composer validate failed',
            module: 'composer',
            file: 'composer.json',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasDependencies(array $data): bool
    {
        return ($data['require'] ?? []) !== [] || ($data['require-dev'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requirements(array $data): array
    {
        $require = $data['require'] ?? [];

        return is_array($require) ? $require : [];
    }

    private function phpVersionSatisfies(string $version, string $constraint): bool
    {
        foreach (explode('|', $constraint) as $part) {
            $part = trim($part);

            if ($part === '' || $part === '*') {
                return true;
            }

            if (str_starts_with($part, '^') && $this->satisfiesCaret($version, substr($part, 1))) {
                return true;
            }

            if (str_starts_with($part, '>=') && version_compare($version, substr($part, 2), '>=')) {
                return true;
            }

            if (preg_match('/^\d+(\.\d+){0,2}$/', $part) === 1 && version_compare($version, $part, '>=')) {
                return true;
            }
        }

        return false;
    }

    private function satisfiesCaret(string $version, string $base): bool
    {
        $major = (int) explode('.', $base)[0];

        return version_compare($version, $base, '>=')
            && version_compare($version, (string) ($major + 1).'.0.0', '<');
    }

    private function isRiskyScript(string $command): bool
    {
        return preg_match('/(curl|wget).*(\||sh|bash)|\|\s*(sh|bash)|rm\s+-rf/i', $command) === 1;
    }
}
