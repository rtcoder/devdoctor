<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Frontend;

final readonly class FrontendOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
