<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

use App\DevDoctor\Core\CommandAvailability;
use App\DevDoctor\Core\CommandAvailabilityInterface;
use App\DevDoctor\Core\Platform;

final readonly class SsPortProvider implements PortProviderInterface
{
    public function __construct(
        private PortCommandRunnerInterface $runner = new ProcessPortCommandRunner,
        private CommandAvailabilityInterface $commands = new CommandAvailability,
        private Platform $platform = Platform::OTHER,
    ) {}

    public function available(): bool
    {
        $platform = $this->platform === Platform::OTHER ? Platform::current() : $this->platform;

        return $platform === Platform::LINUX && $this->commands->available('ss');
    }

    public function usages(int $port): array
    {
        $result = $this->runner->run(['ss', '-ltnp', 'sport = :'.$port]);

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
        if (! str_starts_with(trim($line), 'LISTEN')) {
            return null;
        }

        if (preg_match('/users:\(\("([^"]+)",pid=(\d+)/', $line, $matches) !== 1) {
            return new PortUsage($port, new ProcessInfo(0, 'unknown'), $line);
        }

        return new PortUsage(
            port: $port,
            process: new ProcessInfo((int) $matches[2], $matches[1]),
            address: $line,
        );
    }
}
