<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

final class ExitCode
{
    public const OK = 0;

    public const WARNINGS = 1;

    public const ERRORS = 2;

    public const INVALID_CONFIG = 3;

    public const MISSING_DEPENDENCY = 4;

    public const INTERNAL_ERROR = 5;

    public static function fromIssues(IssueCollection $issues): int
    {
        if ($issues->hasErrors()) {
            return self::ERRORS;
        }

        if ($issues->hasWarnings()) {
            return self::WARNINGS;
        }

        return self::OK;
    }
}
