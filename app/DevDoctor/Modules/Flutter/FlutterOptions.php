<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Flutter;

final readonly class FlutterOptions
{
    public function __construct(
        public string $path,
        public bool $strict = false,
    ) {}
}
