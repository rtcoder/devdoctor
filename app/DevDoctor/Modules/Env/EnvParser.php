<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Env;

final class EnvParser
{
    public function parseFile(string $path, string $displayPath): EnvFile
    {
        if (! is_file($path)) {
            return EnvFile::missing($displayPath);
        }

        return $this->parse((string) file_get_contents($path), $displayPath);
    }

    public function parse(string $contents, string $displayPath): EnvFile
    {
        $entries = [];
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($lines as $index => $line) {
            $entry = $this->parseLine($line, $index + 1, $displayPath);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return new EnvFile($displayPath, $entries);
    }

    private function parseLine(string $line, int $lineNumber, string $displayPath): ?EnvEntry
    {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }

        $exported = false;

        if (str_starts_with($trimmed, 'export ')) {
            $exported = true;
            $trimmed = trim(substr($trimmed, strlen('export ')));
        }

        if (! str_contains($trimmed, '=')) {
            return null;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);
        $quoted = false;

        if (strlen($value) >= 2) {
            $quote = $value[0];
            $last = $value[strlen($value) - 1];

            if (($quote === '"' || $quote === "'") && $last === $quote) {
                $quoted = true;
                $value = substr($value, 1, -1);
            }
        }

        return new EnvEntry(
            key: $key,
            value: $value,
            rawLine: $line,
            line: $lineNumber,
            file: $displayPath,
            quoted: $quoted,
            exported: $exported,
        );
    }
}
