<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Node;

interface NodeRuntimeInterface
{
    public function available(): bool;

    public function version(string $path): ?string;
}
