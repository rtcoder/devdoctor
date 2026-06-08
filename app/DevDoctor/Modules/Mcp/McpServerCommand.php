<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mcp;

final readonly class McpServerCommand
{
    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     */
    public function __construct(
        public string $command,
        public array $args = [],
        public array $env = [],
    ) {}
}
