<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mobile;

final readonly class MobileOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
