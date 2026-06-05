<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Iac;

final readonly class IacOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
