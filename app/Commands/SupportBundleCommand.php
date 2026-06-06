<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\ExitCode;
use DevDoctor\Core\Redactor;
use DevDoctor\Modules\Presets\PresetDetector;
use LaravelZero\Framework\Commands\Command;

final class SupportBundleCommand extends Command
{
    protected $signature = 'support-bundle
        {--path=. : Project path to inspect}
        {--format=json : Output format: json}';

    protected $description = 'Print a redacted support bundle without writing files.';

    public function handle(Redactor $redactor): int
    {
        $path = (string) $this->option('path');
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $payload = [
            'tool' => 'devdoctor',
            'version' => $composer['extra']['devdoctor']['version'] ?? 'unknown',
            'path' => $path,
            'php' => PHP_VERSION,
            'presets' => array_map(static fn ($match): array => [
                'preset' => $match->preset->value,
                'evidence' => $match->evidenceFile,
            ], app(PresetDetector::class)->detect($path)),
            'environment' => $redactor->redactContext(array_filter([
                'APP_ENV' => getenv('APP_ENV') ?: null,
                'APP_DEBUG' => getenv('APP_DEBUG') ?: null,
                'GITHUB_TOKEN' => getenv('GITHUB_TOKEN') ?: null,
            ])),
        ];

        $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ExitCode::OK->value;
    }
}
