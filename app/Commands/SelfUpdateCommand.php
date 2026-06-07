<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\ExitCode;
use DevDoctor\Core\ProcessRunner;
use DevDoctor\Core\Updates\UpdateChecker;
use DevDoctor\Core\VersionResolver;
use LaravelZero\Framework\Commands\Command;

final class SelfUpdateCommand extends Command
{
    protected $signature = 'self-update
        {--run : Execute the suggested package-manager update command when supported}
        {--format=table : Output format: table or json}';

    protected $description = 'Check for a newer DevDoctor release and show or run the safest update command.';

    public function handle(UpdateChecker $checker, ProcessRunner $processRunner, VersionResolver $versions): int
    {
        $format = (string) $this->option('format');

        if (! in_array($format, ['table', 'json'], true)) {
            $this->output->writeln($format === 'json'
                ? json_encode(['error' => 'invalid_format', 'format' => $format], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : 'Invalid format: '.$format);

            return ExitCode::INVALID_CONFIG->value;
        }

        $currentVersion = $versions->current();
        $update = $checker->check($currentVersion);

        if ($update === null) {
            return $this->render($format, [
                'tool' => 'devdoctor',
                'schema_version' => '1.0',
                'current_version' => $currentVersion,
                'update_available' => false,
                'message' => 'DevDoctor is up to date.',
            ]);
        }

        $payload = [
            'tool' => 'devdoctor',
            'schema_version' => '1.0',
            'current_version' => $update->currentVersion,
            'latest_version' => $update->latestRelease->version,
            'latest_release_url' => $update->latestRelease->url,
            'update_available' => true,
            'method' => $update->instruction->method,
            'suggested_command' => $update->instruction->displayCommand,
            'runnable' => $update->instruction->runnable,
        ];

        if (! (bool) $this->option('run')) {
            return $this->render($format, $payload);
        }

        if (! $update->instruction->runnable || $update->instruction->command === []) {
            $payload['executed'] = false;
            $payload['message'] = 'This installation method cannot be updated automatically yet. Run the suggested command manually.';

            return $this->render($format, $payload, ExitCode::INVALID_CONFIG);
        }

        $result = $processRunner->run($update->instruction->command, getcwd() ?: '.', 300);
        $payload['executed'] = true;
        $payload['exit_code'] = $result->exitCode;
        $payload['stdout'] = trim($result->stdout);
        $payload['stderr'] = trim($result->stderr);

        return $this->render($format, $payload, $result->successful() ? ExitCode::OK : ExitCode::INTERNAL_ERROR);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function render(string $format, array $payload, ExitCode $exitCode = ExitCode::OK): int
    {
        if ($format === 'json') {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $exitCode->value;
        }

        if (($payload['update_available'] ?? false) === false) {
            $this->output->writeln((string) $payload['message']);

            return $exitCode->value;
        }

        $this->output->writeln(sprintf(
            'Update available: you are using DevDoctor %s, latest is %s.',
            $payload['current_version'],
            $payload['latest_version'],
        ));
        $this->output->writeln('Update with: '.$payload['suggested_command']);

        if (isset($payload['executed'])) {
            $this->output->writeln($payload['executed'] ? 'Update command executed.' : (string) $payload['message']);
        }

        return $exitCode->value;
    }
}
