<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Queue;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvEntry;
use DevDoctor\Modules\Env\EnvParser;

final readonly class QueueAnalyzer
{
    /**
     * @var array<string, list<string>>
     */
    private const array REQUIRED_KEYS = [
        'database' => ['DB_CONNECTION'],
        'redis' => ['REDIS_HOST'],
        'sqs' => ['SQS_QUEUE'],
        'beanstalkd' => ['QUEUE_HOST'],
    ];

    /**
     * @var list<string>
     */
    private const array SUPPORTED_CONNECTIONS = [
        'sync',
        'database',
        'redis',
        'sqs',
        'beanstalkd',
        'null',
    ];

    public function __construct(
        private EnvParser $parser = new EnvParser,
    ) {}

    public function analyze(QueueOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $env = $this->parser->parseFile($paths->absolute('.env'), '.env');

        if (! $env->exists) {
            $issues->add(new Issue(
                code: IssueCode::DD_QUEUE_CONNECTION_MISSING,
                severity: Severity::INFO,
                message: 'Queue configuration could not be checked because .env is missing.',
                module: ModuleName::QUEUE,
                file: '.env',
                key: 'QUEUE_CONNECTION',
            ));

            return $issues;
        }

        $entries = $this->entriesByKey($env->entries);
        $connectionEntry = $entries['QUEUE_CONNECTION'] ?? null;
        $connection = strtolower(trim($connectionEntry?->value ?? ''));

        if ($connection === '') {
            $issues->add(new Issue(
                code: IssueCode::DD_QUEUE_CONNECTION_MISSING,
                severity: Severity::INFO,
                message: 'QUEUE_CONNECTION is missing or empty.',
                module: ModuleName::QUEUE,
                file: '.env',
                line: $connectionEntry?->line,
                key: 'QUEUE_CONNECTION',
            ));

            return $issues;
        }

        if (! in_array($connection, self::SUPPORTED_CONNECTIONS, true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_QUEUE_CONNECTION_UNKNOWN,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'QUEUE_CONNECTION uses an unknown queue driver: '.$connection,
                module: ModuleName::QUEUE,
                file: '.env',
                line: $connectionEntry?->line,
                key: 'QUEUE_CONNECTION',
                context: ['connection' => $connection],
            ));

            return $issues;
        }

        $this->checkProductionSync($issues, $entries, $connection, $options);
        $this->checkRequiredKeys($issues, $entries, $connection, $options);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_QUEUE_READY,
                severity: Severity::INFO,
                message: 'Queue diagnostics found no actionable issues.',
                module: ModuleName::QUEUE,
                key: $connection,
            ));
        }

        return $issues;
    }

    /**
     * @param  list<EnvEntry>  $entries
     * @return array<string, EnvEntry>
     */
    private function entriesByKey(array $entries): array
    {
        $indexed = [];

        foreach ($entries as $entry) {
            $indexed[$entry->key] = $entry;
        }

        return $indexed;
    }

    /**
     * @param  array<string, EnvEntry>  $entries
     */
    private function checkProductionSync(IssueCollection $issues, array $entries, string $connection, QueueOptions $options): void
    {
        $appEnv = strtolower(trim($entries['APP_ENV']->value ?? ''));

        if ($connection !== 'sync' || $appEnv !== 'production') {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_QUEUE_SYNC_IN_PRODUCTION,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'QUEUE_CONNECTION=sync is risky when APP_ENV=production.',
            module: ModuleName::QUEUE,
            file: '.env',
            line: $entries['QUEUE_CONNECTION']?->line,
            key: 'QUEUE_CONNECTION',
        ));
    }

    /**
     * @param  array<string, EnvEntry>  $entries
     */
    private function checkRequiredKeys(IssueCollection $issues, array $entries, string $connection, QueueOptions $options): void
    {
        foreach (self::REQUIRED_KEYS[$connection] ?? [] as $key) {
            if (isset($entries[$key]) && trim($entries[$key]->value) !== '') {
                continue;
            }

            $issues->add(new Issue(
                code: $connection === 'database' ? IssueCode::DD_QUEUE_DATABASE_REQUIRES_DB : IssueCode::DD_QUEUE_REQUIRED_KEY_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: $key.' is required for '.$connection.' queue configuration.',
                module: ModuleName::QUEUE,
                file: '.env',
                line: $entries[$key]->line ?? null,
                key: $key,
                context: ['connection' => $connection],
            ));
        }
    }
}
