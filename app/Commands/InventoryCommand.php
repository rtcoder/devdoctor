<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\DiagnosticModuleRunner;
use DevDoctor\Core\EcosystemModuleSelector;
use DevDoctor\Core\ExitCode;
use DevDoctor\Modules\Presets\PresetDetector;
use LaravelZero\Framework\Commands\Command;

final class InventoryCommand extends Command
{
    protected $signature = 'inventory
        {--path=. : Project path to inspect}
        {--format=table : Output format: table or json}';

    protected $description = 'Show detected presets and available DevDoctor modules.';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        $presets = app(PresetDetector::class)->detect($path);
        $runner = app(DiagnosticModuleRunner::class);
        $defaultModules = ['env', 'php', 'node', 'laravel', 'composer', 'git', 'docker'];
        $autoModules = app(EcosystemModuleSelector::class)->addDetected($path, $defaultModules);
        $payload = [
            'tool' => 'devdoctor',
            'path' => $path,
            'presets' => array_map(static fn ($match): array => [
                'preset' => $match->preset->value,
                'label' => $match->preset->label(),
                'evidence' => $match->evidenceFile,
            ], $presets),
            'available_modules' => $runner->knownModules(),
            'auto_modules' => $autoModules,
        ];

        if ((string) $this->option('format') === 'json') {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ExitCode::OK->value;
        }

        $this->output->writeln('Presets: '.($payload['presets'] === [] ? 'none' : implode(', ', array_column($payload['presets'], 'preset'))));
        $this->output->writeln('Auto modules: '.implode(', ', $payload['auto_modules']));

        return ExitCode::OK->value;
    }
}
