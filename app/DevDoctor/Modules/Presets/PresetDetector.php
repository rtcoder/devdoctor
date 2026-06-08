<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Presets;

use DevDoctor\Core\ProjectFiles;

final readonly class PresetDetector
{
    private const array FRONTEND_PACKAGES = [
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

    /**
     * @return list<PresetMatch>
     */
    public function detect(string $path): array
    {
        $files = new ProjectFiles($path);
        $composer = $files->json('composer.json');
        $package = $files->json('package.json');
        $matches = [];

        if ($this->hasPackage($composer, 'laravel/framework') || $files->exists('artisan')) {
            $matches[] = new PresetMatch(ProjectPreset::LARAVEL, $this->hasPackage($composer, 'laravel/framework') ? 'composer.json' : 'artisan');
        }

        $mcpConfig = $files->firstExisting(['.mcp.json', 'mcp.json', '.cursor/mcp.json', '.vscode/mcp.json']);

        if ($mcpConfig !== null) {
            $matches[] = new PresetMatch(ProjectPreset::MCP, $mcpConfig);
        }

        if ($this->hasPackage($composer, 'symfony/framework-bundle') || $files->exists('bin/console')) {
            $matches[] = new PresetMatch(ProjectPreset::SYMFONY, $this->hasPackage($composer, 'symfony/framework-bundle') ? 'composer.json' : 'bin/console');
        }

        if ($files->exists('package.json')) {
            $matches[] = new PresetMatch(ProjectPreset::NODE, 'package.json');
        }

        $frontendEvidence = $this->frontendEvidence($files, $package);

        if ($frontendEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::FRONTEND, $frontendEvidence);
        }

        $viteConfig = $files->firstExisting([
            'vite.config.js',
            'vite.config.mjs',
            'vite.config.ts',
            'vite.config.cjs',
        ]);

        if ($this->hasPackage($package, 'vite') || $viteConfig !== null) {
            $matches[] = new PresetMatch(ProjectPreset::VITE, $this->hasPackage($package, 'vite') ? 'package.json' : (string) $viteConfig);
        }

        if ($this->hasPackage($package, 'next')) {
            $matches[] = new PresetMatch(ProjectPreset::NEXTJS, 'package.json');
        }

        if ($this->hasPackage($package, 'nuxt')) {
            $matches[] = new PresetMatch(ProjectPreset::NUXT, 'package.json');
        }

        if ($this->hasPackage($package, 'astro')) {
            $matches[] = new PresetMatch(ProjectPreset::ASTRO, 'package.json');
        }

        array_push($matches, ...$this->flutterMatches($files));
        array_push($matches, ...$this->mobileMatches($files));
        array_push($matches, ...$this->monorepoMatches($files, $package));
        array_push($matches, ...$this->pythonMatches($files));
        array_push($matches, ...$this->rubyMatches($files));
        array_push($matches, ...$this->goMatches($files));
        array_push($matches, ...$this->rustMatches($files));
        array_push($matches, ...$this->javaMatches($files));
        array_push($matches, ...$this->cppMatches($files));
        array_push($matches, ...$this->dotnetMatches($files));
        array_push($matches, ...$this->webMatches($files, $frontendEvidence));
        array_push($matches, ...$this->iacMatches($files));
        array_push($matches, ...$this->kubeMatches($files));

        $composeFile = $files->firstExisting([
            'docker-compose.yml',
            'docker-compose.yaml',
            'compose.yml',
            'compose.yaml',
        ]);

        if ($composeFile !== null) {
            $matches[] = new PresetMatch(ProjectPreset::DOCKER_COMPOSE, $composeFile);
        }

        return $matches;
    }

    private function frontendEvidence(ProjectFiles $files, array $package): ?string
    {
        foreach (self::FRONTEND_PACKAGES as $dependency) {
            if ($this->hasPackage($package, $dependency)) {
                return 'package.json';
            }
        }

        return $files->firstExisting([
            'index.html',
            'src/App.vue',
            'src/App.svelte',
            'src/App.tsx',
            'src/App.jsx',
            'app/page.tsx',
            'pages/index.tsx',
            'vite.config.js',
            'vite.config.mjs',
            'vite.config.ts',
            'vite.config.cjs',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasPackage(array $data, string $package): bool
    {
        foreach (['require', 'require-dev', 'dependencies', 'devDependencies'] as $section) {
            if (is_array($data[$section] ?? null) && array_key_exists($package, $data[$section])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<PresetMatch>
     */
    private function flutterMatches(ProjectFiles $files): array
    {
        $matches = [];
        $evidence = $files->firstExisting(['pubspec.yaml', 'pubspec.lock', '.metadata']);

        if ($evidence === null) {
            return [];
        }

        $matches[] = new PresetMatch(ProjectPreset::DART, $evidence);

        if ($files->exists('.metadata') || $files->contains('pubspec.yaml', 'sdk: flutter')) {
            $matches[] = new PresetMatch(ProjectPreset::FLUTTER, $files->exists('.metadata') ? '.metadata' : 'pubspec.yaml');
        }

        return $matches;
    }

    /**
     * @return list<PresetMatch>
     */
    private function mobileMatches(ProjectFiles $files): array
    {
        $matches = [];

        $androidEvidence = $files->firstExisting(['android/app/build.gradle', 'android/app/build.gradle.kts', 'android/app/src/main/AndroidManifest.xml', 'app/src/main/AndroidManifest.xml']);
        $iosEvidence = $files->firstExisting(['Podfile', 'Podfile.lock', 'ios/Runner.xcodeproj/project.pbxproj']);

        if ($androidEvidence !== null || $iosEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::MOBILE, $androidEvidence ?? (string) $iosEvidence);
        }

        if ($androidEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::ANDROID, $androidEvidence);
        }

        if ($iosEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::IOS, $iosEvidence);
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $package
     * @return list<PresetMatch>
     */
    private function monorepoMatches(ProjectFiles $files, array $package): array
    {
        $evidence = $files->firstExisting(['nx.json', 'turbo.json', 'lerna.json', 'pnpm-workspace.yaml', 'rush.json', 'WORKSPACE', 'MODULE.bazel', 'pants.toml']);

        if ($evidence === null && is_array($package['workspaces'] ?? null)) {
            $evidence = 'package.json';
        }

        return $evidence === null ? [] : [new PresetMatch(ProjectPreset::MONOREPO, $evidence)];
    }

    /**
     * @return list<PresetMatch>
     */
    private function iacMatches(ProjectFiles $files): array
    {
        $matches = [];
        $terraformFiles = $files->glob('*.tf');
        $evidence = $terraformFiles[0] ?? $files->firstExisting(['terragrunt.hcl', '.terraform.lock.hcl', 'tofu.lock.hcl']);

        if ($evidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::IAC, $evidence);
        }

        if ($terraformFiles !== [] || $files->firstExisting(['.terraform.lock.hcl', 'tofu.lock.hcl', 'terragrunt.hcl']) !== null) {
            $matches[] = new PresetMatch(ProjectPreset::TERRAFORM, $terraformFiles[0] ?? 'terragrunt.hcl');
        }

        return $matches;
    }

    /**
     * @return list<PresetMatch>
     */
    private function kubeMatches(ProjectFiles $files): array
    {
        $matches = [];
        $chart = $files->firstExisting(['Chart.yaml', 'Chart.yml']);
        $kubeEvidence = $chart
            ?? $files->firstExisting(['helmfile.yaml', 'helmfile.yml', 'kustomization.yaml', 'kustomization.yml', 'values.yaml', 'values.yml'])
            ?? $this->firstKubernetesManifest($files);

        if ($kubeEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::KUBERNETES, $kubeEvidence);
        }

        if ($chart !== null || $files->firstExisting(['Chart.lock', 'helmfile.yaml', 'helmfile.yml', 'values.yaml', 'values.yml']) !== null) {
            $matches[] = new PresetMatch(ProjectPreset::HELM, $chart ?? 'values.yaml');
        }

        return $matches;
    }

    private function firstKubernetesManifest(ProjectFiles $files): ?string
    {
        foreach ([...$files->glob('*.yaml'), ...$files->glob('*.yml'), ...$files->glob('k8s/*.yaml'), ...$files->glob('k8s/*.yml'), ...$files->glob('manifests/*.yaml'), ...$files->glob('manifests/*.yml')] as $file) {
            if ($files->contains($file, 'apiVersion:') && $files->contains($file, 'kind:')) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return list<PresetMatch>
     */
    private function rubyMatches(ProjectFiles $files): array
    {
        $matches = [];
        $rubyEvidence = $files->firstExisting(['Gemfile', 'gems.rb', '.ruby-version', 'config/application.rb']);

        if ($rubyEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::RUBY, $rubyEvidence);
        }

        if ($files->contains('Gemfile', 'rails') || $files->exists('config/application.rb') || $files->exists('bin/rails')) {
            $matches[] = new PresetMatch(ProjectPreset::RAILS, $files->exists('config/application.rb') ? 'config/application.rb' : 'Gemfile');
        }

        return $matches;
    }

    /**
     * @return list<PresetMatch>
     */
    private function pythonMatches(ProjectFiles $files): array
    {
        $matches = [];
        $pythonEvidence = $files->firstExisting(['pyproject.toml', 'requirements.txt', 'Pipfile', 'environment.yml', 'conda-lock.yml', 'uv.lock']);

        $requirementsFiles = $files->glob('requirements*.txt');

        if ($pythonEvidence !== null || $requirementsFiles !== []) {
            $matches[] = new PresetMatch(ProjectPreset::PYTHON, $pythonEvidence ?? $requirementsFiles[0]);
        }

        if ($requirementsFiles !== []) {
            $matches[] = new PresetMatch(ProjectPreset::PIP, $requirementsFiles[0]);
        }

        foreach ([
            [ProjectPreset::POETRY, ['poetry.lock']],
            [ProjectPreset::PIPENV, ['Pipfile']],
            [ProjectPreset::UV, ['uv.lock']],
            [ProjectPreset::CONDA, ['environment.yml', 'conda-lock.yml']],
        ] as [$preset, $candidates]) {
            $evidence = $files->firstExisting($candidates);

            if ($evidence !== null) {
                $matches[] = new PresetMatch($preset, $evidence);
            }
        }

        return $matches;
    }

    /**
     * @return list<PresetMatch>
     */
    private function goMatches(ProjectFiles $files): array
    {
        $evidence = $files->firstExisting(['go.mod', 'go.work']);

        return $evidence === null ? [] : [new PresetMatch(ProjectPreset::GO, $evidence)];
    }

    /**
     * @return list<PresetMatch>
     */
    private function rustMatches(ProjectFiles $files): array
    {
        $evidence = $files->firstExisting(['Cargo.toml', 'Cargo.lock', 'rust-toolchain.toml']);

        return $evidence === null ? [] : [new PresetMatch(ProjectPreset::RUST, $evidence)];
    }

    /**
     * @return list<PresetMatch>
     */
    private function javaMatches(ProjectFiles $files): array
    {
        $matches = [];
        $javaEvidence = $files->firstExisting(['pom.xml', 'build.gradle', 'build.gradle.kts', 'settings.gradle', 'settings.gradle.kts', 'build.xml']);

        if ($javaEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::JAVA, $javaEvidence);
        }

        foreach ([
            [ProjectPreset::MAVEN, ['pom.xml', 'mvnw', 'mvnw.cmd']],
            [ProjectPreset::GRADLE, ['build.gradle', 'build.gradle.kts', 'gradlew', 'gradlew.bat']],
            [ProjectPreset::ANT, ['build.xml']],
        ] as [$preset, $candidates]) {
            $evidence = $files->firstExisting($candidates);

            if ($evidence !== null) {
                $matches[] = new PresetMatch($preset, $evidence);
            }
        }

        if (
            $files->contains('pom.xml', 'spring-boot')
            || $files->contains('build.gradle', 'spring-boot')
            || $files->contains('build.gradle.kts', 'spring-boot')
        ) {
            $matches[] = new PresetMatch(ProjectPreset::SPRING, $javaEvidence ?? 'pom.xml');
        }

        return $matches;
    }

    /**
     * @return list<PresetMatch>
     */
    private function cppMatches(ProjectFiles $files): array
    {
        $matches = [];
        $cppEvidence = $files->firstExisting(['CMakeLists.txt', 'Makefile', 'meson.build', 'configure.ac', 'vcpkg.json', 'conanfile.txt', 'conanfile.py']);

        if ($cppEvidence !== null) {
            $matches[] = new PresetMatch(ProjectPreset::CPP, $cppEvidence);
        }

        if ($files->exists('CMakeLists.txt')) {
            $matches[] = new PresetMatch(ProjectPreset::CMAKE, 'CMakeLists.txt');
        }

        return $matches;
    }

    /**
     * @return list<PresetMatch>
     */
    private function dotnetMatches(ProjectFiles $files): array
    {
        $evidence = $files->firstExisting(['global.json', 'NuGet.config']);

        if ($evidence === null) {
            $projectFiles = [
                ...$files->glob('*.sln'),
                ...$files->glob('*.csproj'),
                ...$files->glob('*.fsproj'),
                ...$files->glob('*.vbproj'),
            ];

            $evidence = $projectFiles[0] ?? null;
        }

        return $evidence === null ? [] : [new PresetMatch(ProjectPreset::DOTNET, $evidence)];
    }

    /**
     * @return list<PresetMatch>
     */
    private function webMatches(ProjectFiles $files, ?string $frontendEvidence): array
    {
        $evidence = $files->firstExisting(['index.html', 'public/index.html', 'nginx.conf', 'Caddyfile', 'vite.config.ts', 'vite.config.js']) ?? $frontendEvidence;

        return $evidence === null ? [] : [new PresetMatch(ProjectPreset::WEB, $evidence)];
    }
}
