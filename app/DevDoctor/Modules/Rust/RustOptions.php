<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Rust;

final readonly class RustOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
