<?php

declare(strict_types=1);

namespace DevDoctor\Core\Output;

use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\ModuleStatus;

final class JsonRenderer
{
    /**
     * @param  list<ModuleResult>  $results
     */
    public function render(array $results, bool $summaryOnly = false): string
    {
        $errors = $warnings = $info = $suppressed = 0;

        foreach ($results as $result) {
            $summary = $result->issues->summary();
            $errors += $summary['errors'];
            $warnings += $summary['warnings'];
            $info += $summary['info'];
            $suppressed += $summary['suppressed'];
        }

        return json_encode([
            'tool' => 'devdoctor',
            'schema_version' => '1.0',
            'status' => ModuleStatus::fromSummary([
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
                'suppressed' => $suppressed,
            ])->value,
            'summary' => [
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
                'suppressed' => $suppressed,
            ],
            'modules' => array_map(
                static fn (ModuleResult $result): array => $summaryOnly
                    ? array_diff_key($result->toArray(), ['issues' => true])
                    : $result->toArray(),
                $results,
            ),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
