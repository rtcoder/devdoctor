<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mcp;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;
use JsonException;

final readonly class McpAnalyzer
{
    private const array CONFIG_FILES = [
        '.mcp.json',
        'mcp.json',
        '.cursor/mcp.json',
        '.vscode/mcp.json',
    ];

    public function analyze(McpOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $configFiles = $this->configFiles($files, $options);

        if ($configFiles === []) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_NOT_CONFIGURED,
                severity: Severity::INFO,
                message: 'No MCP configuration file detected',
                module: ModuleName::MCP,
                context: ['searched' => self::CONFIG_FILES],
            ));

            return $issues;
        }

        foreach ($configFiles as $configPath) {
            $config = $this->readConfig($paths, $configPath);

            if (! $config->isValid()) {
                $issues->add(new Issue(
                    code: IssueCode::DD_MCP_CONFIG_INVALID,
                    severity: Severity::ERROR,
                    message: 'MCP configuration JSON is invalid: '.$config->error,
                    module: ModuleName::MCP,
                    file: $config->path,
                ));

                continue;
            }

            $this->analyzeConfig($issues, $config, $options);
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_READY,
                severity: Severity::INFO,
                message: 'MCP diagnostics found no actionable issues.',
                module: ModuleName::MCP,
                context: ['configs' => $configFiles],
            ));
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function configFiles(ProjectFiles $files, McpOptions $options): array
    {
        if ($options->config !== null && $options->config !== '') {
            return $files->exists($options->config) ? [$options->config] : [];
        }

        return $files->existing(self::CONFIG_FILES);
    }

    private function readConfig(PathResolver $paths, string $configPath): McpConfigFile
    {
        try {
            $data = json_decode((string) file_get_contents($paths->absolute($configPath)), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return new McpConfigFile($paths->display($configPath), error: $exception->getMessage());
        }

        if (! is_array($data)) {
            return new McpConfigFile($paths->display($configPath), error: 'root value must be a JSON object');
        }

        return new McpConfigFile($paths->display($configPath), $data);
    }

    private function analyzeConfig(IssueCollection $issues, McpConfigFile $config, McpOptions $options): void
    {
        $servers = $this->serversSection($config->data ?? []);

        if ($servers === null) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_SERVERS_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP configuration does not define mcpServers or servers',
                module: ModuleName::MCP,
                file: $config->path,
            ));

            return;
        }

        foreach ($servers as $name => $serverData) {
            if (! is_string($name) || ! is_array($serverData)) {
                $issues->add(new Issue(
                    code: IssueCode::DD_MCP_SERVER_INVALID,
                    severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                    message: 'MCP server entry must be a named JSON object',
                    module: ModuleName::MCP,
                    file: $config->path,
                    key: is_string($name) ? $name : null,
                ));

                continue;
            }

            $server = $this->server($name, $config->path, $serverData);
            $this->analyzeServer($issues, $server, $options);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function serversSection(array $data): ?array
    {
        foreach (['mcpServers', 'servers'] as $section) {
            if (is_array($data[$section] ?? null)) {
                return $data[$section];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function server(string $name, string $file, array $data): McpServer
    {
        $command = $this->stringValue($data['command'] ?? null);
        $url = $this->stringValue($data['url'] ?? null);
        $rawTransport = $this->stringValue($data['transport'] ?? null);

        return new McpServer(
            name: $name,
            file: $file,
            transport: McpServerTransport::fromConfig($rawTransport, $command, $url),
            command: $command === null ? null : new McpServerCommand(
                command: $command,
                args: $this->stringList($data['args'] ?? []),
                env: $this->stringMap($data['env'] ?? []),
            ),
            url: $url,
            rawTransport: $rawTransport,
        );
    }

    private function analyzeServer(IssueCollection $issues, McpServer $server, McpOptions $options): void
    {
        if ($server->rawTransport !== null && $server->transport === null) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_TRANSPORT_UNKNOWN,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP server uses an unsupported transport: '.$server->rawTransport,
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
                context: ['transport' => $server->rawTransport],
            ));

            return;
        }

        if ($server->transport === null) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_SERVER_INVALID,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP server must define a command, url, or supported transport',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
            ));

            return;
        }

        if ($server->transport === McpServerTransport::STDIO && $server->command === null) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_STDIO_COMMAND_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP stdio server is missing a command',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
            ));
        }

        if ($server->transport->isRemote() && ($server->url === null || $server->url === '')) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_REMOTE_URL_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP remote server is missing a url',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
            ));
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): ?string => is_scalar($item) ? (string) $item : null, $value),
            static fn (?string $item): bool => $item !== null,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_scalar($item)) {
                $map[$key] = (string) $item;
            }
        }

        return $map;
    }
}
