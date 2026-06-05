<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Security;

final readonly class SecurityOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
        public int $maxFileSizeBytes = 1048576,
    ) {}
}
