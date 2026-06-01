<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Git;

final readonly class GitOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
        public bool $requireClean = false,
        public bool $requireUpstream = false,
        public bool $scanSensitive = true,
        public bool $scanLargeFiles = false,
        public string $largeFileThreshold = '10M',
    ) {}
}
