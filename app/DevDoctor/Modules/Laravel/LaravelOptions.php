<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Laravel;

final readonly class LaravelOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
