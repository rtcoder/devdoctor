<?php

use App\DevDoctor\Modules\Ports\PortProviderInterface;
use App\DevDoctor\Modules\Ports\PortsAnalyzer;
use App\DevDoctor\Modules\Ports\PortsOptions;
use App\DevDoctor\Modules\Ports\PortUsage;
use App\DevDoctor\Modules\Ports\ProcessInfo;
use App\DevDoctor\Modules\Ports\SystemPortProvider;

final class FakePortProvider implements PortProviderInterface
{
    /**
     * @param  array<int, list<PortUsage>>  $usages
     */
    public function __construct(
        private bool $available = true,
        private array $usages = [],
    ) {}

    public function available(): bool
    {
        return $this->available;
    }

    public function usages(int $port): array
    {
        return $this->usages[$port] ?? [];
    }
}

it('reports no issue for a free port', function () {
    $issues = (new PortsAnalyzer(new FakePortProvider))->analyze(new PortsOptions(path: '.', ports: [8000]));

    expect($issues->summary())->toBe(['errors' => 0, 'warnings' => 0, 'info' => 1])
        ->and($issues->all()[0]->code)->toBe('DD_PORTS_READY');
});

it('reports occupied ports with safe kill suggestion', function () {
    $issues = (new PortsAnalyzer(new FakePortProvider(usages: [
        8000 => [new PortUsage(8000, new ProcessInfo(1234, 'php'))],
    ])))->analyze(new PortsOptions(path: '.', ports: [8000]));

    $issue = $issues->all()[0];

    expect($issue->code)->toBe('DD_PORT_IN_USE')
        ->and($issue->context['suggested_command'])->toBe('kill -TERM 1234');
});

it('reports invalid ports without querying providers', function () {
    $issues = (new PortsAnalyzer(new FakePortProvider))->analyze(new PortsOptions(path: '.', ports: ['70000']));

    expect($issues->all()[0]->code)->toBe('DD_PORT_INVALID_PORT');
});

it('reports privileged ports', function () {
    $issues = (new PortsAnalyzer(new FakePortProvider))->analyze(new PortsOptions(path: '.', ports: [80]));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_PORT_PRIVILEGED');
});

it('reports provider unavailable', function () {
    $issues = (new PortsAnalyzer(new FakePortProvider(available: false)))->analyze(new PortsOptions(path: '.', ports: [8000]));

    expect($issues->all()[0]->code)->toBe('DD_PORT_PROVIDER_UNAVAILABLE');
});

it('reports multiple listeners', function () {
    $issues = (new PortsAnalyzer(new FakePortProvider(usages: [
        8000 => [
            new PortUsage(8000, new ProcessInfo(1234, 'php')),
            new PortUsage(8000, new ProcessInfo(2345, 'node')),
        ],
    ])))->analyze(new PortsOptions(path: '.', ports: [8000]));

    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_PORT_MULTIPLE_LISTENERS')
        ->and($codes)->toContain('DD_PORT_IN_USE');
});

it('uses the first available system port provider', function () {
    $provider = new SystemPortProvider([
        new FakePortProvider(available: false),
        new FakePortProvider(usages: [
            3000 => [new PortUsage(3000, new ProcessInfo(4321, 'node'))],
        ]),
    ]);

    expect($provider->available())->toBeTrue()
        ->and($provider->usages(3000)[0]->process->command)->toBe('node');
});

it('reports unavailable when no system port provider is available', function () {
    $provider = new SystemPortProvider([
        new FakePortProvider(available: false),
        new FakePortProvider(available: false),
    ]);

    expect($provider->available())->toBeFalse()
        ->and($provider->usages(3000))->toBe([]);
});
