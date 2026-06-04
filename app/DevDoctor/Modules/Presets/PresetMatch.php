<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Presets;

final readonly class PresetMatch
{
    public function __construct(
        public ProjectPreset $preset,
        public string $evidenceFile,
    ) {}
}
