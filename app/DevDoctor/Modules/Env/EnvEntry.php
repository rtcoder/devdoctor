<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Env;

final readonly class EnvEntry
{
    public function __construct(
        public string $key,
        public string $value,
        public string $rawLine,
        public int    $line,
        public string $file,
        public bool   $quoted = false,
        public bool   $exported = false,
    )
    {
    }
}
