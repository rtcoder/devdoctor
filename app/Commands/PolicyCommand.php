<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\ExitCode;
use LaravelZero\Framework\Commands\Command;

final class PolicyCommand extends Command
{
    protected $signature = 'policy
        {--format=table : Output format: table or json}';

    protected $description = 'Show DevDoctor safety and compatibility policy.';

    public function handle(): int
    {
        $payload = [
            'tool' => 'devdoctor',
            'policy' => [
                'read_only_by_default' => true,
                'suggested_fixes_execute_commands' => false,
                'schema_version' => '1.0',
                'stable_issue_codes' => true,
                'php_requirement' => '^8.5',
            ],
        ];

        if ((string) $this->option('format') === 'json') {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ExitCode::OK->value;
        }

        foreach ($payload['policy'] as $key => $value) {
            $this->output->writeln($key.': '.(is_bool($value) ? ($value ? 'true' : 'false') : $value));
        }

        return ExitCode::OK->value;
    }
}
