<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class VersionResolver
{
    public function current(): string
    {
        $composerVersion = $this->composerVersion();

        if ($composerVersion !== null) {
            return $composerVersion;
        }

        $configVersion = config('app.version');

        return is_string($configVersion) && $configVersion !== '' ? $configVersion : '0.0.0';
    }

    private function composerVersion(): ?string
    {
        $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $version = $decoded['extra']['devdoctor']['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }
}
