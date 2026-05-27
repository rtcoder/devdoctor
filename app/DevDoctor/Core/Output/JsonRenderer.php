<?php

declare(strict_types=1);

namespace App\DevDoctor\Core\Output;

use App\DevDoctor\Core\ModuleResult;

final class JsonRenderer
{
    /**
     * @param  list<ModuleResult>  $results
     */
    public function render(array $results): string
    {
        $errors = $warnings = $info = 0;

        foreach ($results as $result) {
            $summary = $result->issues->summary();
            $errors += $summary['errors'];
            $warnings += $summary['warnings'];
            $info += $summary['info'];
        }

        return json_encode([
            'tool' => 'devdoctor',
            'status' => $errors > 0 ? 'failed' : ($warnings > 0 ? 'warning' : 'passed'),
            'summary' => [
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
            ],
            'modules' => array_map(
                static fn (ModuleResult $result): array => $result->toArray(),
                $results,
            ),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
