<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Node;

use DevDoctor\Core\CommandAvailability;
use DevDoctor\Core\CommandAvailabilityInterface;
use DevDoctor\Core\ProcessRunner;

final readonly class ProcessNodeRuntime implements NodeRuntimeInterface
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
        private CommandAvailabilityInterface $commands = new CommandAvailability,
    ) {}

    public function available(): bool
    {
        return $this->commands->available('node');
    }

    public function version(string $path): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $result = $this->processRunner->run(['node', '--version'], $path);

        if (! $result->successful()) {
            return null;
        }

        $version = trim($result->stdout);

        return $version === '' ? null : ltrim($version, 'vV');
    }
}
