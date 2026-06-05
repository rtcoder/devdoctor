<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Database;

final readonly class DatabaseOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
        public bool $connect = false,
    ) {}
}
