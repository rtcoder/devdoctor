<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Docker;

use App\DevDoctor\Core\CommandAvailability;
use App\DevDoctor\Core\CommandAvailabilityInterface;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\PathResolver;
use App\DevDoctor\Core\Severity;
use App\DevDoctor\Modules\Ports\PortProviderInterface;
use App\DevDoctor\Modules\Ports\SystemPortProvider;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class DockerAnalyzer
{
    /** @var list<string> */
    private const array COMPOSE_FILES = [
        'docker-compose.yml',
        'docker-compose.yaml',
        'compose.yml',
        'compose.yaml',
    ];

    public function __construct(
        private DockerRunnerInterface $runner = new ProcessDockerRunner,
        private PortProviderInterface $ports = new SystemPortProvider,
        private CommandAvailabilityInterface $commands = new CommandAvailability,
        private ComposeEnvReferenceScanner $envReferences = new ComposeEnvReferenceScanner,
    ) {}

    public function analyze(DockerOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $composeFiles = $this->composeFiles($paths, $options);

        if ($composeFiles === []) {
            $issues->add(new Issue(
                code: 'DD_DOCKER_NO_COMPOSE_PROJECT',
                severity: Severity::INFO,
                message: 'No Docker Compose file detected.',
                module: 'docker',
            ));

            return $issues;
        }

        if (! $this->dockerAvailable($issues, $options)) {
            return $issues;
        }

        foreach ($composeFiles as $composeFile) {
            $data = $this->parseCompose($issues, $composeFile);

            $this->checkComposeConfig($issues, $options->path, $composeFile);
            $this->checkMissingEnvReferences($issues, $paths, $composeFile);

            if (is_array($data)) {
                $this->checkHostPortConflicts($issues, $data);
            }

            if ($options->containers) {
                $this->checkContainers($issues, $options->path, $composeFile);
            }
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: 'DD_DOCKER_READY',
                severity: Severity::INFO,
                message: 'Docker diagnostics found no issues.',
                module: 'docker',
            ));
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function composeFiles(PathResolver $paths, DockerOptions $options): array
    {
        if (is_string($options->composeFile) && $options->composeFile !== '') {
            return [$paths->absolute($options->composeFile)];
        }

        $files = [];

        foreach (self::COMPOSE_FILES as $file) {
            $absolute = $paths->absolute($file);

            if (is_file($absolute)) {
                $files[] = $absolute;
            }
        }

        return $files;
    }

    private function dockerAvailable(IssueCollection $issues, DockerOptions $options): bool
    {
        if (! $this->commands->available('docker')) {
            $issues->add(new Issue(
                code: 'DD_DOCKER_BINARY_MISSING',
                severity: Severity::WARNING,
                message: 'Docker binary was not found.',
                module: 'docker',
            ));

            return false;
        }

        if (! $options->daemon) {
            return true;
        }

        $info = $this->runner->run(['docker', 'info', '--format', 'json'], $options->path);

        if ($info->successful()) {
            return true;
        }

        $issues->add(new Issue(
            code: 'DD_DOCKER_DAEMON_UNAVAILABLE',
            severity: Severity::WARNING,
            message: 'Docker daemon is unavailable.',
            module: 'docker',
        ));

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseCompose(IssueCollection $issues, string $composeFile): ?array
    {
        if (! is_file($composeFile)) {
            $issues->add(new Issue(
                code: 'DD_DOCKER_COMPOSE_FILE_MISSING',
                severity: Severity::ERROR,
                message: 'Compose file does not exist.',
                module: 'docker',
                file: basename($composeFile),
            ));

            return null;
        }

        try {
            $data = Yaml::parseFile($composeFile) ?? [];
        } catch (ParseException $exception) {
            $issues->add(new Issue(
                code: 'DD_DOCKER_COMPOSE_INVALID',
                severity: Severity::ERROR,
                message: $exception->getMessage(),
                module: 'docker',
                file: basename($composeFile),
            ));

            return null;
        }

        if (! is_array($data)) {
            $issues->add(new Issue(
                code: 'DD_DOCKER_COMPOSE_INVALID',
                severity: Severity::ERROR,
                message: 'Compose file must contain a YAML mapping.',
                module: 'docker',
                file: basename($composeFile),
            ));

            return null;
        }

        return $data;
    }

    private function checkComposeConfig(IssueCollection $issues, string $path, string $composeFile): void
    {
        $result = $this->runner->run(['docker', 'compose', '-f', $composeFile, 'config'], $path);

        if ($result->successful()) {
            return;
        }

        $issues->add(new Issue(
            code: 'DD_DOCKER_COMPOSE_CONFIG_INVALID',
            severity: Severity::ERROR,
            message: trim($result->stderr) !== '' ? trim($result->stderr) : 'docker compose config failed.',
            module: 'docker',
            file: basename($composeFile),
        ));
    }

    private function checkMissingEnvReferences(IssueCollection $issues, PathResolver $paths, string $composeFile): void
    {
        $content = (string) file_get_contents($composeFile);

        foreach ($this->envReferences->requiredReferences($content) as $name) {
            if ($this->envValueExists($paths, $name)) {
                continue;
            }

            $issues->add(new Issue(
                code: 'DD_DOCKER_ENV_REFERENCE_MISSING',
                severity: Severity::WARNING,
                message: 'Compose references missing environment variable '.$name,
                module: 'docker',
                file: basename($composeFile),
                key: $name,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $compose
     */
    private function checkHostPortConflicts(IssueCollection $issues, array $compose): void
    {
        $ports = $this->hostPorts($compose);

        if ($ports === [] || ! $this->ports->available()) {
            return;
        }

        foreach ($ports as $port) {
            foreach ($this->ports->usages($port) as $usage) {
                $issues->add(new Issue(
                    code: 'DD_DOCKER_HOST_PORT_CONFLICT',
                    severity: Severity::WARNING,
                    message: 'Compose host port '.$port.' is already in use.',
                    module: 'docker',
                    context: [
                        'port' => $port,
                        'pid' => $usage->process->pid,
                        'command' => $usage->process->command,
                    ],
                ));
            }
        }
    }

    private function checkContainers(IssueCollection $issues, string $path, string $composeFile): void
    {
        $result = $this->runner->run(['docker', 'compose', '-f', $composeFile, 'ps', '--format', 'json'], $path);

        if (! $result->successful()) {
            return;
        }

        foreach ($this->decodeJsonLines($result->stdout) as $container) {
            $state = strtolower((string) ($container['State'] ?? $container['state'] ?? ''));
            $status = strtolower((string) ($container['Status'] ?? $container['status'] ?? ''));
            $health = strtolower((string) ($container['Health'] ?? $container['health'] ?? ''));

            if (! str_contains($state.$status.$health, 'unhealthy') && ! str_contains($state.$status, 'restarting')) {
                continue;
            }

            $issues->add(new Issue(
                code: 'DD_DOCKER_CONTAINER_UNHEALTHY',
                severity: Severity::WARNING,
                message: 'Compose container is unhealthy or restarting.',
                module: 'docker',
                key: (string) ($container['Service'] ?? $container['Name'] ?? 'container'),
                context: array_filter([
                    'state' => $state,
                    'status' => $status,
                    'health' => $health,
                ]),
            ));
        }
    }

    private function envValueExists(PathResolver $paths, string $name): bool
    {
        if (getenv($name) !== false) {
            return true;
        }

        $envFile = $paths->absolute('.env');

        if (! is_file($envFile)) {
            return false;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*(?:export\s+)?'.preg_quote($name, '/').'\s*=/', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $compose
     * @return list<int>
     */
    private function hostPorts(array $compose): array
    {
        $hostPorts = [];
        $services = $compose['services'] ?? [];

        if (! is_array($services)) {
            return [];
        }

        foreach ($services as $service) {
            if (! is_array($service) || ! is_array($service['ports'] ?? null)) {
                continue;
            }

            foreach ($service['ports'] as $port) {
                $hostPort = $this->extractHostPort($port);

                if ($hostPort !== null) {
                    $hostPorts[] = $hostPort;
                }
            }
        }

        return array_values(array_unique($hostPorts));
    }

    private function extractHostPort(mixed $port): ?int
    {
        if (is_array($port)) {
            $published = $port['published'] ?? null;

            return is_numeric($published) ? (int) $published : null;
        }

        if (! is_string($port) || preg_match('/(?:(?:\d+\.\d+\.\d+\.\d+|\[[^]]+]):)?(\d+):\d+(?:\/[a-z]+)?$/i', $port, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeJsonLines(string $stdout): array
    {
        $containers = [];

        foreach (preg_split('/\R/', trim($stdout)) ?: [] as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $containers[] = $decoded;
            }
        }

        return $containers;
    }
}
