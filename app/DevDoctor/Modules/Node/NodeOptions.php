<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Node;

final readonly class NodeOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
