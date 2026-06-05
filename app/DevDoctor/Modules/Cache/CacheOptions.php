<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Cache;

final readonly class CacheOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
        public int $maxSizeMb = 512,
    ) {}
}
