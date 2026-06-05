<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class IssueSuggestionCatalog
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{hint: ?string, fix: ?FixSuggestion}
     */
    public static function for(IssueCode $code, array $context = []): array
    {
        $suggestedCommand = is_string($context['suggested_command'] ?? null) ? $context['suggested_command'] : null;

        return match ($code) {
            IssueCode::DD_CI_UNKNOWN_MODULE => self::suggest('Use one of the documented CI module names.'),
            IssueCode::DD_HEALTH_UNKNOWN_MODULE => self::suggest('Use one of the documented health module names or remove it from --modules.'),
            IssueCode::DD_CI_BASELINE_MISSING => self::suggest('Create the baseline file with --write-baseline or correct the provided path.'),
            IssueCode::DD_CI_BASELINE_INVALID => self::suggest('Regenerate the baseline file with --write-baseline.'),
            IssueCode::DD_CI_BASELINE_EXISTS => self::suggest('Review the existing baseline before replacing it with --force.'),
            IssueCode::DD_ENV_DUPLICATE_KEY => self::suggest('Keep a single declaration for this key so its effective value is unambiguous.'),
            IssueCode::DD_ENV_EMPTY_VALUE => self::suggest('Set a meaningful value or explicitly ignore the key in devdoctor.yml.'),
            IssueCode::DD_ENV_EXAMPLE_MISSING => self::suggest('Create the example file and document every required environment key.'),
            IssueCode::DD_ENV_FILE_MISSING => self::suggest('Create the environment file from the project template before running the application.'),
            IssueCode::DD_ENV_FORBIDDEN_WHEN_PRESENT => self::suggest('Remove or change this value when the configured condition is active.'),
            IssueCode::DD_ENV_INVALID_ALLOWED_VALUE => self::suggest('Choose one of the values allowed by devdoctor.yml.'),
            IssueCode::DD_ENV_INVALID_CONFIG => self::suggest('Fix the YAML structure and rerun DevDoctor.'),
            IssueCode::DD_ENV_INVALID_KEY_NAME => self::suggest('Rename the key to use letters, numbers, and underscores only.'),
            IssueCode::DD_ENV_INVALID_TYPE => self::suggest('Change the value to match the type declared in devdoctor.yml.'),
            IssueCode::DD_ENV_MISSING_IN_ENV => self::suggest('Add the key to the environment file or ignore it explicitly when it is optional.'),
            IssueCode::DD_ENV_MISSING_IN_EXAMPLE => self::suggest('Add the key to the example file without copying a real secret value.'),
            IssueCode::DD_ENV_PROD_DEBUG => self::suggest('Disable debug mode before using production environment settings.'),
            IssueCode::DD_ENV_REQUIRED_MISSING, IssueCode::DD_ENV_REQUIRED_WHEN_MISSING => self::suggest('Add the required key to the environment file.'),
            IssueCode::DD_ENV_SECRET_IN_EXAMPLE => self::suggest('Replace the value with a safe placeholder and rotate the exposed credential if it was real.'),
            IssueCode::DD_PORT_INVALID_PORT => self::suggest('Use a numeric TCP port between 1 and 65535.'),
            IssueCode::DD_PORT_MULTIPLE_LISTENERS => self::suggest('Inspect all listeners and stop only the process that should not own the port.'),
            IssueCode::DD_PORT_PROVIDER_UNAVAILABLE => self::suggest('Install a supported port inspection tool for this operating system.'),
            IssueCode::DD_PORT_IN_USE => self::suggest(
                'Confirm the process can be stopped before freeing the port.',
                $suggestedCommand === null ? null : new FixSuggestion('Terminate the process that owns the port.', $suggestedCommand, true),
            ),
            IssueCode::DD_COMPOSER_BINARY_MISSING => self::suggest('Install Composer or add it to PATH before running Composer diagnostics.'),
            IssueCode::DD_COMPOSER_EXTENSION_MISSING => self::suggest('Install and enable the required PHP extension.'),
            IssueCode::DD_COMPOSER_JSON_INVALID => self::suggest('Fix composer.json syntax before running Composer commands.'),
            IssueCode::DD_COMPOSER_LOCK_MISSING, IssueCode::DD_COMPOSER_LOCK_OUTDATED => self::suggest(
                'Review dependency changes and regenerate composer.lock intentionally.',
                new FixSuggestion('Regenerate composer.lock after reviewing dependency constraints.', 'composer update'),
            ),
            IssueCode::DD_COMPOSER_PACKAGE_ABANDONED => self::suggest('Plan migration to a maintained replacement package.'),
            IssueCode::DD_COMPOSER_PHP_VERSION_MISMATCH => self::suggest('Use a PHP version that satisfies composer.json or update the declared constraint intentionally.'),
            IssueCode::DD_COMPOSER_SCRIPT_RISKY => self::suggest('Review the script and replace shell execution with a safer, auditable command where possible.'),
            IssueCode::DD_COMPOSER_VALIDATE_FAILED => self::suggest(
                'Run Composer validation for the full error details.',
                new FixSuggestion('Validate composer.json.', 'composer validate --strict --no-check-publish'),
            ),
            IssueCode::DD_COMPOSER_VENDOR_MISSING => self::suggest(
                'Install dependencies after reviewing composer.lock.',
                new FixSuggestion('Install locked Composer dependencies.', 'composer install'),
            ),
            IssueCode::DD_PHP_BINARY_MISSING => self::suggest('Install PHP or add it to PATH before running PHP diagnostics.'),
            IssueCode::DD_PHP_COMPOSER_JSON_INVALID => self::suggest('Fix composer.json syntax before relying on PHP platform diagnostics.'),
            IssueCode::DD_PHP_EXTENSION_MISSING => self::suggest('Install and enable the required PHP extension for the active CLI runtime.'),
            IssueCode::DD_PHP_INI_MISSING => self::suggest('Confirm the CLI runtime is intentionally running without a php.ini file.'),
            IssueCode::DD_PHP_MEMORY_LIMIT_LOW => self::suggest('Increase CLI memory_limit or run DevDoctor with a lower intentional threshold.'),
            IssueCode::DD_PHP_VERSION_MISMATCH => self::suggest('Use a PHP runtime that satisfies composer.json or update the declared constraint intentionally.'),
            IssueCode::DD_PHP_XDEBUG_ENABLED_IN_CI => self::suggest('Disable Xdebug in CI, for example by setting XDEBUG_MODE=off.'),
            IssueCode::DD_NODE_BINARY_MISSING => self::suggest('Install Node.js or add it to PATH before running Node diagnostics.'),
            IssueCode::DD_NODE_LOCK_MISSING => self::suggest('Generate and commit the intended package manager lockfile.'),
            IssueCode::DD_NODE_MODULES_MISSING => self::suggest('Install dependencies with the package manager selected by the project lockfile.'),
            IssueCode::DD_NODE_MULTIPLE_LOCKFILES => self::suggest('Keep only the lockfile for the package manager the project actually uses.'),
            IssueCode::DD_NODE_PACKAGE_JSON_INVALID => self::suggest('Fix package.json syntax before running package manager commands.'),
            IssueCode::DD_NODE_PACKAGE_MANAGER_MISMATCH => self::suggest('Align packageManager with the committed lockfile.'),
            IssueCode::DD_NODE_SCRIPT_RISKY => self::suggest('Review the script and replace remote shell execution with a safer, auditable command.'),
            IssueCode::DD_NODE_VERSION_FILE_CONFLICT => self::suggest('Choose one Node.js version policy and align package.json, .nvmrc, and .node-version.'),
            IssueCode::DD_NODE_VERSION_MISMATCH => self::suggest('Use a Node.js runtime that satisfies the project requirement.'),
            IssueCode::DD_LARAVEL_APP_KEY_MISSING => self::suggest('Generate and set APP_KEY before running the application.'),
            IssueCode::DD_LARAVEL_APP_URL_DEFAULT => self::suggest('Set APP_URL to the canonical local, staging, or production URL for this project.'),
            IssueCode::DD_LARAVEL_CONFIG_CACHED => self::suggest('Rebuild Laravel config cache after changing environment or config files.'),
            IssueCode::DD_LARAVEL_DIRECTORY_MISSING => self::suggest('Create the expected Laravel runtime directory.'),
            IssueCode::DD_LARAVEL_DIRECTORY_NOT_WRITABLE => self::suggest('Adjust ownership or permissions so the current user can write Laravel runtime files.'),
            IssueCode::DD_LARAVEL_ENV_MISSING => self::suggest('Create .env from .env.example and fill required Laravel values.'),
            IssueCode::DD_LARAVEL_PROD_DEBUG => self::suggest('Disable APP_DEBUG before using production environment settings.'),
            IssueCode::DD_SECURITY_DOCKER_PRIVILEGED => self::suggest('Avoid privileged containers unless the service explicitly requires host-level access.'),
            IssueCode::DD_SECURITY_DOCKER_SOCKET_MOUNT => self::suggest('Avoid mounting the Docker socket into containers unless this is an intentional trusted control plane.'),
            IssueCode::DD_SECURITY_ENV_NOT_IGNORED => self::suggest('Add .env patterns to .gitignore to reduce accidental secret commits.'),
            IssueCode::DD_SECURITY_RISKY_COMPOSER_SCRIPT => self::suggest('Review the Composer script and replace remote shell execution with a safer install step.'),
            IssueCode::DD_SECURITY_RISKY_PACKAGE_SCRIPT => self::suggest('Review the package script and replace remote shell execution with a safer install step.'),
            IssueCode::DD_SECURITY_SECRET_IN_EXAMPLE => self::suggest('Replace the value with a placeholder and rotate the credential if it was real.'),
            IssueCode::DD_SECURITY_SECRET_PATTERN => self::suggest('Move hard-coded secrets into a secret manager or local environment file and rotate exposed values.'),
            IssueCode::DD_GIT_BINARY_MISSING => self::suggest('Install Git or add it to PATH before running repository diagnostics.'),
            IssueCode::DD_GIT_CONFLICTS => self::suggest('Resolve all merge conflicts before continuing.'),
            IssueCode::DD_GIT_DETACHED_HEAD => self::suggest('Create or switch to a branch before making long-lived changes.'),
            IssueCode::DD_GIT_DIRTY_WORKTREE => self::suggest('Review, commit, stash, or discard the intended changes before requiring a clean worktree.'),
            IssueCode::DD_GIT_ENV_NOT_IGNORED => self::suggest('Add the environment file pattern to .gitignore.'),
            IssueCode::DD_GIT_LARGE_UNTRACKED_FILE => self::suggest('Review whether the file belongs in Git or should be ignored or stored elsewhere.'),
            IssueCode::DD_GIT_NO_UPSTREAM => self::suggest('Configure an upstream branch before relying on ahead/behind diagnostics.'),
            IssueCode::DD_GIT_TRACKED_SENSITIVE_FILE => self::suggest('Remove the file from version control and rotate any exposed credentials.'),
            IssueCode::DD_GIT_UNTRACKED_SENSITIVE_FILE => self::suggest('Ignore the file or confirm it cannot be committed accidentally.'),
            IssueCode::DD_DOCKER_BINARY_MISSING => self::suggest('Install Docker or add it to PATH before running Compose diagnostics.'),
            IssueCode::DD_DOCKER_COMPOSE_CONFIG_INVALID, IssueCode::DD_DOCKER_COMPOSE_INVALID => self::suggest('Fix the Compose configuration before starting containers.'),
            IssueCode::DD_DOCKER_COMPOSE_FILE_MISSING => self::suggest('Check the --compose-file path or add a supported Compose file.'),
            IssueCode::DD_DOCKER_CONTAINER_UNHEALTHY => self::suggest('Inspect container logs and health-check output before restarting services.'),
            IssueCode::DD_DOCKER_DAEMON_UNAVAILABLE => self::suggest('Start the Docker daemon and verify the current user can access it.'),
            IssueCode::DD_DOCKER_ENV_REFERENCE_MISSING => self::suggest('Define the referenced environment variable or provide an intentional Compose default.'),
            IssueCode::DD_DOCKER_HOST_PORT_CONFLICT => self::suggest('Change the host port mapping or stop the process currently using that port.'),
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
