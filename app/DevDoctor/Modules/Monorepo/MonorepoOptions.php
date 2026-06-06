<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Monorepo;

final readonly class MonorepoOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
