<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Core\Config\ConfigWizard;
use DevDoctor\Core\ExitCode;
use DevDoctor\Core\PathResolver;
use LaravelZero\Framework\Commands\Command;

final class InitCommand extends Command
{
    protected $signature = 'init
        {--path=. : Project path to inspect}
        {--config=devdoctor.yml : Config file name}
        {--dry-run : Print generated config without writing it}
        {--force : Allow replacing an existing config file}
        {--ci : Use CI-safe behavior}';

    protected $description = 'Generate an initial devdoctor.yml configuration.';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        $paths = PathResolver::fromBasePath($path);
        $configFile = (string) $this->option('config');
        $target = $paths->absolute($configFile);
        $yaml = app(ConfigWizard::class)->generate($path);

        $this->output->writeln($yaml);

        if ((bool) $this->option('dry-run')) {
            return ExitCode::OK->value;
        }

        if ((bool) $this->option('ci') || ! $this->input->isInteractive()) {
            $this->components->error('Writing config requires an interactive confirmation. Use --dry-run in CI or non-interactive mode.');

            return ExitCode::INVALID_CONFIG->value;
        }

        if (is_file($target) && ! (bool) $this->option('force')) {
            $this->components->error($configFile.' already exists. Use --force to allow replacement.');

            return ExitCode::INVALID_CONFIG->value;
        }

        if (! $this->confirm('Write '.$configFile.'?', false)) {
            $this->components->info('No file was written.');

            return ExitCode::OK->value;
        }

        if (is_file($target) && ! $this->confirm('Replace the existing '.$configFile.'?', false)) {
            $this->components->info('No file was written.');

            return ExitCode::OK->value;
        }

        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($target, $yaml);
        $this->components->info('Created '.$configFile.'.');

        return ExitCode::OK->value;
    }
}
