<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Php;

interface PhpRuntimeInterface
{
    public function version(): string;

    /**
     * @return list<string>
     */
    public function loadedExtensions(): array;

    public function iniValue(string $key): string|false;

    public function iniFile(): string|false;

    public function xdebugEnabled(): bool;
}
