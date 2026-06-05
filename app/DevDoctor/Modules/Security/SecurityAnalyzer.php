<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Security;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvParser;
use DevDoctor\Modules\Env\SecretScanner;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class SecurityAnalyzer
{
    private const IGNORED_DIRECTORIES = ['.git', 'vendor', 'node_modules', 'builds', 'storage'];

    public function __construct(
        private EnvParser $envParser = new EnvParser,
        private SecretScanner $secretScanner = new SecretScanner,
    ) {}

    public function analyze(SecurityOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;

        $this->checkGitignore($issues, $paths);
        $this->checkEnvExamples($issues, $paths);
        $this->checkComposerScripts($issues, $paths);
        $this->checkPackageScripts($issues, $paths);
        $this->checkComposeSecurity($issues, $paths);
        $this->scanProjectFiles($issues, $paths, $options);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_SECURITY_READY,
                severity: Severity::INFO,
                message: 'Security diagnostics found no actionable issues.',
                module: ModuleName::SECURITY,
            ));
        }

        return $issues;
    }

    private function checkGitignore(IssueCollection $issues, PathResolver $paths): void
    {
        $gitignore = $paths->absolute('.gitignore');

        if (! is_file($gitignore)) {
            return;
        }

        $contents = (string) file_get_contents($gitignore);

        if (preg_match('/(^|\R)\s*\.env(\.\*)?\s*($|\R)/', $contents) === 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_SECURITY_ENV_NOT_IGNORED,
            severity: Severity::WARNING,
            message: '.gitignore does not explicitly ignore .env files',
            module: ModuleName::SECURITY,
            file: '.gitignore',
        ));
    }

    private function checkEnvExamples(IssueCollection $issues, PathResolver $paths): void
    {
        foreach (['.env.example', '.env.dist', '.env.sample'] as $file) {
            $absolute = $paths->absolute($file);

            if (! is_file($absolute)) {
                continue;
            }

            foreach ($this->envParser->parseFile($absolute, $file)->entries as $entry) {
                if (! $this->secretScanner->isSuspicious($entry)) {
                    continue;
                }

                $issues->add(new Issue(
                    code: IssueCode::DD_SECURITY_SECRET_IN_EXAMPLE,
                    severity: Severity::ERROR,
                    message: 'Likely secret value appears in '.$file,
                    module: ModuleName::SECURITY,
                    file: $file,
                    line: $entry->line,
                    key: $entry->key,
                ));
            }
        }
    }

    private function checkComposerScripts(IssueCollection $issues, PathResolver $paths): void
    {
        $composerJson = $paths->absolute('composer.json');
        $data = $this->json($composerJson);

        if ($data === null || ! is_array($data['scripts'] ?? null)) {
            return;
        }

        foreach ($data['scripts'] as $event => $commands) {
            $commands = is_array($commands) ? $commands : [$commands];

            foreach ($commands as $command) {
                if (! is_string($event) || ! is_string($command) || ! $this->isRiskyShell($command)) {
                    continue;
                }

                $issues->add(new Issue(
                    code: IssueCode::DD_SECURITY_RISKY_COMPOSER_SCRIPT,
                    severity: Severity::WARNING,
                    message: 'Composer script '.$event.' contains risky shell execution',
                    module: ModuleName::SECURITY,
                    file: 'composer.json',
                    key: 'scripts.'.$event,
                ));
            }
        }
    }

    private function checkPackageScripts(IssueCollection $issues, PathResolver $paths): void
    {
        $packageJson = $paths->absolute('package.json');
        $data = $this->json($packageJson);

        if ($data === null || ! is_array($data['scripts'] ?? null)) {
            return;
        }

        foreach ($data['scripts'] as $name => $command) {
            if (! is_string($name) || ! is_string($command) || ! $this->isRiskyShell($command)) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_SECURITY_RISKY_PACKAGE_SCRIPT,
                severity: Severity::WARNING,
                message: 'package.json script '.$name.' contains risky shell execution',
                module: ModuleName::SECURITY,
                file: 'package.json',
                key: 'scripts.'.$name,
            ));
        }
    }

    private function checkComposeSecurity(IssueCollection $issues, PathResolver $paths): void
    {
        foreach (['docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml'] as $file) {
            $absolute = $paths->absolute($file);

            if (! is_file($absolute)) {
                continue;
            }

            $contents = (string) file_get_contents($absolute);

            if (preg_match('/privileged\s*:\s*true/i', $contents) === 1) {
                $issues->add(new Issue(
                    code: IssueCode::DD_SECURITY_DOCKER_PRIVILEGED,
                    severity: Severity::WARNING,
                    message: 'Compose file enables privileged mode',
                    module: ModuleName::SECURITY,
                    file: $file,
                    key: 'privileged',
                ));
            }

            if (str_contains($contents, '/var/run/docker.sock')) {
                $issues->add(new Issue(
                    code: IssueCode::DD_SECURITY_DOCKER_SOCKET_MOUNT,
                    severity: Severity::WARNING,
                    message: 'Compose file mounts the Docker socket',
                    module: ModuleName::SECURITY,
                    file: $file,
                    key: '/var/run/docker.sock',
                ));
            }
        }
    }

    private function scanProjectFiles(IssueCollection $issues, PathResolver $paths, SecurityOptions $options): void
    {
        $base = $paths->absolute('.');

        if (! is_dir($base)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relative = $paths->display($file->getPathname());

            if ($this->ignored($relative) || $file->getSize() > $options->maxFileSizeBytes || ! $this->scannable($relative)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if ($this->containsLikelySecret($contents)) {
                $issues->add(new Issue(
                    code: IssueCode::DD_SECURITY_SECRET_PATTERN,
                    severity: Severity::WARNING,
                    message: 'File contains a likely hard-coded secret pattern',
                    module: ModuleName::SECURITY,
                    file: $relative,
                ));
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function json(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function ignored(string $relative): bool
    {
        foreach (self::IGNORED_DIRECTORIES as $directory) {
            if ($relative === $directory || str_starts_with($relative, $directory.DIRECTORY_SEPARATOR) || str_starts_with($relative, $directory.'/')) {
                return true;
            }
        }

        return false;
    }

    private function scannable(string $relative): bool
    {
        return preg_match('/\.(env|ya?ml|json|ini|conf|txt|md|xml|php|js|ts)$/i', $relative) === 1
            || in_array(basename($relative), ['.env.example', '.env.dist', '.env.sample'], true);
    }

    private function containsLikelySecret(string $contents): bool
    {
        return preg_match('/["\']?(api[_-]?key|secret|token|password)["\']?\s*(=>|:|=)\s*["\']?[A-Za-z0-9+\/_.=-]{24,}/i', $contents) === 1
            || preg_match('/-----BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY-----/', $contents) === 1;
    }

    private function isRiskyShell(string $command): bool
    {
        return preg_match('/(curl|wget).*(\||sh|bash)|\|\s*(sh|bash)|rm\s+-rf/i', $command) === 1;
    }
}
