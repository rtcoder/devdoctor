<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Laravel;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvEntry;
use DevDoctor\Modules\Env\EnvFile;
use DevDoctor\Modules\Env\EnvParser;
use JsonException;

final readonly class LaravelAnalyzer
{
    public function __construct(
        private EnvParser $envParser = new EnvParser,
    ) {}

    public function analyze(LaravelOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;

        if (! $this->isLaravelProject($paths)) {
            $issues->add(new Issue(
                code: IssueCode::DD_LARAVEL_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Laravel project detected',
                module: 'laravel',
            ));

            return $issues;
        }

        $env = $this->env($paths);

        if ($env === null) {
            $issues->add(new Issue(
                code: IssueCode::DD_LARAVEL_ENV_MISSING,
                severity: Severity::WARNING,
                message: '.env file is missing for this Laravel project',
                module: 'laravel',
                file: '.env',
            ));
        } else {
            $this->checkEnv($issues, $env);
        }

        $this->checkWritableDirectory($issues, $paths, 'storage', $options);
        $this->checkWritableDirectory($issues, $paths, 'bootstrap/cache', $options);
        $this->checkCachedConfig($issues, $paths);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_LARAVEL_READY,
                severity: Severity::INFO,
                message: 'Laravel diagnostics found no actionable issues.',
                module: 'laravel',
            ));
        }

        return $issues;
    }

    private function isLaravelProject(PathResolver $paths): bool
    {
        if (is_file($paths->absolute('artisan'))) {
            return true;
        }

        $composerJson = $paths->absolute('composer.json');

        if (! is_file($composerJson)) {
            return false;
        }

        try {
            $data = json_decode((string) file_get_contents($composerJson), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        $require = is_array($data) && is_array($data['require'] ?? null) ? $data['require'] : [];

        return array_key_exists('laravel/framework', $require);
    }

    private function env(PathResolver $paths): ?EnvFile
    {
        $path = $paths->absolute('.env');

        return is_file($path) ? $this->envParser->parseFile($path, '.env') : null;
    }

    private function checkEnv(IssueCollection $issues, EnvFile $env): void
    {
        $appKey = $this->envValue($env, 'APP_KEY');

        if ($appKey === null || trim($appKey->value) === '') {
            $issues->add(new Issue(
                code: IssueCode::DD_LARAVEL_APP_KEY_MISSING,
                severity: Severity::ERROR,
                message: 'APP_KEY is missing or empty',
                module: 'laravel',
                file: '.env',
                key: 'APP_KEY',
            ));
        }

        $appEnv = strtolower((string) ($this->envValue($env, 'APP_ENV')?->value ?? ''));
        $appDebug = strtolower((string) ($this->envValue($env, 'APP_DEBUG')?->value ?? ''));

        if ($appEnv === 'production' && in_array($appDebug, ['true', '1', 'yes', 'on'], true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_LARAVEL_PROD_DEBUG,
                severity: Severity::ERROR,
                message: 'APP_DEBUG is enabled while APP_ENV is production',
                module: 'laravel',
                file: '.env',
                key: 'APP_DEBUG',
            ));
        }

        $appUrl = trim((string) ($this->envValue($env, 'APP_URL')?->value ?? ''));

        if ($appUrl === '' || in_array($appUrl, ['http://localhost', 'https://localhost'], true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_LARAVEL_APP_URL_DEFAULT,
                severity: Severity::WARNING,
                message: 'APP_URL is missing or still uses the default localhost value',
                module: 'laravel',
                file: '.env',
                key: 'APP_URL',
            ));
        }
    }

    private function envValue(EnvFile $env, string $key): ?EnvEntry
    {
        return $env->get($key);
    }

    private function checkWritableDirectory(IssueCollection $issues, PathResolver $paths, string $directory, LaravelOptions $options): void
    {
        $absolute = $paths->absolute($directory);

        if (! is_dir($absolute)) {
            $issues->add(new Issue(
                code: IssueCode::DD_LARAVEL_DIRECTORY_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: $directory.' directory is missing',
                module: 'laravel',
                file: $directory,
            ));

            return;
        }

        if (is_writable($absolute)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_LARAVEL_DIRECTORY_NOT_WRITABLE,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: $directory.' directory is not writable by the current user',
            module: 'laravel',
            file: $directory,
        ));
    }

    private function checkCachedConfig(IssueCollection $issues, PathResolver $paths): void
    {
        if (! is_file($paths->absolute('bootstrap/cache/config.php'))) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_LARAVEL_CONFIG_CACHED,
            severity: Severity::INFO,
            message: 'Laravel config cache file exists; remember to rebuild it after environment changes',
            module: 'laravel',
            file: 'bootstrap/cache/config.php',
        ));
    }
}
