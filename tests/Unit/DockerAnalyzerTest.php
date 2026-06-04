<?php

use DevDoctor\Core\CommandAvailabilityInterface;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ProcessResult;
use DevDoctor\Modules\Docker\DockerAnalyzer;
use DevDoctor\Modules\Docker\DockerOptions;
use DevDoctor\Modules\Docker\DockerRunnerInterface;
use DevDoctor\Modules\Ports\PortProviderInterface;
use DevDoctor\Modules\Ports\PortUsage;
use DevDoctor\Modules\Ports\ProcessInfo;
use Tests\Support\FakeCommandAvailability;

it('does not require docker when no compose file exists', function () {
    $issues = analyzeDocker(new FakeDockerRunner([]));

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_NO_COMPOSE_PROJECT']);
});

it('reports missing docker binary for compose projects', function () {
    $path = dockerTempPath();
    dockerCompose($path, "services:\n  app:\n    image: nginx\n");

    $issues = analyzeDocker(new FakeDockerRunner([], $path), new DockerOptions($path), commands: new FakeCommandAvailability);

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_BINARY_MISSING']);
});

it('reports daemon and compose config failures', function () {
    $path = dockerTempPath();
    $composeFile = dockerCompose($path, "services:\n  app:\n    image: nginx\n");

    $issues = analyzeDocker(new FakeDockerRunner([
        'which docker' => dockerOk("/usr/bin/docker\n"),
        'docker info --format json' => new ProcessResult(1, '', 'daemon unavailable'),
    ], $path), new DockerOptions($path));

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_DAEMON_UNAVAILABLE']);

    $issues = analyzeDocker(new FakeDockerRunner([
        'which docker' => dockerOk("/usr/bin/docker\n"),
        'docker compose -f '.$composeFile.' config' => new ProcessResult(1, '', 'bad config'),
    ], $path), new DockerOptions($path, daemon: false, containers: false));

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_COMPOSE_CONFIG_INVALID']);
});

it('reports invalid compose yaml and missing env references', function () {
    $path = dockerTempPath();
    $composeFile = dockerCompose($path, "services:\n  app: [");

    $issues = analyzeDocker(new FakeDockerRunner([
        'which docker' => dockerOk("/usr/bin/docker\n"),
        'docker compose -f '.$composeFile.' config' => dockerOk(''),
    ], $path), new DockerOptions($path, daemon: false, containers: false));

    expect(dockerCodes($issues))->toContain('DD_DOCKER_COMPOSE_INVALID');

    $composeFile = dockerCompose($path, "services:\n  app:\n    image: \${DD_TEST_IMAGE_MISSING}\n");

    $issues = analyzeDocker(new FakeDockerRunner([
        'which docker' => dockerOk("/usr/bin/docker\n"),
        'docker compose -f '.$composeFile.' config' => dockerOk(''),
    ], $path), new DockerOptions($path, daemon: false, containers: false));

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_ENV_REFERENCE_MISSING']);
});

it('respects compose interpolation defaults and required operators', function () {
    $path = dockerTempPath();
    $composeFile = dockerCompose($path, <<<'YAML'
services:
  app:
    image: ${OPTIONAL_IMAGE:-nginx}
    environment:
      OPTIONAL_VALUE: ${OPTIONAL_VALUE-default}
      REQUIRED_VALUE: ${REQUIRED_VALUE?must be set}
YAML);

    $issues = analyzeDocker(new FakeDockerRunner([
        'docker compose -f '.$composeFile.' config' => dockerOk(''),
    ], $path), new DockerOptions($path, daemon: false, containers: false));

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_ENV_REFERENCE_MISSING'])
        ->and($issues->all()[0]->key)->toBe('REQUIRED_VALUE');
});

it('reports compose host port conflicts', function () {
    $path = dockerTempPath();
    $composeFile = dockerCompose($path, "services:\n  web:\n    image: nginx\n    ports:\n      - '8080:80'\n");

    $issues = analyzeDocker(new FakeDockerRunner([
        'which docker' => dockerOk("/usr/bin/docker\n"),
        'docker compose -f '.$composeFile.' config' => dockerOk(''),
    ], $path), new DockerOptions($path, daemon: false, containers: false), new FakeDockerPorts([8080]));

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_HOST_PORT_CONFLICT']);
});

it('reports unhealthy and restarting compose containers', function () {
    $path = dockerTempPath();
    $composeFile = dockerCompose($path, "services:\n  web:\n    image: nginx\n");
    $json = json_encode(['Service' => 'web', 'State' => 'restarting'], JSON_THROW_ON_ERROR)."\n";
    $json .= json_encode(['Service' => 'db', 'Health' => 'unhealthy'], JSON_THROW_ON_ERROR)."\n";

    $issues = analyzeDocker(new FakeDockerRunner([
        'which docker' => dockerOk("/usr/bin/docker\n"),
        'docker compose -f '.$composeFile.' config' => dockerOk(''),
        'docker compose -f '.$composeFile.' ps --format json' => dockerOk($json),
    ], $path), new DockerOptions($path, daemon: false));

    expect(dockerCodes($issues))->toBe([
        'DD_DOCKER_CONTAINER_UNHEALTHY',
        'DD_DOCKER_CONTAINER_UNHEALTHY',
    ]);
});

it('reports ready compose projects', function () {
    $path = dockerTempPath();
    $composeFile = dockerCompose($path, "services:\n  web:\n    image: nginx\n");

    $issues = analyzeDocker(new FakeDockerRunner([
        'which docker' => dockerOk("/usr/bin/docker\n"),
        'docker compose -f '.$composeFile.' config' => dockerOk(''),
        'docker compose -f '.$composeFile.' ps --format json' => dockerOk(''),
    ], $path), new DockerOptions($path, daemon: false));

    expect(dockerCodes($issues))->toBe(['DD_DOCKER_READY']);
});

function analyzeDocker(
    FakeDockerRunner $runner,
    ?DockerOptions $options = null,
    ?PortProviderInterface $ports = null,
    ?CommandAvailabilityInterface $commands = null,
): IssueCollection {
    $path = $options?->path ?? dockerTempPath();

    return (new DockerAnalyzer(
        $runner,
        $ports ?? new FakeDockerPorts,
        $commands ?? new FakeCommandAvailability(['docker']),
    ))->analyze($options ?? new DockerOptions($path));
}

function dockerOk(string $stdout): ProcessResult
{
    return new ProcessResult(0, $stdout, '');
}

/**
 * @return list<string>
 */
function dockerCodes(IssueCollection $issues): array
{
    return array_map(static fn ($issue): string => $issue->code, $issues->all());
}

function dockerTempPath(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'devdoctor-docker-'.bin2hex(random_bytes(4));
    mkdir($path);

    return $path;
}

function dockerCompose(string $path, string $content): string
{
    $composeFile = $path.DIRECTORY_SEPARATOR.'docker-compose.yml';
    file_put_contents($composeFile, $content);

    return $composeFile;
}

final class FakeDockerRunner implements DockerRunnerInterface
{
    /**
     * @param  array<string, ProcessResult>  $responses
     */
    public function __construct(
        private array $responses,
        private readonly string $path = '',
    ) {}

    public function run(array $command, string $workingDirectory): ProcessResult
    {
        expect($workingDirectory)->toBe($this->path !== '' ? $this->path : $workingDirectory);

        $key = implode(' ', $command);

        return $this->responses[$key] ?? new ProcessResult(1, '', 'missing fake response: '.$key);
    }
}

final readonly class FakeDockerPorts implements PortProviderInterface
{
    /**
     * @param  list<int>  $usedPorts
     */
    public function __construct(
        private array $usedPorts = [],
    ) {}

    public function available(): bool
    {
        return true;
    }

    public function usages(int $port): array
    {
        if (! in_array($port, $this->usedPorts, true)) {
            return [];
        }

        return [
            new PortUsage($port, new ProcessInfo(1234, 'php'), 'tcp'),
        ];
    }
}
