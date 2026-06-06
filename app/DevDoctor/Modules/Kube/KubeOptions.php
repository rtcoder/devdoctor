<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Kube;

final readonly class KubeOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
