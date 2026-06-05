<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Php;

use DevDoctor\Core\CommandAvailability;
use DevDoctor\Core\CommandAvailabilityInterface;
use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use JsonException;

final readonly class PhpAnalyzer
{
    public function __construct(
        private PhpRuntimeInterface $runtime = new NativePhpRuntime,
        private CommandAvailabilityInterface $commands = new CommandAvailability,
    ) {}

    public function analyze(PhpOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;

        if (! $this->commands->available('php')) {
            $issues->add(new Issue(
                code: IssueCode::DD_PHP_BINARY_MISSING,
                severity: Severity::ERROR,
                message: 'PHP binary was not found in PATH',
                module: ModuleName::PHP,
            ));
        }

        $composer = $this->composerRequirements($paths);

        if ($composer['invalid']) {
            $issues->add(new Issue(
                code: IssueCode::DD_PHP_COMPOSER_JSON_INVALID,
                severity: Severity::ERROR,
                message: 'composer.json could not be parsed for PHP diagnostics',
                module: ModuleName::PHP,
                file: 'composer.json',
            ));
        }

        $this->checkPhpVersion($issues, $composer['requirements']);
        $this->checkExtensions($issues, $composer['requirements']);
        $this->checkMemoryLimit($issues, $options);
        $this->checkIni($issues);
        $this->checkXdebug($issues, $options);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_PHP_READY,
                severity: Severity::INFO,
                message: 'PHP diagnostics found no actionable issues.',
                module: ModuleName::PHP,
            ));
        }

        return $issues;
    }

    /**
     * @return array{invalid: bool, requirements: array<string, mixed>}
     */
    private function composerRequirements(PathResolver $paths): array
    {
        $composerJson = $paths->absolute('composer.json');

        if (! is_file($composerJson)) {
            return ['invalid' => false, 'requirements' => []];
        }

        try {
            $data = json_decode((string) file_get_contents($composerJson), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['invalid' => true, 'requirements' => []];
        }

        if (! is_array($data) || ! is_array($data['require'] ?? null)) {
            return ['invalid' => false, 'requirements' => []];
        }

        return ['invalid' => false, 'requirements' => $data['require']];
    }

    /**
     * @param  array<string, mixed>  $requirements
     */
    private function checkPhpVersion(IssueCollection $issues, array $requirements): void
    {
        $constraint = $requirements['php'] ?? null;

        if (! is_string($constraint) || $this->phpVersionSatisfies($this->runtime->version(), $constraint)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_PHP_VERSION_MISMATCH,
            severity: Severity::ERROR,
            message: 'Current PHP '.$this->runtime->version().' does not satisfy composer requirement '.$constraint,
            module: ModuleName::PHP,
            file: 'composer.json',
            key: 'php',
        ));
    }

    /**
     * @param  array<string, mixed>  $requirements
     */
    private function checkExtensions(IssueCollection $issues, array $requirements): void
    {
        $loaded = array_map('strtolower', $this->runtime->loadedExtensions());

        foreach ($requirements as $package => $constraint) {
            if (! is_string($package) || ! str_starts_with($package, 'ext-')) {
                continue;
            }

            $extension = strtolower(substr($package, 4));

            if (in_array($extension, $loaded, true)) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_PHP_EXTENSION_MISSING,
                severity: Severity::ERROR,
                message: 'Required PHP extension '.$package.' is not loaded',
                module: ModuleName::PHP,
                file: 'composer.json',
                key: $package,
                context: ['constraint' => $constraint],
            ));
        }
    }

    private function checkMemoryLimit(IssueCollection $issues, PhpOptions $options): void
    {
        $value = $this->runtime->iniValue('memory_limit');

        if (! is_string($value) || trim($value) === '' || trim($value) === '-1') {
            return;
        }

        $megabytes = $this->memoryToMegabytes($value);

        if ($megabytes === null || $megabytes >= $options->minimumMemoryMb) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_PHP_MEMORY_LIMIT_LOW,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'PHP memory_limit '.$value.' is below '.$options->minimumMemoryMb.'M',
            module: ModuleName::PHP,
            key: 'memory_limit',
            context: ['minimum_mb' => $options->minimumMemoryMb],
        ));
    }

    private function checkIni(IssueCollection $issues): void
    {
        if ($this->runtime->iniFile() !== false) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_PHP_INI_MISSING,
            severity: Severity::INFO,
            message: 'No php.ini file is loaded by the current PHP runtime',
            module: ModuleName::PHP,
        ));
    }

    private function checkXdebug(IssueCollection $issues, PhpOptions $options): void
    {
        if (! $options->ci || ! $this->runtime->xdebugEnabled()) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_PHP_XDEBUG_ENABLED_IN_CI,
            severity: Severity::WARNING,
            message: 'Xdebug is enabled while running in CI mode',
            module: ModuleName::PHP,
            key: 'xdebug',
        ));
    }

    private function memoryToMegabytes(string $value): ?int
    {
        if (preg_match('/^\s*(\d+)\s*([kmgt]?)\s*$/i', $value, $matches) !== 1) {
            return null;
        }

        $amount = (int) $matches[1];

        return match (strtolower($matches[2] ?? '')) {
            'k' => (int) ceil($amount / 1024),
            'g' => $amount * 1024,
            't' => $amount * 1024 * 1024,
            default => $amount,
        };
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
}
