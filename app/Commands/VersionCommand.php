<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\ExitCode;
use DevDoctor\Core\VersionResolver;
use LaravelZero\Framework\Commands\Command;

final class VersionCommand extends Command
{
    protected $signature = 'version
        {--format=table : Output format: table or json}';

    protected $description = 'Show the current DevDoctor version.';

    public function handle(VersionResolver $versions): int
    {
        $format = (string) $this->option('format');

        if (! in_array($format, ['table', 'json'], true)) {
            $this->output->writeln($format === 'json'
                ? json_encode(['error' => 'invalid_format', 'format' => $format], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : 'Invalid format: '.$format);

            return ExitCode::INVALID_CONFIG->value;
        }

        $payload = [
            'tool' => 'devdoctor',
            'schema_version' => '1.0',
            'version' => $versions->current(),
        ];

        if ($format === 'json') {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ExitCode::OK->value;
        }

        $this->output->writeln('DevDoctor '.$payload['version']);

        return ExitCode::OK->value;
    }
}
