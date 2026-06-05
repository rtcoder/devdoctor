<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Dotnet;

final readonly class DotnetOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
