<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

use Symfony\Component\Process\ExecutableFinder;

final readonly class CommandAvailability implements CommandAvailabilityInterface
{
    public function __construct(
        private ExecutableFinder $finder = new ExecutableFinder,
    ) {}

    public function available(string $command): bool
    {
        return $this->find($command) !== null;
    }

    public function find(string $command): ?string
    {
        return $this->finder->find($command);
    }
}
