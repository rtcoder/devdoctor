<?php

use App\DevDoctor\Core\Platform;
use App\DevDoctor\Core\ProcessResult;
use App\DevDoctor\Modules\Ports\LsofPortProvider;
use App\DevDoctor\Modules\Ports\PortProviderInterface;
use App\DevDoctor\Modules\Ports\PortsAnalyzer;
use App\DevDoctor\Modules\Ports\PortsOptions;
use App\DevDoctor\Modules\Ports\PortUsage;
use App\DevDoctor\Modules\Ports\ProcessInfo;
use App\DevDoctor\Modules\Ports\SsPortProvider;
use App\DevDoctor\Modules\Ports\SystemPortProvider;
use App\DevDoctor\Modules\Ports\WindowsNetstatPortProvider;
use Tests\Support\FakeCommandAvailability;
use Tests\Support\FakePortCommandRunner;

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
    ]), platform: Platform::LINUX))->analyze(new PortsOptions(path: '.', ports: [8000]));

    $issue = $issues->all()[0];

    expect($issue->code)->toBe('DD_PORT_IN_USE')
        ->and($issue->context['suggested_command'])->toBe('kill -TERM 1234');
});

it('uses a windows taskkill suggestion on windows', function () {
    $issues = (new PortsAnalyzer(new FakePortProvider(usages: [
        8000 => [new PortUsage(8000, new ProcessInfo(1234, 'php'))],
    ]), platform: Platform::WINDOWS))->analyze(new PortsOptions(path: '.', ports: [8000]));

    expect($issues->all()[0]->context['suggested_command'])->toBe('taskkill /PID 1234');
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

it('parses lsof listeners', function () {
    $runner = new FakePortCommandRunner([
        'lsof -nP -iTCP:8000 -sTCP:LISTEN' => new ProcessResult(
            0,
            "COMMAND PID USER FD TYPE DEVICE SIZE/OFF NODE NAME\nphp 1234 user 10u IPv4 0t0 TCP *:8000 (LISTEN)\n",
            '',
        ),
    ]);
    $provider = new LsofPortProvider($runner, new FakeCommandAvailability(['lsof']), Platform::MACOS);

    expect($provider->available())->toBeTrue()
        ->and($provider->usages(8000)[0]->process->pid)->toBe(1234)
        ->and($provider->usages(8000)[0]->process->command)->toBe('php');
});

it('parses ss listeners', function () {
    $runner = new FakePortCommandRunner([
        'ss -ltnp sport = :3000' => new ProcessResult(
            0,
            "State Recv-Q Send-Q Local Address:Port Peer Address:Port Process\nLISTEN 0 511 *:3000 *:* users:((\"node\",pid=2345,fd=20))\n",
            '',
        ),
    ]);
    $provider = new SsPortProvider($runner, new FakeCommandAvailability(['ss']), Platform::LINUX);

    expect($provider->available())->toBeTrue()
        ->and($provider->usages(3000)[0]->process->pid)->toBe(2345)
        ->and($provider->usages(3000)[0]->process->command)->toBe('node');
});

it('parses windows netstat listeners', function () {
    $runner = new FakePortCommandRunner([
        'netstat -ano -p tcp' => new ProcessResult(
            0,
            "  Proto  Local Address          Foreign Address        State           PID\r\n  TCP    0.0.0.0:5173           0.0.0.0:0              LISTENING       3456\r\n",
            '',
        ),
        'tasklist /FI PID eq 3456 /FO CSV /NH' => new ProcessResult(
            0,
            "\"node.exe\",\"3456\",\"Console\",\"1\",\"42,000 K\"\r\n",
            '',
        ),
    ]);
    $provider = new WindowsNetstatPortProvider($runner, new FakeCommandAvailability(['netstat', 'tasklist']), Platform::WINDOWS);

    expect($provider->available())->toBeTrue()
        ->and($provider->usages(5173)[0]->process->pid)->toBe(3456)
        ->and($provider->usages(5173)[0]->process->command)->toBe('node.exe');
});
