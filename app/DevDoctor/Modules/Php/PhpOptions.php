<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Php;

final readonly class PhpOptions
{
    public function __construct(
        public string $path,
        public bool $ci = false,
        public bool $strict = false,
        public int $minimumMemoryMb = 128,
    ) {}
}
