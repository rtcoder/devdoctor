<?php

declare(strict_types=1);

namespace DevDoctor\Core;

enum Severity: string
{
    case ERROR = 'error';

    case WARNING = 'warning';

    case INFO = 'info';

    public function rank(): int
    {
        return match ($this) {
            self::ERROR => 0,
            self::WARNING => 1,
            self::INFO => 2,
        };
    }
}
