<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

use App\DevDoctor\Core\ProcessRunner;

final readonly class LsofPortProvider implements PortProviderInterface
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
    )
    {
    }

    public function available(): bool
    {
        return $this->processRunner->run(['which', 'lsof'], getcwd())->successful();
    }

    public function usages(int $port): array
    {
        $result = $this->processRunner->run(['lsof', '-nP', '-iTCP:' . $port, '-sTCP:LISTEN'], getcwd());

        if (!$result->successful()) {
            return [];
        }

        $lines = $result->stdout
                |> trim(...)
                |> (fn($x) => explode(PHP_EOL, $x))
                |> array_filter(...)
                |> array_values(...);
        array_shift($lines);

        return array_map(
                fn(string $line): ?PortUsage => $this->parseLine($line, $port),
                $lines,
            )
                |> array_filter(...)
                |> array_values(...);
    }

    private function parseLine(string $line, int $port): ?PortUsage
    {
        $parts = preg_split('/\s+/', trim($line), 9);

        if ($parts === false || count($parts) < 2 || !ctype_digit($parts[1])) {
            return null;
        }

        return new PortUsage(
            port: $port,
            process: new ProcessInfo((int)$parts[1], $parts[0]),
            address: $parts[8] ?? null,
        );
    }
}
