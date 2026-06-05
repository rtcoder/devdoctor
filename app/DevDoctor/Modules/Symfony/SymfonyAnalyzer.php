<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Symfony;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvEntry;
use DevDoctor\Modules\Env\EnvParser;

final readonly class SymfonyAnalyzer
{
    public function __construct(
        private EnvParser $envParser = new EnvParser,
    ) {}

    public function analyze(SymfonyOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;

        if (! $this->isSymfonyProject($files)) {
            $issues->add(new Issue(
                code: IssueCode::DD_SYMFONY_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Symfony project detected',
                module: ModuleName::SYMFONY,
            ));

            return $issues;
        }

        $env = $this->environment($paths);

        if ($env === []) {
            $issues->add(new Issue(
                code: IssueCode::DD_SYMFONY_ENV_MISSING,
                severity: Severity::WARNING,
                message: '.env file is missing for this Symfony project',
                module: ModuleName::SYMFONY,
                file: '.env',
            ));
        } else {
            $this->checkEnvironment($issues, $env);
        }

        $this->checkRuntimeDirectory($issues, $paths, 'var/cache', $options);
        $this->checkRuntimeDirectory($issues, $paths, 'var/log', $options);
        $this->checkRecipeDrift($issues, $files);
        $this->checkComposerScripts($issues, $files);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_SYMFONY_READY,
                severity: Severity::INFO,
                message: 'Symfony diagnostics found no actionable issues.',
                module: ModuleName::SYMFONY,
            ));
        }

        return $issues;
    }

    private function isSymfonyProject(ProjectFiles $files): bool
    {
        $composer = $files->json('composer.json');

        if ($this->hasPackage($composer, 'symfony/framework-bundle')) {
            return true;
        }

        return $files->exists('bin/console') || $files->exists('config/bundles.php');
    }

    /**
     * @param  array<string, mixed>  $composer
     */
    private function hasPackage(array $composer, string $package): bool
    {
        foreach (['require', 'require-dev'] as $section) {
            if (is_array($composer[$section] ?? null) && array_key_exists($package, $composer[$section])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, EnvEntry>
     */
    private function environment(PathResolver $paths): array
    {
        $entries = [];

        foreach (['.env', '.env.local'] as $file) {
            $absolute = $paths->absolute($file);

            if (! is_file($absolute)) {
                continue;
            }

            foreach ($this->envParser->parseFile($absolute, $file)->entries as $entry) {
                $entries[$entry->key] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, EnvEntry>  $env
     */
    private function checkEnvironment(IssueCollection $issues, array $env): void
    {
        $secret = $env['APP_SECRET'] ?? null;
        $secretValue = trim((string) $secret?->value);

        if ($secret === null || $secretValue === '' || in_array(strtolower($secretValue), ['changeme', 'change_me', 'change-me'], true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_SYMFONY_SECRET_MISSING,
                severity: Severity::ERROR,
                message: 'APP_SECRET is missing, empty, or still uses a default placeholder',
                module: ModuleName::SYMFONY,
                file: $secret?->file ?? '.env',
                line: $secret?->line,
                key: 'APP_SECRET',
            ));
        }

        $appEnv = strtolower((string) (($env['APP_ENV'] ?? null)?->value ?? ''));
        $appDebug = strtolower((string) (($env['APP_DEBUG'] ?? null)?->value ?? ''));

        if (in_array($appEnv, ['prod', 'production'], true) && in_array($appDebug, ['true', '1', 'yes', 'on'], true)) {
            $debug = $env['APP_DEBUG'] ?? null;
            $issues->add(new Issue(
                code: IssueCode::DD_SYMFONY_PROD_DEBUG,
                severity: Severity::ERROR,
                message: 'APP_DEBUG is enabled while APP_ENV is prod',
                module: ModuleName::SYMFONY,
                file: $debug?->file ?? '.env',
                line: $debug?->line,
                key: 'APP_DEBUG',
            ));
        }
    }

    private function checkRuntimeDirectory(IssueCollection $issues, PathResolver $paths, string $directory, SymfonyOptions $options): void
    {
        if (is_dir($paths->absolute($directory))) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_SYMFONY_RUNTIME_DIR_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: $directory.' directory is missing',
            module: ModuleName::SYMFONY,
            file: $directory,
        ));
    }

    private function checkRecipeDrift(IssueCollection $issues, ProjectFiles $files): void
    {
        if (! $files->exists('symfony.lock') || $files->exists('config/bundles.php')) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_SYMFONY_RECIPE_DRIFT,
            severity: Severity::WARNING,
            message: 'symfony.lock exists but config/bundles.php was not found',
            module: ModuleName::SYMFONY,
            file: 'symfony.lock',
        ));
    }

    private function checkComposerScripts(IssueCollection $issues, ProjectFiles $files): void
    {
        $composer = $files->json('composer.json');
        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];

        foreach ($scripts as $event => $commands) {
            foreach ($this->scriptLines($commands) as $command) {
                if (preg_match('/curl\s+[^|]+\|\s*(?:sh|bash)|bash\s+-c|rm\s+-rf/i', $command) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_SYMFONY_RISKY_COMPOSER_SCRIPT,
                        severity: Severity::WARNING,
                        message: 'Symfony Composer script contains shell execution that should be reviewed',
                        module: ModuleName::SYMFONY,
                        file: 'composer.json',
                        key: is_string($event) ? $event : null,
                    ));
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function scriptLines(mixed $commands): array
    {
        if (is_string($commands)) {
            return [$commands];
        }

        if (! is_array($commands)) {
            return [];
        }

        return array_values(array_filter($commands, 'is_string'));
    }
}
