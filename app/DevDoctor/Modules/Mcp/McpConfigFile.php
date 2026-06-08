<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mcp;

final readonly class McpConfigFile
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public string $path,
        public ?array $data = null,
        public ?string $error = null,
    ) {}

    public function isValid(): bool
    {
        return $this->data !== null && $this->error === null;
    }
}
