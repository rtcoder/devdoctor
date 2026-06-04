<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

final readonly class PortUsage
{
    public function __construct(
        public int $port,
        public ProcessInfo $process,
        public ?string $address = null,
    ) {}
}
