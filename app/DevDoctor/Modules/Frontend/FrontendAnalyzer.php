<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Frontend;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Presets\PresetDetector;
use DevDoctor\Modules\Presets\ProjectPreset;

final readonly class FrontendAnalyzer
{
    private const array FRONTEND_PRESETS = [
        ProjectPreset::FRONTEND,
        ProjectPreset::VITE,
        ProjectPreset::NEXTJS,
        ProjectPreset::NUXT,
        ProjectPreset::ASTRO,
    ];

    private const array FRONTEND_DEPENDENCIES = [
        '@angular/core',
        '@sveltejs/kit',
        'astro',
        'next',
        'nuxt',
        'react',
        'svelte',
        'vite',
        'vue',
    ];

    public function __construct(
        private PresetDetector $detector = new PresetDetector,
    ) {}

    public function analyze(FrontendOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $matches = array_values(array_filter(
            $this->detector->detect($options->path),
            static fn ($match): bool => in_array($match->preset, self::FRONTEND_PRESETS, true),
        ));

        if ($matches === []) {
            $issues->add(new Issue(
                code: IssueCode::DD_FRONTEND_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No frontend project detected',
                module: ModuleName::FRONTEND,
            ));

            return $issues;
        }

        foreach ($matches as $match) {
            $issues->add(new Issue(
                code: IssueCode::DD_FRONTEND_PRESET_DETECTED,
                severity: Severity::INFO,
                message: $match->preset === ProjectPreset::FRONTEND
                    ? 'Frontend project preset detected.'
                    : $match->preset->label().' frontend preset detected.',
                module: ModuleName::FRONTEND,
                file: $match->evidenceFile,
                key: $match->preset->value,
                context: ['preset' => $match->preset->value],
            ));
        }

        $package = $files->json('package.json');

        if ($package !== [] && $this->hasFrontendDependencies($package) && ! $this->hasBuildScript($package)) {
            $issues->add(new Issue(
                code: IssueCode::DD_FRONTEND_BUILD_SCRIPT_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'Frontend dependencies are present but package.json has no build script',
                module: ModuleName::FRONTEND,
                file: 'package.json',
                key: 'scripts.build',
            ));
        }

        if (! $issues->hasWarnings() && ! $issues->hasErrors()) {
            $issues->add(new Issue(
                code: IssueCode::DD_FRONTEND_READY,
                severity: Severity::INFO,
                message: 'Frontend diagnostics found no actionable issues.',
                module: ModuleName::FRONTEND,
            ));
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function hasBuildScript(array $package): bool
    {
        return is_string($package['scripts']['build'] ?? null)
            && trim($package['scripts']['build']) !== '';
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function hasFrontendDependencies(array $package): bool
    {
        foreach (['dependencies', 'devDependencies'] as $section) {
            if (! is_array($package[$section] ?? null)) {
                continue;
            }

            foreach (self::FRONTEND_DEPENDENCIES as $dependency) {
                if (array_key_exists($dependency, $package[$section])) {
                    return true;
                }
            }
        }

        return false;
    }
}
