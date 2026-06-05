<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class IssueSuggestionCatalog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function for(IssueCode $code, array $context = []): HintAndFixIssue
    {
        $suggestedCommand = is_string($context['suggested_command'] ?? null) ? $context['suggested_command'] : null;

        return match ($code) {
            IssueCode::DD_PORT_IN_USE => self::suggest(
                $code->hint(),
                $suggestedCommand === null ? null : new FixSuggestion('Terminate the process that owns the port.', $suggestedCommand, true),
            ),
            IssueCode::DD_COMPOSER_LOCK_MISSING, IssueCode::DD_COMPOSER_LOCK_OUTDATED => self::suggest(
                $code->hint(),
                new FixSuggestion('Regenerate composer.lock after reviewing dependency constraints.', 'composer update'),
            ),
            IssueCode::DD_COMPOSER_VALIDATE_FAILED => self::suggest(
                $code->hint(),
                new FixSuggestion('Validate composer.json.', 'composer validate --strict --no-check-publish'),
            ),
            IssueCode::DD_COMPOSER_VENDOR_MISSING => self::suggest(
                $code->hint(),
                new FixSuggestion('Install locked Composer dependencies.', 'composer install'),
            ),
            IssueCode::DD_CACHE_LARAVEL_ARTIFACT => self::suggest(
                $code->hint(),
                new FixSuggestion('Clear Laravel cached framework artifacts intentionally.', 'php artisan optimize:clear'),
            ),
            default => new HintAndFixIssue($code->hint()),
        };
    }

    private static function suggest(?string $hint, ?FixSuggestion $fix = null): HintAndFixIssue
    {
        return new HintAndFixIssue($hint, $fix);
    }
}
