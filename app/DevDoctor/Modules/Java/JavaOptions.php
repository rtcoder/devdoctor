<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Java;

final readonly class JavaOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
