<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Go;

final readonly class GoOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
