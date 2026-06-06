<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\ExitCode;
use DevDoctor\Core\IssueCode;
use LaravelZero\Framework\Commands\Command;

final class ExplainCommand extends Command
{
    protected $signature = 'explain
        {code? : Issue code to explain}
        {--format=table : Output format: table or json}';

    protected $description = 'Explain DevDoctor issue codes and their hints.';

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $code = $this->argument('code');
        $codes = IssueCode::cases();

        if (is_string($code) && $code !== '') {
            $issueCode = IssueCode::tryFrom($code);

            if ($issueCode === null) {
                $this->output->writeln($format === 'json'
                    ? json_encode(['error' => 'unknown_issue_code', 'code' => $code], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : 'Unknown issue code: '.$code);

                return ExitCode::INVALID_CONFIG->value;
            }

            $codes = [$issueCode];
        }

        $payload = [
            'tool' => 'devdoctor',
            'issue_codes' => array_map(static fn (IssueCode $issueCode): array => [
                'code' => $issueCode->value,
                'hint' => $issueCode->hint(),
            ], $codes),
        ];

        if ($format === 'json') {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ExitCode::OK->value;
        }

        foreach ($payload['issue_codes'] as $item) {
            $this->output->writeln($item['code'].($item['hint'] === null ? '' : "\nHint: ".$item['hint']));
        }

        return ExitCode::OK->value;
    }
}
