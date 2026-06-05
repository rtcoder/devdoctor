<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Symfony;

final readonly class SymfonyOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
