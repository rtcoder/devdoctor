<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Cpp;

final readonly class CppOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
