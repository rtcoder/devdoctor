<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

use App\DevDoctor\Core\CommandAvailability;
use App\DevDoctor\Core\CommandAvailabilityInterface;
use App\DevDoctor\Core\Platform;

final readonly class WindowsNetstatPortProvider implements PortProviderInterface
{
    public function __construct(
        private PortCommandRunnerInterface $runner = new ProcessPortCommandRunner,
        private CommandAvailabilityInterface $commands = new CommandAvailability,
        private Platform $platform = Platform::OTHER,
    ) {}

    public function available(): bool
    {
        $platform = $this->platform === Platform::OTHER ? Platform::current() : $this->platform;

        return $platform === Platform::WINDOWS && $this->commands->available('netstat');
    }

    public function usages(int $port): array
    {
        $result = $this->runner->run(['netstat', '-ano', '-p', 'tcp']);

        if (! $result->successful()) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $line): ?PortUsage => $this->parseLine($line, $port),
            array_filter(explode(PHP_EOL, trim($result->stdout))),
        )));
    }

    private function parseLine(string $line, int $port): ?PortUsage
    {
        $parts = preg_split('/\s+/', trim($line));

        if ($parts === false || count($parts) < 5 || strtoupper($parts[0]) !== 'TCP') {
            return null;
        }

        if (strtoupper($parts[3]) !== 'LISTENING' || ! ctype_digit($parts[4])) {
            return null;
        }

        if (! $this->addressUsesPort($parts[1], $port)) {
            return null;
        }

        return new PortUsage(
            port: $port,
            process: new ProcessInfo((int) $parts[4], $this->processName((int) $parts[4])),
            address: $parts[1],
        );
    }

    private function addressUsesPort(string $address, int $port): bool
    {
        return str_ends_with($address, ':'.$port);
    }

    private function processName(int $pid): string
    {
        if (! $this->commands->available('tasklist')) {
            return 'pid '.$pid;
        }

        $result = $this->runner->run(['tasklist', '/FI', 'PID eq '.$pid, '/FO', 'CSV', '/NH']);

        if (! $result->successful()) {
            return 'pid '.$pid;
        }

        $row = str_getcsv(trim($result->stdout));
        $name = $row[0] ?? '';

        return is_string($name) && $name !== '' ? $name : 'pid '.$pid;
    }
}
