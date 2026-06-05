<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Cache;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;

final readonly class CacheAnalyzer
{
    /**
     * @var list<string>
     */
    private const CACHE_DIRECTORIES = [
        'bootstrap/cache',
        'storage/framework/cache',
        'storage/framework/views',
        'var/cache',
        '.next/cache',
        'node_modules/.cache',
        '.turbo/cache',
        '.vite',
    ];

    /**
     * @var list<string>
     */
    private const LARAVEL_ARTIFACTS = [
        'bootstrap/cache/config.php',
        'bootstrap/cache/events.php',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/routes-v7.php',
        'bootstrap/cache/routes.php',
        'bootstrap/cache/services.php',
    ];

    public function analyze(CacheOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $detected = false;

        foreach (self::CACHE_DIRECTORIES as $directory) {
            $absolute = $paths->absolute($directory);

            if (! is_dir($absolute)) {
                continue;
            }

            $detected = true;
            $this->checkWritable($issues, $absolute, $directory, $options);
            $this->checkSize($issues, $absolute, $directory, $options);
        }

        foreach (self::LARAVEL_ARTIFACTS as $artifact) {
            if (! is_file($paths->absolute($artifact))) {
                continue;
            }

            $detected = true;
            $issues->add(new Issue(
                code: IssueCode::DD_CACHE_LARAVEL_ARTIFACT,
                severity: Severity::INFO,
                message: 'Laravel cache artifact exists: '.$artifact,
                module: ModuleName::CACHE,
                file: $artifact,
                context: ['suggested_command' => 'php artisan optimize:clear'],
            ));
        }

        if (! $detected) {
            $issues->add(new Issue(
                code: IssueCode::DD_CACHE_NOT_DETECTED,
                severity: Severity::INFO,
                message: 'No supported cache directories or artifacts were detected.',
                module: ModuleName::CACHE,
            ));

            return $issues;
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_CACHE_READY,
                severity: Severity::INFO,
                message: 'Cache diagnostics found no actionable issues.',
                module: ModuleName::CACHE,
            ));
        }

        return $issues;
    }

    private function checkWritable(IssueCollection $issues, string $absolute, string $directory, CacheOptions $options): void
    {
        if (is_writable($absolute)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_CACHE_DIRECTORY_NOT_WRITABLE,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'Cache directory is not writable by the current user: '.$directory,
            module: ModuleName::CACHE,
            file: $directory,
        ));
    }

    private function checkSize(IssueCollection $issues, string $absolute, string $directory, CacheOptions $options): void
    {
        if ($options->maxSizeMb <= 0) {
            return;
        }

        $bytes = $this->directorySize($absolute);
        $threshold = $options->maxSizeMb * 1024 * 1024;

        if ($bytes <= $threshold) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_CACHE_DIRECTORY_LARGE,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'Cache directory exceeds '.$options->maxSizeMb.' MiB: '.$directory,
            module: ModuleName::CACHE,
            file: $directory,
            context: [
                'size_bytes' => $bytes,
                'threshold_bytes' => $threshold,
            ],
        ));
    }

    private function directorySize(string $path): int
    {
        $bytes = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $bytes += $file->getSize();
        }

        return $bytes;
    }
}
