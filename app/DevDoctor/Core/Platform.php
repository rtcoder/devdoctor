<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

enum Platform: string
{
    case LINUX = 'linux';
    case MACOS = 'macos';
    case WINDOWS = 'windows';
    case OTHER = 'other';

    public static function current(): self
    {
        return self::fromOsFamily(PHP_OS_FAMILY);
    }

    public static function fromOsFamily(string $family): self
    {
        return match ($family) {
            'Linux' => self::LINUX,
            'Darwin' => self::MACOS,
            'Windows' => self::WINDOWS,
            default => self::OTHER,
        };
    }

    public function isUnix(): bool
    {
        return $this === self::LINUX || $this === self::MACOS;
    }
}
