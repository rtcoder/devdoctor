<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Env;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Redactor;
use DevDoctor\Core\Severity;

final readonly class EnvAnalyzer
{
    public function __construct(
        private EnvParser $parser = new EnvParser,
        private SecretScanner $secretScanner = new SecretScanner,
        private Redactor $redactor = new Redactor,
    ) {}

    public function analyze(EnvAnalysisOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;

        $env = $this->parser->parseFile($paths->absolute($options->envFile), $paths->display($options->envFile));
        $example = $this->parser->parseFile($paths->absolute($options->exampleFile), $paths->display($options->exampleFile));

        $this->checkFileExistence($issues, $env, $example, $options);

        foreach ([$env, $example] as $file) {
            if (! $file->exists) {
                continue;
            }

            $this->checkDuplicates($issues, $file);
            $this->checkInvalidKeys($issues, $file);
            $this->checkEmptyValues($issues, $file);
            $this->checkHeuristicUrls($issues, $file);
        }

        $this->checkKeyDiff($issues, $env, $example, $options);
        $this->checkProductionDebug($issues, $env);
        $this->checkConfiguredRules($issues, $env, $options);

        if ($options->scanSecrets) {
            $this->scanSecrets($issues, $paths, $options->exampleFile);
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_ENV_READY,
                severity: Severity::INFO,
                message: 'Env diagnostics found no issues.',
                module: ModuleName::ENV,
            ));
        }

        return $issues;
    }

    private function checkFileExistence(IssueCollection $issues, EnvFile $env, EnvFile $example, EnvAnalysisOptions $options): void
    {
        if (! $env->exists) {
            $issues->add(new Issue(
                code: IssueCode::DD_ENV_FILE_MISSING,
                severity: Severity::ERROR,
                message: $env->path.' does not exist',
                module: ModuleName::ENV,
                file: $env->path,
            ));
        }

        if (! $example->exists) {
            $issues->add(new Issue(
                code: IssueCode::DD_ENV_EXAMPLE_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: $example->path.' does not exist',
                module: ModuleName::ENV,
                file: $example->path,
            ));
        }
    }

    private function checkDuplicates(IssueCollection $issues, EnvFile $file): void
    {
        foreach ($file->duplicates() as $key => $entries) {
            foreach (array_slice($entries, 1) as $entry) {
                $issues->add(new Issue(
                    code: IssueCode::DD_ENV_DUPLICATE_KEY,
                    severity: Severity::ERROR,
                    message: $key.' is defined more than once',
                    module: ModuleName::ENV,
                    file: $file->path,
                    line: $entry->line,
                    key: $key,
                ));
            }
        }
    }

    private function checkInvalidKeys(IssueCollection $issues, EnvFile $file): void
    {
        foreach ($file->entries as $entry) {
            if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $entry->key) === 1) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_ENV_INVALID_KEY_NAME,
                severity: Severity::WARNING,
                message: $entry->key.' does not match expected env key format',
                module: ModuleName::ENV,
                file: $file->path,
                line: $entry->line,
                key: $entry->key,
            ));
        }
    }

    private function checkEmptyValues(IssueCollection $issues, EnvFile $file): void
    {
        foreach ($file->entries as $entry) {
            if ($entry->value !== '') {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_ENV_EMPTY_VALUE,
                severity: Severity::WARNING,
                message: $entry->key.' is empty',
                module: ModuleName::ENV,
                file: $file->path,
                line: $entry->line,
                key: $entry->key,
            ));
        }
    }

    private function checkHeuristicUrls(IssueCollection $issues, EnvFile $file): void
    {
        foreach ($file->entries as $entry) {
            if ($entry->value === '' || ! $this->isLikelyUrlKey($entry->key)) {
                continue;
            }

            if (filter_var($entry->value, FILTER_VALIDATE_URL) !== false) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_ENV_INVALID_TYPE,
                severity: Severity::WARNING,
                message: $entry->key.' should look like a valid URL',
                module: ModuleName::ENV,
                file: $file->path,
                line: $entry->line,
                key: $entry->key,
            ));
        }
    }

    private function checkKeyDiff(IssueCollection $issues, EnvFile $env, EnvFile $example, EnvAnalysisOptions $options): void
    {
        if (! $env->exists || ! $example->exists) {
            return;
        }

        $severity = $options->strict ? Severity::ERROR : Severity::WARNING;

        foreach ($example->keys() as $key) {
            if (! $env->has($key) && ! in_array($key, $options->ignoreMissingInEnv, true)) {
                $entry = $example->get($key);
                $issues->add(new Issue(
                    code: IssueCode::DD_ENV_MISSING_IN_ENV,
                    severity: $severity,
                    message: $key.' exists in '.$example->path.' but is missing in '.$env->path,
                    module: ModuleName::ENV,
                    file: $example->path,
                    line: $entry?->line,
                    key: $key,
                ));
            }
        }

        foreach ($env->keys() as $key) {
            if (! $example->has($key) && ! in_array($key, $options->ignoreMissingInExample, true)) {
                $entry = $env->get($key);
                $issues->add(new Issue(
                    code: IssueCode::DD_ENV_MISSING_IN_EXAMPLE,
                    severity: $severity,
                    message: $key.' exists in '.$env->path.' but is missing in '.$example->path,
                    module: ModuleName::ENV,
                    file: $env->path,
                    line: $entry?->line,
                    key: $key,
                ));
            }
        }
    }

    private function checkProductionDebug(IssueCollection $issues, EnvFile $env): void
    {
        if (! $env->exists) {
            return;
        }

        $appEnv = strtolower($env->get('APP_ENV')?->value ?? '');
        $appDebug = strtolower($env->get('APP_DEBUG')?->value ?? '');
        $nodeEnv = strtolower($env->get('NODE_ENV')?->value ?? '');
        $debug = strtolower($env->get('DEBUG')?->value ?? '');

        if ($appEnv === 'production' && $appDebug === 'true') {
            $entry = $env->get('APP_DEBUG');
            $issues->add(new Issue(
                code: IssueCode::DD_ENV_PROD_DEBUG,
                severity: Severity::ERROR,
                message: 'APP_DEBUG=true while APP_ENV=production',
                module: ModuleName::ENV,
                file: $env->path,
                line: $entry?->line,
                key: 'APP_DEBUG',
            ));
        }

        if ($nodeEnv === 'production' && $debug === 'true') {
            $entry = $env->get('DEBUG');
            $issues->add(new Issue(
                code: IssueCode::DD_ENV_PROD_DEBUG,
                severity: Severity::ERROR,
                message: 'DEBUG=true while NODE_ENV=production',
                module: ModuleName::ENV,
                file: $env->path,
                line: $entry?->line,
                key: 'DEBUG',
            ));
        }
    }

    private function scanSecrets(IssueCollection $issues, PathResolver $paths, string $exampleFile): void
    {
        foreach (array_unique([$exampleFile, '.env.dist', '.env.sample']) as $file) {
            $envFile = $this->parser->parseFile($paths->absolute($file), $paths->display($file));

            if (! $envFile->exists) {
                continue;
            }

            foreach ($envFile->entries as $entry) {
                if (! $this->secretScanner->isSuspicious($entry)) {
                    continue;
                }

                $issues->add(new Issue(
                    code: IssueCode::DD_ENV_SECRET_IN_EXAMPLE,
                    severity: Severity::ERROR,
                    message: $entry->key.' appears to contain a real secret in '.$envFile->path,
                    module: ModuleName::ENV,
                    file: $envFile->path,
                    line: $entry->line,
                    key: $entry->key,
                    context: ['redacted_value' => $this->redactor->redact($entry->value)],
                ));
            }
        }
    }

    private function checkConfiguredRules(IssueCollection $issues, EnvFile $env, EnvAnalysisOptions $options): void
    {
        if (! $env->exists) {
            return;
        }

        foreach ($options->rules as $key => $rule) {
            $entry = $env->get($key);

            if (($rule['required'] ?? false) === true && ($entry === null || $entry->value === '')) {
                $issues->add(new Issue(
                    code: IssueCode::DD_ENV_REQUIRED_MISSING,
                    severity: Severity::ERROR,
                    message: $key.' is required',
                    module: ModuleName::ENV,
                    key: $key,
                ));
            }

            if (isset($rule['required_when']) && is_array($rule['required_when'])) {
                foreach ($rule['required_when'] as $otherKey => $expected) {
                    if (is_string($otherKey) && $this->valueMatches($env->get($otherKey)?->value, $expected) && ($entry === null || $entry->value === '')) {
                        $issues->add(new Issue(
                            code: IssueCode::DD_ENV_REQUIRED_WHEN_MISSING,
                            severity: Severity::ERROR,
                            message: $key.' is required when '.$otherKey.' is '.$this->stringValue($expected),
                            module: ModuleName::ENV,
                            key: $key,
                        ));
                    }
                }
            }

            if (isset($rule['forbidden_when']) && is_array($rule['forbidden_when'])) {
                foreach ($rule['forbidden_when'] as $otherKey => $expected) {
                    if (is_string($otherKey) && $this->valueMatches($env->get($otherKey)?->value, $expected) && $entry !== null && $entry->value !== '') {
                        $issues->add(new Issue(
                            code: IssueCode::DD_ENV_FORBIDDEN_WHEN_PRESENT,
                            severity: Severity::ERROR,
                            message: $key.' is forbidden when '.$otherKey.' is '.$this->stringValue($expected),
                            module: ModuleName::ENV,
                            file: $entry->file,
                            line: $entry->line,
                            key: $key,
                        ));
                    }
                }
            }

            if ($entry === null || $entry->value === '') {
                continue;
            }

            if (isset($rule['allowed']) && is_array($rule['allowed']) && ! in_array($entry->value, array_map($this->stringValue(...), $rule['allowed']), true)) {
                $issues->add(new Issue(
                    code: IssueCode::DD_ENV_INVALID_ALLOWED_VALUE,
                    severity: Severity::ERROR,
                    message: $key.' has a value that is not allowed',
                    module: ModuleName::ENV,
                    file: $entry->file,
                    line: $entry->line,
                    key: $key,
                ));
            }

            if (isset($rule['type']) && is_string($rule['type']) && ! $this->isValidType($entry->value, $rule['type'])) {
                $issues->add(new Issue(
                    code: IssueCode::DD_ENV_INVALID_TYPE,
                    severity: Severity::ERROR,
                    message: $key.' must be '.$rule['type'],
                    module: ModuleName::ENV,
                    file: $entry->file,
                    line: $entry->line,
                    key: $key,
                ));
            }
        }
    }

    private function isValidType(string $value, string $type): bool
    {
        return match ($type) {
            'string' => true,
            'bool' => in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'], true),
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            default => true,
        };
    }

    private function valueMatches(?string $actual, mixed $expected): bool
    {
        return strtolower((string) $actual) === strtolower($this->stringValue($expected));
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function isLikelyUrlKey(string $key): bool
    {
        return str_ends_with($key, '_URL')
            || str_ends_with($key, '_URI')
            || str_ends_with($key, 'ENDPOINT');
    }
}
