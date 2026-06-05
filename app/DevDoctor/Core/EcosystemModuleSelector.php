<?php

declare(strict_types=1);

namespace DevDoctor\Core;

use DevDoctor\Modules\Presets\PresetDetector;
use DevDoctor\Modules\Presets\ProjectPreset;

final readonly class EcosystemModuleSelector
{
    public function __construct(
        private PresetDetector $detector = new PresetDetector,
    ) {}

    /**
     * @param  list<string>  $modules
     * @return list<string>
     */
    public function addDetected(string $path, array $modules): array
    {
        $presets = array_map(
            static fn ($match): ProjectPreset => $match->preset,
            $this->detector->detect($path),
        );

        if ($this->hasAny($presets, [ProjectPreset::FRONTEND, ProjectPreset::VITE, ProjectPreset::NEXTJS, ProjectPreset::NUXT, ProjectPreset::ASTRO])) {
            $modules[] = ModuleName::FRONTEND->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::PYTHON, ProjectPreset::PIP, ProjectPreset::POETRY, ProjectPreset::PIPENV, ProjectPreset::UV, ProjectPreset::CONDA])) {
            $modules[] = ModuleName::PYTHON->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::GO])) {
            $modules[] = ModuleName::GO->value;
        }

        return array_values(array_unique($modules));
    }

    /**
     * @param  list<ProjectPreset>  $presets
     * @param  list<ProjectPreset>  $needles
     */
    private function hasAny(array $presets, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $presets, true)) {
                return true;
            }
        }

        return false;
    }
}
