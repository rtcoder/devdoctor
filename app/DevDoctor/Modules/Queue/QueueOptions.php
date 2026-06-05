<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Queue;

final readonly class QueueOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
