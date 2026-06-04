<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

final readonly class PortsOptions
{
    /**
     * @param list<int|string> $ports
     */
    public function __construct(
        public string $path,
        public array  $ports = [],
        public bool   $common = false,
        public bool   $strict = false,
        public bool   $includeDocker = false,
    )
    {
    }
}
