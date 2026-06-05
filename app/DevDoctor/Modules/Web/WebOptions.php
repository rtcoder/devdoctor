<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Web;

final readonly class WebOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
