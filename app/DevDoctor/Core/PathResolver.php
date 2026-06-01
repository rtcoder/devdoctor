<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

final readonly class PathResolver
{
    public function __construct(
        private string $basePath,
    ) {}

    public static function fromBasePath(string $basePath): self
    {
        return new self(rtrim($basePath, DIRECTORY_SEPARATOR));
    }

    public function absolute(string $path): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        return $this->basePath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
    }

    public function display(string $path): string
    {
        $absolute = $this->absolute($path);
        $base = $this->basePath.DIRECTORY_SEPARATOR;

        if (str_starts_with($absolute, $base)) {
            return substr($absolute, strlen($base));
        }

        if ($absolute === $this->basePath) {
            return '.';
        }

        return $path;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Z]:[\\\\\\/]/i', $path) === 1;
    }
}
