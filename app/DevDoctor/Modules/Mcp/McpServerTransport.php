<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mcp;

enum McpServerTransport: string
{
    case STDIO = 'stdio';
    case SSE = 'sse';
    case HTTP = 'http';

    public static function fromConfig(?string $transport, ?string $command, ?string $url): ?self
    {
        if ($transport !== null && $transport !== '') {
            return self::tryFrom(strtolower($transport));
        }

        if ($command !== null && $command !== '') {
            return self::STDIO;
        }

        if ($url !== null && $url !== '') {
            return self::HTTP;
        }

        return null;
    }

    public function isRemote(): bool
    {
        return $this === self::SSE || $this === self::HTTP;
    }
}
