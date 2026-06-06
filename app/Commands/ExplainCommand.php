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
        {--format=table : Output format: table or json}
        {--module= : Filter issue codes by module}';

    protected $description = 'Explain DevDoctor issue codes and their hints.';

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $code = $this->argument('code');
        $codes = IssueCode::cases();
        $module = $this->option('module');
        $modulesByCode = $this->modulesByCode();

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

        if (is_string($module) && $module !== '') {
            $codes = array_values(array_filter($codes, static fn (IssueCode $issueCode): bool => ($modulesByCode[$issueCode->value] ?? null) === $module));
        }

        $payload = [
            'tool' => 'devdoctor',
            'issue_codes' => array_map(static fn (IssueCode $issueCode): array => [
                'code' => $issueCode->value,
                'module' => $modulesByCode[$issueCode->value] ?? null,
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

    /**
     * @return array<string, string>
     */
    private function modulesByCode(): array
    {
        $path = dirname(__DIR__, 2).'/schemas/v1/issue-codes.json';
        $catalog = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $modules = [];

        foreach ($catalog['codes'] as $entry) {
            $modules[$entry['code']] = $entry['module'];
        }

        return $modules;
    }
}
