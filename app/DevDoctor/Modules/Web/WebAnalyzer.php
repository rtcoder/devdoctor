<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Web;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class WebAnalyzer
{
    public function analyze(WebOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;

        if (! $this->isWebProject($files)) {
            $issues->add(new Issue(
                code: IssueCode::DD_WEB_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No generic web project evidence detected',
                module: ModuleName::WEB,
            ));

            return $issues;
        }

        $this->checkBuildOutput($issues, $files);
        $this->checkPublicSecrets($issues, $files);
        $this->checkAssetReferences($issues, $files);
        $this->checkInsecureConfig($issues, $files);
        $this->checkPortConfig($issues, $files);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_WEB_READY,
                severity: Severity::INFO,
                message: 'Generic web diagnostics found no actionable issues.',
                module: ModuleName::WEB,
            ));
        }

        return $issues;
    }

    private function isWebProject(ProjectFiles $files): bool
    {
        return $files->firstExisting([
            'index.html',
            'public/index.html',
            'package.json',
            'nginx.conf',
            'Caddyfile',
            'httpd.conf',
        ]) !== null;
    }

    private function checkBuildOutput(IssueCollection $issues, ProjectFiles $files): void
    {
        $package = $files->json('package.json');
        $scripts = is_array($package['scripts'] ?? null) ? $package['scripts'] : [];

        if (! array_key_exists('build', $scripts)) {
            return;
        }

        if ($files->firstExisting(['dist/index.html', 'build/index.html', 'public/index.html']) !== null) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_WEB_BUILD_OUTPUT_MISSING,
            severity: Severity::INFO,
            message: 'Web project has a build script but no common build output entry file was found',
            module: ModuleName::WEB,
            file: 'package.json',
        ));
    }

    private function checkPublicSecrets(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (['public/.env', 'public/env.js', 'public/config.js', 'config.js', 'env.js'] as $file) {
            $contents = $files->contents($file);

            if ($contents === '') {
                continue;
            }

            if (preg_match('/(SECRET|TOKEN|PASSWORD|API_KEY)\s*[:=]/i', $contents) === 1) {
                $issues->add(new Issue(
                    code: IssueCode::DD_WEB_PUBLIC_SECRET,
                    severity: Severity::WARNING,
                    message: 'Public web config appears to contain secret-like keys',
                    module: ModuleName::WEB,
                    file: $file,
                ));
            }
        }
    }

    private function checkAssetReferences(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (['index.html', 'public/index.html'] as $file) {
            $contents = $files->contents($file);

            if ($contents === '') {
                continue;
            }

            $base = str_contains($file, '/') ? dirname($file) : '.';
            preg_match_all('/\b(?:src|href)=["\']([^"\']+)["\']/i', $contents, $matches);

            foreach ($matches[1] as $reference) {
                if ($this->isExternalReference($reference)) {
                    continue;
                }

                $asset = ltrim(parse_url($reference, PHP_URL_PATH) ?: '', '/');
                $candidate = $base === '.' ? $asset : $base.'/'.$asset;

                if ($asset !== '' && ! $files->exists($candidate)) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_WEB_ASSET_REFERENCE_MISSING,
                        severity: Severity::WARNING,
                        message: 'Web entry file references an asset that was not found',
                        module: ModuleName::WEB,
                        file: $file,
                        key: $reference,
                    ));
                }
            }
        }
    }

    private function isExternalReference(string $reference): bool
    {
        return str_starts_with($reference, 'http://')
            || str_starts_with($reference, 'https://')
            || str_starts_with($reference, '//')
            || str_starts_with($reference, '#')
            || str_starts_with($reference, 'mailto:')
            || str_starts_with($reference, 'data:');
    }

    private function checkInsecureConfig(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (['nginx.conf', 'Caddyfile', 'httpd.conf'] as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/listen\s+80\b|ssl\s+off|autoindex\s+on/i', $line) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_WEB_INSECURE_DEFAULT_CONFIG,
                        severity: Severity::WARNING,
                        message: 'Web server config contains an insecure default that should be reviewed',
                        module: ModuleName::WEB,
                        file: $file,
                        line: $lineNumber + 1,
                    ));
                }
            }
        }
    }

    private function checkPortConfig(IssueCollection $issues, ProjectFiles $files): void
    {
        if (preg_match('/^PORT=(\d+)/m', $files->contents('.env'), $envMatch) !== 1) {
            return;
        }

        $package = $files->json('package.json');
        $scripts = is_array($package['scripts'] ?? null) ? $package['scripts'] : [];

        foreach ($scripts as $script) {
            if (is_string($script) && preg_match('/--port[=\s]+(\d+)/', $script, $scriptMatch) === 1 && $scriptMatch[1] !== $envMatch[1]) {
                $issues->add(new Issue(
                    code: IssueCode::DD_WEB_PORT_CONFIG_CONFLICT,
                    severity: Severity::WARNING,
                    message: 'Web port configuration disagrees between .env and package scripts',
                    module: ModuleName::WEB,
                    file: 'package.json',
                    key: 'PORT',
                    context: ['env_port' => $envMatch[1], 'script_port' => $scriptMatch[1]],
                ));
            }
        }
    }
}
