<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

final readonly class ReleaseInfo
{
    public function __construct(
        public string $version,
        public string $url,
    ) {}
}
