<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Presets;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\Severity;

final readonly class PresetsAnalyzer
{
    public function __construct(
        private PresetDetector $detector = new PresetDetector,
    ) {}

    public function analyze(string $path): IssueCollection
    {
        $issues = new IssueCollection;
        $matches = $this->detector->detect($path);

        if ($matches === []) {
            $issues->add(new Issue(
                code: 'DD_PRESET_NONE_DETECTED',
                severity: Severity::INFO,
                message: 'No supported project presets detected.',
                module: 'presets',
            ));

            return $issues;
        }

        foreach ($matches as $match) {
            $issues->add(new Issue(
                code: 'DD_PRESET_DETECTED',
                severity: Severity::INFO,
                message: $match->preset->label().' project preset detected.',
                module: 'presets',
                file: $match->evidenceFile,
                key: $match->preset->value,
                context: ['preset' => $match->preset->value],
            ));
        }

        return $issues;
    }
}
