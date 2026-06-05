<?php

declare(strict_types=1);

namespace DevDoctor\Core;

use JsonException;

final readonly class ProjectFiles
{
    private PathResolver $paths;

    public function __construct(string $path)
    {
        $this->paths = PathResolver::fromBasePath($path);
    }

    public function exists(string $file): bool
    {
        return is_file($this->paths->absolute($file));
    }

    public function existsIn(string $directory, string $file): bool
    {
        return $this->exists(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$file);
    }

    /**
     * @param  list<string>  $files
     */
    public function firstExisting(array $files): ?string
    {
        foreach ($files as $file) {
            if ($this->exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    public function existing(array $files): array
    {
        return array_values(array_filter($files, fn (string $file): bool => $this->exists($file)));
    }

    /**
     * @return array<string, mixed>
     */
    public function json(string $file): array
    {
        if (! $this->exists($file)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($this->paths->absolute($file)), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    public function contains(string $file, string $needle): bool
    {
        return str_contains($this->contents($file), $needle);
    }

    public function contents(string $file): string
    {
        if (! $this->exists($file)) {
            return '';
        }

        return (string) file_get_contents($this->paths->absolute($file));
    }

    /**
     * @return list<string>
     */
    public function glob(string $pattern): array
    {
        $matches = glob($this->paths->absolute($pattern));

        if ($matches === false) {
            return [];
        }

        return array_values(array_map(
            fn (string $file): string => $this->paths->display($file),
            array_filter($matches, 'is_file'),
        ));
    }
}
