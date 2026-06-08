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

        if ($this->hasAny($presets, [ProjectPreset::FLUTTER, ProjectPreset::DART])) {
            $modules[] = ModuleName::FLUTTER->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::MOBILE, ProjectPreset::ANDROID, ProjectPreset::IOS])) {
            $modules[] = ModuleName::MOBILE->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::MONOREPO])) {
            $modules[] = ModuleName::MONOREPO->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::PYTHON, ProjectPreset::PIP, ProjectPreset::POETRY, ProjectPreset::PIPENV, ProjectPreset::UV, ProjectPreset::CONDA])) {
            $modules[] = ModuleName::PYTHON->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::RUBY, ProjectPreset::RAILS])) {
            $modules[] = ModuleName::RUBY->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::GO])) {
            $modules[] = ModuleName::GO->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::RUST])) {
            $modules[] = ModuleName::RUST->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::JAVA, ProjectPreset::MAVEN, ProjectPreset::GRADLE, ProjectPreset::ANT, ProjectPreset::SPRING])) {
            $modules[] = ModuleName::JAVA->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::MCP])) {
            $modules[] = ModuleName::MCP->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::IAC, ProjectPreset::TERRAFORM])) {
            $modules[] = ModuleName::IAC->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::KUBERNETES, ProjectPreset::HELM])) {
            $modules[] = ModuleName::KUBE->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::DOTNET])) {
            $modules[] = ModuleName::DOTNET->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::CPP, ProjectPreset::CMAKE])) {
            $modules[] = ModuleName::CPP->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::WEB])) {
            $modules[] = ModuleName::WEB->value;
        }

        if ($this->hasAny($presets, [ProjectPreset::SYMFONY])) {
            $modules[] = ModuleName::SYMFONY->value;
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
