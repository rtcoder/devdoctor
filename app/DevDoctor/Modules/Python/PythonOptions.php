<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Python;

final readonly class PythonOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
