<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

final class Severity
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const INFO = 'info';

    public static function rank(string $severity): int
    {
        return match ($severity) {
            self::ERROR => 0,
            self::WARNING => 1,
            self::INFO => 2,
            default => 3,
        };
    }
}
