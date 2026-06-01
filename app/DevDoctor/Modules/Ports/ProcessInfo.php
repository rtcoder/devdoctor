<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

final readonly class ProcessInfo
{
    public function __construct(
        public int $pid,
        public string $command,
    ) {}
}
