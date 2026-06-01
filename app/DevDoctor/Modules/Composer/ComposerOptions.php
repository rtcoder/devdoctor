<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Composer;

final readonly class ComposerOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
        public bool $validate = true,
        public bool $platformCheck = true,
        public bool $scripts = true,
    ) {}
}
