<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

final readonly class UpdateInstruction
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        public string $method,
        public string $displayCommand,
        public array $command = [],
        public bool $runnable = false,
    ) {}
}
