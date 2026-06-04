<?php

declare(strict_types=1);

namespace Tests\Support;

use App\DevDoctor\Core\CommandAvailabilityInterface;

final readonly class FakeCommandAvailability implements CommandAvailabilityInterface
{
    /**
     * @param  list<string>  $commands
     */
    public function __construct(
        private array $commands = [],
    ) {}

    public function available(string $command): bool
    {
        return in_array($command, $this->commands, true);
    }

    public function find(string $command): ?string
    {
        return $this->available($command) ? $command : null;
    }
}
