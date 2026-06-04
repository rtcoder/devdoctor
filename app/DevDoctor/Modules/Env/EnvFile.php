<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Env;

final readonly class EnvFile
{
    /**
     * @param list<EnvEntry> $entries
     */
    public function __construct(
        public string $path,
        public array  $entries,
        public bool   $exists = true,
    )
    {
    }

    public static function missing(string $path): self
    {
        return new self($path, [], false);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function get(string $key): ?EnvEntry
    {
        return array_find($this->entries, fn($entry) => $entry->key === $key);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(
                static fn(EnvEntry $entry): string => $entry->key,
                $this->entries,
            )
                |> array_unique(...)
                |> array_values(...);
    }

    /**
     * @return array<string, list<EnvEntry>>
     */
    public function duplicates(): array
    {
        $byKey = [];

        foreach ($this->entries as $entry) {
            $byKey[$entry->key][] = $entry;
        }

        return array_filter(
            $byKey,
            static fn(array $entries): bool => count($entries) > 1,
        );
    }
}
