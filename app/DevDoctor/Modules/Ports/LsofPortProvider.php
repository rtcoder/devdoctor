<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ports;

use DevDoctor\Core\CommandAvailability;
use DevDoctor\Core\CommandAvailabilityInterface;
use DevDoctor\Core\Platform;

final readonly class LsofPortProvider implements PortProviderInterface
{
    public function __construct(
        private PortCommandRunnerInterface $runner = new ProcessPortCommandRunner,
        private CommandAvailabilityInterface $commands = new CommandAvailability,
        private Platform $platform = Platform::OTHER,
    ) {}

    public function available(): bool
    {
        $platform = $this->platform === Platform::OTHER ? Platform::current() : $this->platform;

        return $platform->isUnix() && $this->commands->available('lsof');
    }

    public function usages(int $port): array
    {
        $result = $this->runner->run(['lsof', '-nP', '-iTCP:'.$port, '-sTCP:LISTEN']);

        if (! $result->successful()) {
            return [];
        }

        $lines = preg_split('/\R/', trim($result->stdout)) ?: [];
        $lines = array_values(array_filter($lines));
        array_shift($lines);

        return array_values(array_filter(array_map(
            fn (string $line): ?PortUsage => $this->parseLine($line, $port),
            $lines,
        )));
    }

    private function parseLine(string $line, int $port): ?PortUsage
    {
        $parts = preg_split('/\s+/', trim($line), 9);

        if ($parts === false || count($parts) < 2 || ! ctype_digit($parts[1])) {
            return null;
        }

        return new PortUsage(
            port: $port,
            process: new ProcessInfo((int) $parts[1], $parts[0]),
            address: $parts[8] ?? null,
        );
    }
}
