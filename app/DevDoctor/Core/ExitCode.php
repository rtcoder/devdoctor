<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

enum ExitCode: int
{
    case OK = 0;

    case WARNINGS = 1;

    case ERRORS = 2;

    case INVALID_CONFIG = 3;

    case MISSING_DEPENDENCY = 4;

    case INTERNAL_ERROR = 5;

    public static function fromIssues(IssueCollection $issues): self
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
