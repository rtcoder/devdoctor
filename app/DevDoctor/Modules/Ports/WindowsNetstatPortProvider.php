<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

use App\DevDoctor\Core\ProcessRunner;

final readonly class WindowsNetstatPortProvider implements PortProviderInterface
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
    )
    {
    }

    public function available(): bool
    {
        return PHP_OS_FAMILY === 'Windows'
            && $this->processRunner->run(['where', 'netstat'], getcwd())->successful();
    }

    public function usages(int $port): array
    {
        $result = $this->processRunner->run(['netstat', '-ano', '-p', 'tcp'], getcwd());

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
        $parts = preg_split('/\s+/', trim($line));

        if ($parts === false || count($parts) < 5 || strtoupper($parts[0]) !== 'TCP') {
            return null;
        }

        if (strtoupper($parts[3]) !== 'LISTENING' || !ctype_digit($parts[4])) {
            return null;
        }

        if (!$this->addressUsesPort($parts[1], $port)) {
            return null;
        }

        return new PortUsage(
            port: $port,
            process: new ProcessInfo((int)$parts[4], 'pid ' . $parts[4]),
            address: $parts[1],
        );
    }

    private function addressUsesPort(string $address, int $port): bool
    {
        return str_ends_with($address, ':' . $port);
    }
}
