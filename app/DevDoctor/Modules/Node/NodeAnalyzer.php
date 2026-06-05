<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Node;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use JsonException;

final readonly class NodeAnalyzer
{
    private const LOCKFILES = [
        'package-lock.json' => 'npm',
        'npm-shrinkwrap.json' => 'npm',
        'pnpm-lock.yaml' => 'pnpm',
        'yarn.lock' => 'yarn',
        'bun.lock' => 'bun',
        'bun.lockb' => 'bun',
    ];

    public function __construct(
        private NodeRuntimeInterface $runtime = new ProcessNodeRuntime,
    ) {}

    public function analyze(NodeOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;

        if (! $this->isNodeProject($paths)) {
            $issues->add(new Issue(
                code: IssueCode::DD_NODE_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Node.js project detected',
                module: ModuleName::NODE,
            ));

            return $issues;
        }

        $package = $this->packageJson($paths);

        if ($package['invalid']) {
            $issues->add(new Issue(
                code: IssueCode::DD_NODE_PACKAGE_JSON_INVALID,
                severity: Severity::ERROR,
                message: 'package.json could not be parsed',
                module: ModuleName::NODE,
                file: 'package.json',
            ));

            return $issues;
        }

        $data = $package['data'];
        $lockfiles = $this->lockfiles($paths);
        $hasDependencies = $this->hasDependencies($data);

        if (! $this->runtime->available()) {
            $issues->add(new Issue(
                code: IssueCode::DD_NODE_BINARY_MISSING,
                severity: Severity::WARNING,
                message: 'Node.js binary was not found in PATH',
                module: ModuleName::NODE,
            ));
        }

        $this->checkLockfiles($issues, $lockfiles, $hasDependencies);
        $this->checkPackageManager($issues, $data, $lockfiles);
        $this->checkNodeModules($issues, $paths, $options, $hasDependencies);
        $this->checkVersion($issues, $paths, $data, $options);
        $this->checkRiskyScripts($issues, $data);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_NODE_READY,
                severity: Severity::INFO,
                message: 'Node.js diagnostics found no actionable issues.',
                module: ModuleName::NODE,
            ));
        }

        return $issues;
    }

    private function isNodeProject(PathResolver $paths): bool
    {
        if (is_file($paths->absolute('package.json'))) {
            return true;
        }

        foreach (array_keys(self::LOCKFILES) as $lockfile) {
            if (is_file($paths->absolute($lockfile))) {
                return true;
            }
        }

        return is_file($paths->absolute('.nvmrc')) || is_file($paths->absolute('.node-version'));
    }

    /**
     * @return array{invalid: bool, data: array<string, mixed>}
     */
    private function packageJson(PathResolver $paths): array
    {
        $packageJson = $paths->absolute('package.json');

        if (! is_file($packageJson)) {
            return ['invalid' => false, 'data' => []];
        }

        try {
            $data = json_decode((string) file_get_contents($packageJson), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['invalid' => true, 'data' => []];
        }

        return ['invalid' => ! is_array($data), 'data' => is_array($data) ? $data : []];
    }

    /**
     * @return array<string, string>
     */
    private function lockfiles(PathResolver $paths): array
    {
        $found = [];

        foreach (self::LOCKFILES as $file => $manager) {
            if (is_file($paths->absolute($file))) {
                $found[$file] = $manager;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasDependencies(array $data): bool
    {
        foreach (['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies'] as $key) {
            if (is_array($data[$key] ?? null) && $data[$key] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $lockfiles
     */
    private function checkLockfiles(IssueCollection $issues, array $lockfiles, bool $hasDependencies): void
    {
        if (count($lockfiles) > 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_NODE_MULTIPLE_LOCKFILES,
                severity: Severity::WARNING,
                message: 'Multiple Node.js lockfiles are present: '.implode(', ', array_keys($lockfiles)),
                module: ModuleName::NODE,
                file: array_key_first($lockfiles),
            ));
        }

        if ($hasDependencies && $lockfiles === []) {
            $issues->add(new Issue(
                code: IssueCode::DD_NODE_LOCK_MISSING,
                severity: Severity::WARNING,
                message: 'package.json declares dependencies but no supported lockfile was found',
                module: ModuleName::NODE,
                file: 'package.json',
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $lockfiles
     */
    private function checkPackageManager(IssueCollection $issues, array $data, array $lockfiles): void
    {
        $declared = $this->declaredPackageManager($data);

        if ($declared === null || $lockfiles === []) {
            return;
        }

        if (in_array($declared, array_values($lockfiles), true)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_NODE_PACKAGE_MANAGER_MISMATCH,
            severity: Severity::WARNING,
            message: 'packageManager declares '.$declared.' but lockfiles indicate '.implode(', ', array_unique(array_values($lockfiles))),
            module: ModuleName::NODE,
            file: 'package.json',
            key: 'packageManager',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function declaredPackageManager(array $data): ?string
    {
        $packageManager = $data['packageManager'] ?? null;

        if (! is_string($packageManager) || ! str_contains($packageManager, '@')) {
            return null;
        }

        return strtolower(strtok($packageManager, '@') ?: '');
    }

    private function checkNodeModules(IssueCollection $issues, PathResolver $paths, NodeOptions $options, bool $hasDependencies): void
    {
        if (! $hasDependencies || is_dir($paths->absolute('node_modules'))) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_NODE_MODULES_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'node_modules directory is missing',
            module: ModuleName::NODE,
            file: 'node_modules',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkVersion(IssueCollection $issues, PathResolver $paths, array $data, NodeOptions $options): void
    {
        $requirements = $this->nodeRequirements($paths, $data);

        if (count(array_unique(array_values($requirements))) > 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_NODE_VERSION_FILE_CONFLICT,
                severity: Severity::WARNING,
                message: 'Node.js version requirements disagree across project files',
                module: ModuleName::NODE,
                file: array_key_first($requirements),
            ));
        }

        $constraint = $requirements['package.json'] ?? reset($requirements);

        if (! is_string($constraint) || $constraint === '') {
            return;
        }

        $version = $this->runtime->version($options->path);

        if ($version === null || $this->nodeVersionSatisfies($version, $constraint)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_NODE_VERSION_MISMATCH,
            severity: Severity::ERROR,
            message: 'Current Node.js '.$version.' does not satisfy project requirement '.$constraint,
            module: ModuleName::NODE,
            file: array_key_exists('package.json', $requirements) ? 'package.json' : array_key_first($requirements),
            key: array_key_exists('package.json', $requirements) ? 'engines.node' : 'node',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function nodeRequirements(PathResolver $paths, array $data): array
    {
        $requirements = [];
        $engine = $data['engines']['node'] ?? null;

        if (is_string($engine) && trim($engine) !== '') {
            $requirements['package.json'] = trim($engine);
        }

        foreach (['.nvmrc', '.node-version'] as $file) {
            $path = $paths->absolute($file);

            if (is_file($path)) {
                $value = trim((string) file_get_contents($path));

                if ($value !== '') {
                    $requirements[$file] = ltrim($value, 'vV');
                }
            }
        }

        return $requirements;
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

        foreach ($scripts as $name => $command) {
            if (! is_string($name) || ! is_string($command) || ! $this->isRiskyScript($command)) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_NODE_SCRIPT_RISKY,
                severity: Severity::WARNING,
                message: 'package.json script '.$name.' contains risky shell execution',
                module: ModuleName::NODE,
                file: 'package.json',
                key: 'scripts.'.$name,
            ));
        }
    }

    private function nodeVersionSatisfies(string $version, string $constraint): bool
    {
        foreach (explode('||', str_replace('|', '||', $constraint)) as $part) {
            $part = trim($part);

            if ($part === '' || $part === '*') {
                return true;
            }

            if ($this->satisfiesAllNodeConstraints($version, preg_split('/\s+/', $part) ?: [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function satisfiesAllNodeConstraints(string $version, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! $this->satisfiesNodeConstraint($version, $token)) {
                return false;
            }
        }

        return $tokens !== [];
    }

    private function satisfiesNodeConstraint(string $version, string $token): bool
    {
        $token = trim($token);

        if ($token === '' || $token === '*') {
            return true;
        }

        if (str_starts_with($token, '^')) {
            return $this->satisfiesCaret($version, substr($token, 1));
        }

        foreach (['>=', '<=', '>', '<'] as $operator) {
            if (str_starts_with($token, $operator)) {
                return version_compare($version, substr($token, strlen($operator)), $operator);
            }
        }

        if (preg_match('/^\d+(\.\d+){0,2}$/', $token) === 1) {
            return $this->satisfiesCaret($version, $token);
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
