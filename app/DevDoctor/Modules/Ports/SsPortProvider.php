<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

use App\DevDoctor\Core\ProcessRunner;

final readonly class SsPortProvider implements PortProviderInterface
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
    )
    {
    }

    public function available(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && $this->processRunner->run(['which', 'ss'], getcwd())->successful();
    }

    public function usages(int $port): array
    {
        $result = $this->processRunner->run(['ss', '-ltnp', 'sport = :' . $port], getcwd());

        if (!$result->successful()) {
            return [];
        }

        return $result->stdout
                |> trim(...)
                |> (fn($x) => explode(PHP_EOL, $x))
                |> array_filter(...)
                |> (fn($x) => array_map(fn(string $line): ?PortUsage => $this->parseLine($line, $port), $x))
                |> array_filter(...)
                |> array_values(...);
    }

    private function parseLine(string $line, int $port): ?PortUsage
    {
        if (!str_starts_with(trim($line), 'LISTEN')) {
            return null;
        }

        if (preg_match('/users:\(\("([^"]+)",pid=(\d+)/', $line, $matches) !== 1) {
            return new PortUsage($port, new ProcessInfo(0, 'unknown'), $line);
        }

        return new PortUsage(
            port: $port,
            process: new ProcessInfo((int)$matches[2], $matches[1]),
            address: $line,
        );
    }
}
