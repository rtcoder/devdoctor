<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Database;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvEntry;
use DevDoctor\Modules\Env\EnvParser;
use PDO;
use PDOException;

final readonly class DatabaseAnalyzer
{
    /**
     * @var array<string, list<string>>
     */
    private const REQUIRED_KEYS = [
        'mysql' => ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'],
        'pgsql' => ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'],
        'postgres' => ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'],
        'sqlsrv' => ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'],
        'sqlite' => ['DB_DATABASE'],
    ];

    /**
     * @var array<string, string>
     */
    private const PDO_DRIVERS = [
        'mysql' => 'mysql',
        'pgsql' => 'pgsql',
        'postgres' => 'pgsql',
        'sqlsrv' => 'sqlsrv',
        'sqlite' => 'sqlite',
    ];

    public function __construct(
        private EnvParser $parser = new EnvParser,
    ) {}

    public function analyze(DatabaseOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $env = $this->parser->parseFile($paths->absolute('.env'), '.env');

        if (! $env->exists) {
            $issues->add(new Issue(
                code: IssueCode::DD_DB_CONNECTION_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'Database configuration could not be checked because .env is missing.',
                module: ModuleName::DATABASE,
                file: '.env',
                key: 'DB_CONNECTION',
            ));

            return $issues;
        }

        $entries = $this->entriesByKey($env->entries);
        $connection = strtolower(trim($entries['DB_CONNECTION']?->value ?? ''));

        if ($connection === '') {
            $issues->add(new Issue(
                code: IssueCode::DD_DB_CONNECTION_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'DB_CONNECTION is missing or empty.',
                module: ModuleName::DATABASE,
                file: '.env',
                line: $entries['DB_CONNECTION']->line ?? null,
                key: 'DB_CONNECTION',
            ));

            return $issues;
        }

        if (! array_key_exists($connection, self::REQUIRED_KEYS)) {
            $issues->add(new Issue(
                code: IssueCode::DD_DB_CONNECTION_UNKNOWN,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'DB_CONNECTION uses an unknown database driver: '.$connection,
                module: ModuleName::DATABASE,
                file: '.env',
                line: $entries['DB_CONNECTION']?->line,
                key: 'DB_CONNECTION',
                context: ['connection' => $connection],
            ));

            return $issues;
        }

        $this->checkRequiredKeys($issues, $entries, $connection, $options);
        $this->checkPort($issues, $entries, $connection, $options);
        $this->checkSqliteFile($issues, $entries, $connection, $paths, $options);

        if ($options->connect) {
            $this->checkConnection($issues, $entries, $connection, $paths);
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_DB_READY,
                severity: Severity::INFO,
                message: 'Database diagnostics found no actionable issues.',
                module: ModuleName::DATABASE,
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
    private function checkRequiredKeys(IssueCollection $issues, array $entries, string $connection, DatabaseOptions $options): void
    {
        foreach (self::REQUIRED_KEYS[$connection] as $key) {
            if (isset($entries[$key]) && trim($entries[$key]->value) !== '') {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_DB_REQUIRED_KEY_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: $key.' is required for '.$connection.' database configuration.',
                module: ModuleName::DATABASE,
                file: '.env',
                line: $entries[$key]->line ?? null,
                key: $key,
                context: ['connection' => $connection],
            ));
        }
    }

    /**
     * @param  array<string, EnvEntry>  $entries
     */
    private function checkPort(IssueCollection $issues, array $entries, string $connection, DatabaseOptions $options): void
    {
        if ($connection === 'sqlite' || ! isset($entries['DB_PORT']) || trim($entries['DB_PORT']->value) === '') {
            return;
        }

        $port = trim($entries['DB_PORT']->value);

        if (ctype_digit($port) && (int) $port >= 1 && (int) $port <= 65535) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_DB_PORT_INVALID,
            severity: Severity::ERROR,
            message: 'DB_PORT must be a numeric TCP port between 1 and 65535.',
            module: ModuleName::DATABASE,
            file: '.env',
            line: $entries['DB_PORT']->line,
            key: 'DB_PORT',
        ));
    }

    /**
     * @param  array<string, EnvEntry>  $entries
     */
    private function checkSqliteFile(IssueCollection $issues, array $entries, string $connection, PathResolver $paths, DatabaseOptions $options): void
    {
        if ($connection !== 'sqlite' || ! isset($entries['DB_DATABASE'])) {
            return;
        }

        $database = trim($entries['DB_DATABASE']->value);

        if ($database === '' || $database === ':memory:') {
            return;
        }

        $path = str_starts_with($database, DIRECTORY_SEPARATOR)
            ? $database
            : $paths->absolute($database);

        if (is_file($path)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_DB_SQLITE_FILE_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'SQLite database file does not exist: '.$database,
            module: ModuleName::DATABASE,
            file: '.env',
            line: $entries['DB_DATABASE']->line,
            key: 'DB_DATABASE',
            context: ['database' => $database],
        ));
    }

    /**
     * @param  array<string, EnvEntry>  $entries
     */
    private function checkConnection(IssueCollection $issues, array $entries, string $connection, PathResolver $paths): void
    {
        $driver = self::PDO_DRIVERS[$connection];

        if (! in_array($driver, PDO::getAvailableDrivers(), true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_DB_DRIVER_MISSING,
                severity: Severity::WARNING,
                message: 'PDO driver is not available: '.$driver,
                module: ModuleName::DATABASE,
                key: $driver,
            ));

            return;
        }

        try {
            $pdo = new PDO($this->dsn($entries, $connection, $paths), $entries['DB_USERNAME']?->value ?? null, $entries['DB_PASSWORD']?->value ?? null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('select 1');
            $issues->add(new Issue(
                code: IssueCode::DD_DB_CONNECT_OK,
                severity: Severity::INFO,
                message: 'Database connection check completed successfully.',
                module: ModuleName::DATABASE,
                key: $connection,
            ));
        } catch (PDOException $exception) {
            $issues->add(new Issue(
                code: IssueCode::DD_DB_CONNECT_FAILED,
                severity: Severity::WARNING,
                message: 'Database connection check failed: '.$exception->getMessage(),
                module: ModuleName::DATABASE,
                key: $connection,
            ));
        }
    }

    /**
     * @param  array<string, EnvEntry>  $entries
     */
    private function dsn(array $entries, string $connection, PathResolver $paths): string
    {
        if ($connection === 'sqlite') {
            $database = trim($entries['DB_DATABASE']?->value ?? '');
            $path = $database === ':memory:' || str_starts_with($database, DIRECTORY_SEPARATOR)
                ? $database
                : $paths->absolute($database);

            return 'sqlite:'.$path;
        }

        $driver = self::PDO_DRIVERS[$connection];
        $host = trim($entries['DB_HOST']?->value ?? 'localhost');
        $port = trim($entries['DB_PORT']?->value ?? '');
        $database = trim($entries['DB_DATABASE']?->value ?? '');
        $dsn = $driver.':host='.$host;

        if ($port !== '') {
            $dsn .= ';port='.$port;
        }

        if ($database !== '') {
            $dsn .= ';dbname='.$database;
        }

        return $dsn;
    }
}
