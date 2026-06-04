<?php

declare(strict_types=1);

namespace DevDoctor\Core;

enum ModuleStatus: string
{
    case PASSED = 'passed';

    case WARNING = 'warning';

    case FAILED = 'failed';

    public static function fromSummary(array $summary): self
    {
        if ($summary['errors'] > 0) {
            return self::FAILED;
        }

        if ($summary['warnings'] > 0) {
            return self::WARNING;
        }

        return self::PASSED;
    }
}
