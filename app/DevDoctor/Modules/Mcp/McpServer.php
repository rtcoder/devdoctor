<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mcp;

final readonly class McpServer
{
    public function __construct(
        public string $name,
        public string $file,
        public ?McpServerTransport $transport,
        public ?McpServerCommand $command = null,
        public ?string $url = null,
        /** @var array<string, string> */
        public array $headers = [],
        public ?string $rawTransport = null,
    ) {}
}
