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
use DevDoctor\Modules\Env\EnvEntry;
use DevDoctor\Modules\Env\EnvParser;
use DevDoctor\Modules\Env\SecretScanner;
use JsonException;

final readonly class McpAnalyzer
{
    private const array CONFIG_FILES = [
        '.mcp.json',
        'mcp.json',
        '.agents/mcp.json',
        '.claude/mcp.json',
        '.cline/mcp.json',
        '.codex/mcp.json',
        '.continue/mcp.json',
        '.continue/config.json',
        '.cursor/mcp.json',
        '.roo/mcp.json',
        '.vscode/mcp.json',
        '.windsurf/mcp.json',
    ];

    private const array ENV_FILES = [
        '.env',
        '.env.example',
        '.env.local',
    ];

    public function __construct(
        private EnvParser $envParser = new EnvParser,
        private SecretScanner $secretScanner = new SecretScanner,
    ) {}

    public function analyze(McpOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $configFiles = $this->configFiles($files, $options);
        $knownEnvKeys = $this->knownEnvKeys($paths);

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

            $this->analyzeConfig($issues, $config, $options, $knownEnvKeys);
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

    /**
     * @param  list<string>  $knownEnvKeys
     */
    private function analyzeConfig(IssueCollection $issues, McpConfigFile $config, McpOptions $options, array $knownEnvKeys): void
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
            $this->analyzeServer($issues, $server, $options, $knownEnvKeys);
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

        if (is_array($data['mcp'] ?? null)) {
            foreach (['mcpServers', 'servers'] as $section) {
                if (is_array($data['mcp'][$section] ?? null)) {
                    return $data['mcp'][$section];
                }
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
            headers: $this->stringMap($data['headers'] ?? []),
            rawTransport: $rawTransport,
        );
    }

    /**
     * @param  list<string>  $knownEnvKeys
     */
    private function analyzeServer(IssueCollection $issues, McpServer $server, McpOptions $options, array $knownEnvKeys): void
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

        $this->checkPolicy($issues, $server, $options);
        $this->checkRemoteUrl($issues, $server, $options);
        $this->checkRiskyCommand($issues, $server, $options);
        $this->checkSupplyChainPins($issues, $server, $options);
        $this->checkInlineSecrets($issues, $server, $options);
        $this->checkEnvReferences($issues, $server, $options, $knownEnvKeys);
    }

    private function checkPolicy(IssueCollection $issues, McpServer $server, McpOptions $options): void
    {
        if ($options->disallowRemote && $server->transport?->isRemote()) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_REMOTE_DISALLOWED,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP remote server is disallowed by project policy',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
                context: ['transport' => $server->transport->value],
            ));
        }

        if ($server->command === null) {
            return;
        }

        $command = $this->normalizedExecutable($server->command->command);

        if (in_array($command, $options->deniedCommands, true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_COMMAND_DENIED,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP stdio command is denied by project policy',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
                context: ['command' => $command],
            ));
        }

        if ($options->allowedCommands !== [] && ! in_array($command, $options->allowedCommands, true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_COMMAND_NOT_ALLOWED,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP stdio command is not in the project allow-list',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
                context: ['command' => $command, 'allowed' => $options->allowedCommands],
            ));
        }
    }

    private function checkRemoteUrl(IssueCollection $issues, McpServer $server, McpOptions $options): void
    {
        if (! $server->transport?->isRemote() || $server->url === null || ! str_starts_with(strtolower($server->url), 'http://')) {
            return;
        }

        $host = strtolower((string) parse_url($server->url, PHP_URL_HOST));

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_MCP_REMOTE_URL_INSECURE,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'MCP remote server uses an insecure HTTP URL',
            module: ModuleName::MCP,
            file: $server->file,
            key: $server->name,
            context: ['url' => $server->url],
        ));
    }

    private function checkRiskyCommand(IssueCollection $issues, McpServer $server, McpOptions $options): void
    {
        if ($server->command === null || ! $this->isRiskyCommand($server->command)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_MCP_COMMAND_RISKY,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'MCP stdio server command should be reviewed before use',
            module: ModuleName::MCP,
            file: $server->file,
            key: $server->name,
            context: [
                'command' => $server->command->command,
                'args' => $server->command->args,
            ],
        ));
    }

    private function checkSupplyChainPins(IssueCollection $issues, McpServer $server, McpOptions $options): void
    {
        if ($server->command === null) {
            return;
        }

        $package = $this->packageRunnerTarget($server->command);

        if ($package !== null && ! $this->isPackagePinned($package)) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_PACKAGE_UNPINNED,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP stdio server uses a package runner without an explicit package version',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
                context: [
                    'command' => $server->command->command,
                    'package' => $package,
                ],
            ));
        }

        $image = $this->dockerRunImage($server->command);

        if ($image !== null && $this->isMutableDockerImage($image)) {
            $issues->add(new Issue(
                code: IssueCode::DD_MCP_DOCKER_IMAGE_MUTABLE,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP stdio server uses a Docker image without an immutable or stable tag',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name,
                context: [
                    'command' => $server->command->command,
                    'image' => $image,
                ],
            ));
        }
    }

    private function checkInlineSecrets(IssueCollection $issues, McpServer $server, McpOptions $options): void
    {
        foreach (['env' => $server->command?->env ?? [], 'headers' => $server->headers] as $section => $values) {
            foreach ($values as $key => $value) {
                if (! $this->secretScanner->isSuspicious(new EnvEntry($key, $value, $key.'='.$value, 1, $server->file))) {
                    continue;
                }

                $issues->add(new Issue(
                    code: IssueCode::DD_MCP_ENV_SECRET_INLINE,
                    severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                    message: 'MCP server configuration appears to contain an inline secret',
                    module: ModuleName::MCP,
                    file: $server->file,
                    key: $server->name.'.'.$section.'.'.$key,
                    context: ['section' => $section, 'key' => $key],
                ));
            }
        }
    }

    /**
     * @param  list<string>  $knownEnvKeys
     */
    private function checkEnvReferences(IssueCollection $issues, McpServer $server, McpOptions $options, array $knownEnvKeys): void
    {
        foreach ($this->envReferences($server) as $reference) {
            if (in_array($reference, $knownEnvKeys, true)) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_MCP_ENV_REFERENCE_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'MCP server references an environment key not declared in local env files',
                module: ModuleName::MCP,
                file: $server->file,
                key: $server->name.'.'.$reference,
                context: ['env' => $reference],
            ));
        }
    }

    private function isRiskyCommand(McpServerCommand $command): bool
    {
        $executable = $this->normalizedExecutable($command->command);
        $joined = strtolower(implode(' ', $command->args));

        if (in_array($executable, ['bash', 'sh', 'zsh', 'cmd', 'powershell', 'pwsh'], true) && preg_match('/(^|\s)(-c|\/c|invoke-expression|iex)(\s|$)/', $joined) === 1) {
            return true;
        }

        return preg_match('/(curl|wget)\s+.+\|\s*(bash|sh|zsh|powershell|pwsh)/', $joined) === 1
            || str_contains($joined, 'invoke-expression')
            || preg_match('/(^|\s)iex(\s|$)/', $joined) === 1;
    }

    private function packageRunnerTarget(McpServerCommand $command): ?string
    {
        $executable = $this->normalizedExecutable($command->command);

        if (! in_array($executable, ['npx', 'pnpm', 'yarn', 'bunx', 'uvx'], true)) {
            return null;
        }

        foreach ($command->args as $index => $argument) {
            if ($argument === '' || str_starts_with($argument, '-')) {
                if (in_array($argument, ['--package', '-p'], true)) {
                    return $command->args[$index + 1] ?? null;
                }

                continue;
            }

            if (in_array($argument, ['dlx', 'exec'], true)) {
                continue;
            }

            return $argument;
        }

        return null;
    }

    private function isPackagePinned(string $package): bool
    {
        if (str_contains($package, '==')) {
            return ! str_ends_with($package, '==');
        }

        $version = null;

        if (str_starts_with($package, '@')) {
            $slash = strpos($package, '/');
            $versionAt = $slash === false ? false : strpos($package, '@', $slash);
            $version = $versionAt === false ? null : substr($package, $versionAt + 1);
        } elseif (str_contains($package, '@')) {
            $version = substr($package, strrpos($package, '@') + 1);
        }

        return is_string($version) && $version !== '' && strtolower($version) !== 'latest';
    }

    private function dockerRunImage(McpServerCommand $command): ?string
    {
        $executable = $this->normalizedExecutable($command->command);

        if ($executable !== 'docker') {
            return null;
        }

        $runIndex = array_search('run', $command->args, true);

        if (! is_int($runIndex)) {
            return null;
        }

        $optionsWithValues = ['--env', '-e', '--name', '--network', '--publish', '-p', '--volume', '-v', '--workdir', '-w', '--user', '-u'];

        for ($index = $runIndex + 1; $index < count($command->args); $index++) {
            $argument = $command->args[$index];

            if ($argument === '' || $argument === '--rm' || $argument === '-i' || $argument === '-t' || $argument === '-it' || $argument === '--pull=always') {
                continue;
            }

            if (str_starts_with($argument, '--') && str_contains($argument, '=')) {
                continue;
            }

            if (in_array($argument, $optionsWithValues, true)) {
                $index++;

                continue;
            }

            if (str_starts_with($argument, '-')) {
                continue;
            }

            return $argument;
        }

        return null;
    }

    private function isMutableDockerImage(string $image): bool
    {
        if (str_contains($image, '@sha256:')) {
            return false;
        }

        $lastSlash = strrpos($image, '/');
        $lastColon = strrpos($image, ':');

        if ($lastColon === false || ($lastSlash !== false && $lastColon < $lastSlash)) {
            return true;
        }

        return strtolower(substr($image, $lastColon + 1)) === 'latest';
    }

    private function normalizedExecutable(string $command): string
    {
        return preg_replace('/\.(cmd|exe|bat)$/', '', strtolower(basename($command))) ?? strtolower(basename($command));
    }

    /**
     * @return list<string>
     */
    private function envReferences(McpServer $server): array
    {
        $values = array_filter([
            $server->url,
            $server->command?->command,
            ...($server->command?->args ?? []),
            ...array_values($server->command?->env ?? []),
            ...array_values($server->headers),
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        $references = [];

        foreach ($values as $value) {
            if (preg_match_all('/\$\{([A-Z_][A-Z0-9_]*)(?:[^}]*)\}/i', $value, $matches) !== false) {
                array_push($references, ...$matches[1]);
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * @return list<string>
     */
    private function knownEnvKeys(PathResolver $paths): array
    {
        $keys = [];

        foreach (self::ENV_FILES as $file) {
            $envFile = $this->envParser->parseFile($paths->absolute($file), $paths->display($file));

            if ($envFile->exists) {
                array_push($keys, ...$envFile->keys());
            }
        }

        return array_values(array_unique($keys));
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
