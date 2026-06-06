<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\ExitCode;
use LaravelZero\Framework\Commands\Command;

final class CommandsCommand extends Command
{
    protected $signature = 'commands
        {--format=table : Output format: table or json}
        {--module= : Filter commands by module}
        {--type= : Filter commands by type}';

    protected $description = 'List DevDoctor commands and their documentation metadata.';

    public function handle(): int
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
            'commands' => $this->filteredCommands(),
        ];

        if ($format === 'json') {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ExitCode::OK->value;
        }

        $this->table(
            ['Command', 'Module', 'Type', 'Read-only', 'Summary'],
            array_map(static fn (array $command): array => [
                $command['name'],
                $command['module'],
                $command['type'],
                $command['read_only'] ? 'yes' : 'no',
                $command['summary'],
            ], $payload['commands'])
        );

        return ExitCode::OK->value;
    }

    /**
     * @return array<int, array{name:string,module:string,type:string,read_only:bool,summary:string,example:string}>
     */
    private function filteredCommands(): array
    {
        $module = $this->option('module');
        $type = $this->option('type');

        return array_values(array_filter($this->commandCatalog(), static function (array $command) use ($module, $type): bool {
            if (is_string($module) && $module !== '' && $command['module'] !== $module) {
                return false;
            }

            if (is_string($type) && $type !== '' && $command['type'] !== $type) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array<int, array{name:string,module:string,type:string,read_only:bool,summary:string,example:string}>
     */
    private function commandCatalog(): array
    {
        $path = dirname(__DIR__, 2).'/docs/commands.json';
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $payload['commands'];
    }
}
