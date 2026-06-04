<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Docker;

final readonly class DockerOptions
{
    public function __construct(
        public string  $path,
        public bool    $strict = false,
        public ?string $composeFile = null,
        public bool    $daemon = true,
        public bool    $containers = true,
    )
    {
    }
}
