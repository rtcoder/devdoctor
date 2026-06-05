<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class IssueFingerprint
{
    public static function for(Issue $issue): string
    {
        return hash('sha256', implode("\0", [
            $issue->code->value,
            $issue->module?->value ?? '',
            self::normalizePath($issue->file),
            $issue->key ?? '',
        ]));
    }

    private static function normalizePath(?string $path): string
    {
        return $path === null ? '' : str_replace('\\', '/', $path);
    }
}
