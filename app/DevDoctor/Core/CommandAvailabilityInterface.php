<?php

declare(strict_types=1);

namespace DevDoctor\Core;

interface CommandAvailabilityInterface
{
    public function available(string $command): bool;

    public function find(string $command): ?string;
}
