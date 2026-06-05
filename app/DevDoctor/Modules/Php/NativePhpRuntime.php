<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Php;

final class NativePhpRuntime implements PhpRuntimeInterface
{
    public function version(): string
    {
        return PHP_VERSION;
    }

    public function loadedExtensions(): array
    {
        $extensions = get_loaded_extensions();
        sort($extensions);

        return array_values($extensions);
    }

    public function iniValue(string $key): string|false
    {
        return ini_get($key);
    }

    public function iniFile(): string|false
    {
        return php_ini_loaded_file();
    }

    public function xdebugEnabled(): bool
    {
        $mode = strtolower((string) getenv('XDEBUG_MODE'));

        return extension_loaded('xdebug') && $mode !== 'off';
    }
}
