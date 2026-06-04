<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\Platform;
use App\DevDoctor\Core\Severity;

final readonly class PortsAnalyzer
{
    public function __construct(
        private PortProviderInterface $provider = new SystemPortProvider,
        private CommonPorts $commonPorts = new CommonPorts,
        private Platform $platform = Platform::OTHER,
    ) {}

    public function analyze(PortsOptions $options): IssueCollection
    {
        $issues = new IssueCollection;
        $ports = $this->normalizePorts($options->ports === [] || $options->common ? $this->commonPorts->all() : $options->ports, $issues);

        if ($ports === []) {
            return $issues;
        }

        if (! $this->provider->available()) {
            $issues->add(new Issue(
                code: 'DD_PORT_PROVIDER_UNAVAILABLE',
                severity: Severity::WARNING,
                message: 'No supported port provider is available',
                module: 'ports',
            ));

            return $issues;
        }

        foreach ($ports as $port) {
            if ($port < 1024) {
                $issues->add(new Issue(
                    code: 'DD_PORT_PRIVILEGED',
                    severity: Severity::INFO,
                    message: 'Port '.$port.' may require elevated permissions to bind',
                    module: 'ports',
                    context: ['port' => $port],
                ));
            }

            $usages = $this->provider->usages($port);

            if (count($usages) > 1) {
                $issues->add(new Issue(
                    code: 'DD_PORT_MULTIPLE_LISTENERS',
                    severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                    message: 'Port '.$port.' has multiple listeners',
                    module: 'ports',
                    context: ['port' => $port],
                ));
            }

            foreach ($usages as $usage) {
                $context = [
                    'port' => $port,
                    'pid' => $usage->process->pid,
                    'command' => $usage->process->command,
                ];
                $suggestion = $this->terminationSuggestion($usage->process->pid);

                if ($suggestion !== null) {
                    $context['suggested_command'] = $suggestion;
                }

                $issues->add(new Issue(
                    code: 'DD_PORT_IN_USE',
                    severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                    message: 'Port '.$port.' is used by '.$usage->process->command.' (PID '.$usage->process->pid.')',
                    module: 'ports',
                    context: $context,
                ));
            }
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: 'DD_PORTS_READY',
                severity: Severity::INFO,
                message: 'No checked ports are currently in use.',
                module: 'ports',
            ));
        }

        return $issues;
    }

    /**
     * @param  list<int|string>  $ports
     * @return list<int>
     */
    private function normalizePorts(array $ports, IssueCollection $issues): array
    {
        $normalized = [];

        foreach ($ports as $port) {
            if (! is_numeric($port) || (string) (int) $port !== (string) $port || (int) $port < 1 || (int) $port > 65535) {
                $issues->add(new Issue(
                    code: 'DD_PORT_INVALID_PORT',
                    severity: Severity::WARNING,
                    message: 'Port '.$port.' is not a valid TCP port',
                    module: 'ports',
                    context: ['port' => $port],
                ));

                continue;
            }

            $normalized[] = (int) $port;
        }

        return array_values(array_unique($normalized));
    }

    private function terminationSuggestion(int $pid): ?string
    {
        if ($pid <= 0) {
            return null;
        }

        $platform = $this->platform === Platform::OTHER ? Platform::current() : $this->platform;

        return match ($platform) {
            Platform::WINDOWS => 'taskkill /PID '.$pid,
            Platform::LINUX, Platform::MACOS => 'kill -TERM '.$pid,
            Platform::OTHER => null,
        };
    }
}
