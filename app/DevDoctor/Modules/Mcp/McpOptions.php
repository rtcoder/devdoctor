<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mcp;

final readonly class McpOptions
{
    public function __construct(
        public string $path,
        public ?string $config = null,
        public bool $strict = false,
        /** @var list<string> */
        public array $allowedCommands = [],
        /** @var list<string> */
        public array $deniedCommands = [],
        public bool $disallowRemote = false,
    ) {}
}
