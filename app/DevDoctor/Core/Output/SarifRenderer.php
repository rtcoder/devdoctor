<?php

declare(strict_types=1);

namespace App\DevDoctor\Core\Output;

use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueFingerprint;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Redactor;
use App\DevDoctor\Core\Severity;

final class SarifRenderer
{
    /**
     * @param  list<ModuleResult>  $results
     */
    public function render(array $results): string
    {
        $issues = [];

        foreach ($results as $result) {
            $issues = array_merge($issues, $result->issues->all());
        }

        $rules = [];

        foreach ($issues as $issue) {
            $rules[$issue->code] ??= $this->rule($issue);
        }

        ksort($rules);

        return json_encode([
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'version' => '2.1.0',
            'runs' => [[
                'tool' => [
                    'driver' => [
                        'name' => 'DevDoctor',
                        'informationUri' => 'https://github.com/rtcoder/devdoctor',
                        'rules' => array_values($rules),
                    ],
                ],
                'results' => array_map($this->result(...), $issues),
            ]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    /**
     * @return array<string, mixed>
     */
    private function rule(Issue $issue): array
    {
        $rule = [
            'id' => $issue->code,
            'name' => $issue->code,
            'shortDescription' => ['text' => $issue->message],
        ];

        if ($issue->hint !== null) {
            $rule['help'] = ['text' => $issue->hint];
        }

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Issue $issue): array
    {
        $result = [
            'ruleId' => $issue->code,
            'level' => $this->level($issue->severity),
            'message' => ['text' => $issue->message],
            'partialFingerprints' => [
                'devdoctorFingerprint/v1' => IssueFingerprint::for($issue),
            ],
            'properties' => array_filter([
                'module' => $issue->module,
                'key' => $issue->key,
                'hint' => $issue->hint,
                'fix' => $issue->fix?->toArray(new Redactor),
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
        ];

        if ($issue->file !== null) {
            $region = [];

            if ($issue->line !== null) {
                $region['startLine'] = $issue->line;
            }

            $result['locations'] = [[
                'physicalLocation' => array_filter([
                    'artifactLocation' => ['uri' => str_replace('\\', '/', $issue->file)],
                    'region' => $region,
                ], static fn (mixed $value): bool => $value !== []),
            ]];
        }

        return $result;
    }

    private function level(Severity $severity): string
    {
        return match ($severity) {
            Severity::ERROR => 'error',
            Severity::WARNING => 'warning',
            Severity::INFO => 'note',
        };
    }
}
