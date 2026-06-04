<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Presets;

use App\DevDoctor\Core\PathResolver;
use JsonException;

final readonly class PresetDetector
{
    /**
     * @return list<PresetMatch>
     */
    public function detect(string $path): array
    {
        $paths = PathResolver::fromBasePath($path);
        $composer = $this->jsonFile($paths->absolute('composer.json'));
        $package = $this->jsonFile($paths->absolute('package.json'));
        $matches = [];

        if ($this->hasPackage($composer, 'laravel/framework') || is_file($paths->absolute('artisan'))) {
            $matches[] = new PresetMatch(ProjectPreset::LARAVEL, $this->hasPackage($composer, 'laravel/framework') ? 'composer.json' : 'artisan');
        }

        if ($this->hasPackage($composer, 'symfony/framework-bundle') || is_file($paths->absolute('bin/console'))) {
            $matches[] = new PresetMatch(ProjectPreset::SYMFONY, $this->hasPackage($composer, 'symfony/framework-bundle') ? 'composer.json' : 'bin/console');
        }

        if (is_file($paths->absolute('package.json'))) {
            $matches[] = new PresetMatch(ProjectPreset::NODE, 'package.json');
        }

        $viteConfig = $this->firstExisting($paths, [
            'vite.config.js',
            'vite.config.mjs',
            'vite.config.ts',
            'vite.config.cjs',
        ]);

        if ($this->hasPackage($package, 'vite') || $viteConfig !== null) {
            $matches[] = new PresetMatch(ProjectPreset::VITE, $this->hasPackage($package, 'vite') ? 'package.json' : (string) $viteConfig);
        }

        if ($this->hasPackage($package, 'next')) {
            $matches[] = new PresetMatch(ProjectPreset::NEXTJS, 'package.json');
        }

        $composeFile = $this->firstExisting($paths, [
            'docker-compose.yml',
            'docker-compose.yaml',
            'compose.yml',
            'compose.yaml',
        ]);

        if ($composeFile !== null) {
            $matches[] = new PresetMatch(ProjectPreset::DOCKER_COMPOSE, $composeFile);
        }

        return $matches;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonFile(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasPackage(array $data, string $package): bool
    {
        foreach (['require', 'require-dev', 'dependencies', 'devDependencies'] as $section) {
            if (is_array($data[$section] ?? null) && array_key_exists($package, $data[$section])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $files
     */
    private function firstExisting(PathResolver $paths, array $files): ?string
    {
        foreach ($files as $file) {
            if (is_file($paths->absolute($file))) {
                return $file;
            }
        }

        return null;
    }
}
