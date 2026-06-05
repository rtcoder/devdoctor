<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ruby;

final readonly class RubyOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
