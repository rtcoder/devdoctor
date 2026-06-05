<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class IssueSuggestionCatalog
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{hint: ?string, fix: ?FixSuggestion}
     */
    public static function for(string $code, array $context = []): array
    {
        $suggestedCommand = is_string($context['suggested_command'] ?? null) ? $context['suggested_command'] : null;

        return match ($code) {
            'DD_CI_UNKNOWN_MODULE' => self::suggest('Use one of the documented CI module names.'),
            'DD_CI_BASELINE_MISSING' => self::suggest('Create the baseline file with --write-baseline or correct the provided path.'),
            'DD_CI_BASELINE_INVALID' => self::suggest('Regenerate the baseline file with --write-baseline.'),
            'DD_CI_BASELINE_EXISTS' => self::suggest('Review the existing baseline before replacing it with --force.'),
            'DD_ENV_DUPLICATE_KEY' => self::suggest('Keep a single declaration for this key so its effective value is unambiguous.'),
            'DD_ENV_EMPTY_VALUE' => self::suggest('Set a meaningful value or explicitly ignore the key in devdoctor.yml.'),
            'DD_ENV_EXAMPLE_MISSING' => self::suggest('Create the example file and document every required environment key.'),
            'DD_ENV_FILE_MISSING' => self::suggest('Create the environment file from the project template before running the application.'),
            'DD_ENV_FORBIDDEN_WHEN_PRESENT' => self::suggest('Remove or change this value when the configured condition is active.'),
            'DD_ENV_INVALID_ALLOWED_VALUE' => self::suggest('Choose one of the values allowed by devdoctor.yml.'),
            'DD_ENV_INVALID_CONFIG' => self::suggest('Fix the YAML structure and rerun DevDoctor.'),
            'DD_ENV_INVALID_KEY_NAME' => self::suggest('Rename the key to use letters, numbers, and underscores only.'),
            'DD_ENV_INVALID_TYPE' => self::suggest('Change the value to match the type declared in devdoctor.yml.'),
            'DD_ENV_MISSING_IN_ENV' => self::suggest('Add the key to the environment file or ignore it explicitly when it is optional.'),
            'DD_ENV_MISSING_IN_EXAMPLE' => self::suggest('Add the key to the example file without copying a real secret value.'),
            'DD_ENV_PROD_DEBUG' => self::suggest('Disable debug mode before using production environment settings.'),
            'DD_ENV_REQUIRED_MISSING', 'DD_ENV_REQUIRED_WHEN_MISSING' => self::suggest('Add the required key to the environment file.'),
            'DD_ENV_SECRET_IN_EXAMPLE' => self::suggest('Replace the value with a safe placeholder and rotate the exposed credential if it was real.'),
            'DD_PORT_INVALID_PORT' => self::suggest('Use a numeric TCP port between 1 and 65535.'),
            'DD_PORT_MULTIPLE_LISTENERS' => self::suggest('Inspect all listeners and stop only the process that should not own the port.'),
            'DD_PORT_PROVIDER_UNAVAILABLE' => self::suggest('Install a supported port inspection tool for this operating system.'),
            'DD_PORT_IN_USE' => self::suggest(
                'Confirm the process can be stopped before freeing the port.',
                $suggestedCommand === null ? null : new FixSuggestion('Terminate the process that owns the port.', $suggestedCommand, true),
            ),
            'DD_COMPOSER_BINARY_MISSING' => self::suggest('Install Composer or add it to PATH before running Composer diagnostics.'),
            'DD_COMPOSER_EXTENSION_MISSING' => self::suggest('Install and enable the required PHP extension.'),
            'DD_COMPOSER_JSON_INVALID' => self::suggest('Fix composer.json syntax before running Composer commands.'),
            'DD_COMPOSER_LOCK_MISSING', 'DD_COMPOSER_LOCK_OUTDATED' => self::suggest(
                'Review dependency changes and regenerate composer.lock intentionally.',
                new FixSuggestion('Regenerate composer.lock after reviewing dependency constraints.', 'composer update'),
            ),
            'DD_COMPOSER_PACKAGE_ABANDONED' => self::suggest('Plan migration to a maintained replacement package.'),
            'DD_COMPOSER_PHP_VERSION_MISMATCH' => self::suggest('Use a PHP version that satisfies composer.json or update the declared constraint intentionally.'),
            'DD_COMPOSER_SCRIPT_RISKY' => self::suggest('Review the script and replace shell execution with a safer, auditable command where possible.'),
            'DD_COMPOSER_VALIDATE_FAILED' => self::suggest(
                'Run Composer validation for the full error details.',
                new FixSuggestion('Validate composer.json.', 'composer validate --strict --no-check-publish'),
            ),
            'DD_COMPOSER_VENDOR_MISSING' => self::suggest(
                'Install dependencies after reviewing composer.lock.',
                new FixSuggestion('Install locked Composer dependencies.', 'composer install'),
            ),
            'DD_PHP_BINARY_MISSING' => self::suggest('Install PHP or add it to PATH before running PHP diagnostics.'),
            'DD_PHP_COMPOSER_JSON_INVALID' => self::suggest('Fix composer.json syntax before relying on PHP platform diagnostics.'),
            'DD_PHP_EXTENSION_MISSING' => self::suggest('Install and enable the required PHP extension for the active CLI runtime.'),
            'DD_PHP_INI_MISSING' => self::suggest('Confirm the CLI runtime is intentionally running without a php.ini file.'),
            'DD_PHP_MEMORY_LIMIT_LOW' => self::suggest('Increase CLI memory_limit or run DevDoctor with a lower intentional threshold.'),
            'DD_PHP_VERSION_MISMATCH' => self::suggest('Use a PHP runtime that satisfies composer.json or update the declared constraint intentionally.'),
            'DD_PHP_XDEBUG_ENABLED_IN_CI' => self::suggest('Disable Xdebug in CI, for example by setting XDEBUG_MODE=off.'),
            'DD_GIT_BINARY_MISSING' => self::suggest('Install Git or add it to PATH before running repository diagnostics.'),
            'DD_GIT_CONFLICTS' => self::suggest('Resolve all merge conflicts before continuing.'),
            'DD_GIT_DETACHED_HEAD' => self::suggest('Create or switch to a branch before making long-lived changes.'),
            'DD_GIT_DIRTY_WORKTREE' => self::suggest('Review, commit, stash, or discard the intended changes before requiring a clean worktree.'),
            'DD_GIT_ENV_NOT_IGNORED' => self::suggest('Add the environment file pattern to .gitignore.'),
            'DD_GIT_LARGE_UNTRACKED_FILE' => self::suggest('Review whether the file belongs in Git or should be ignored or stored elsewhere.'),
            'DD_GIT_NO_UPSTREAM' => self::suggest('Configure an upstream branch before relying on ahead/behind diagnostics.'),
            'DD_GIT_TRACKED_SENSITIVE_FILE' => self::suggest('Remove the file from version control and rotate any exposed credentials.'),
            'DD_GIT_UNTRACKED_SENSITIVE_FILE' => self::suggest('Ignore the file or confirm it cannot be committed accidentally.'),
            'DD_DOCKER_BINARY_MISSING' => self::suggest('Install Docker or add it to PATH before running Compose diagnostics.'),
            'DD_DOCKER_COMPOSE_CONFIG_INVALID', 'DD_DOCKER_COMPOSE_INVALID' => self::suggest('Fix the Compose configuration before starting containers.'),
            'DD_DOCKER_COMPOSE_FILE_MISSING' => self::suggest('Check the --compose-file path or add a supported Compose file.'),
            'DD_DOCKER_CONTAINER_UNHEALTHY' => self::suggest('Inspect container logs and health-check output before restarting services.'),
            'DD_DOCKER_DAEMON_UNAVAILABLE' => self::suggest('Start the Docker daemon and verify the current user can access it.'),
            'DD_DOCKER_ENV_REFERENCE_MISSING' => self::suggest('Define the referenced environment variable or provide an intentional Compose default.'),
            'DD_DOCKER_HOST_PORT_CONFLICT' => self::suggest('Change the host port mapping or stop the process currently using that port.'),
            default => ['hint' => null, 'fix' => null],
        };
    }

    /**
     * @return array{hint: string, fix: ?FixSuggestion}
     */
    private static function suggest(string $hint, ?FixSuggestion $fix = null): array
    {
        return ['hint' => $hint, 'fix' => $fix];
    }
}
