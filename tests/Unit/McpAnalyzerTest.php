<?php

use DevDoctor\Modules\Mcp\McpAnalyzer;
use DevDoctor\Modules\Mcp\McpOptions;

function mcpFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-mcp-'.bin2hex(random_bytes(4));
    mkdir($path);

    foreach ($files as $file => $contents) {
        $target = $path.'/'.$file;
        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($target, $contents);
    }

    return $path;
}

it('reports projects without mcp config as info', function () {
    $issues = (new McpAnalyzer)->analyze(new McpOptions(path: mcpFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_MCP_NOT_CONFIGURED');
});

it('reports ready stdio and remote mcp servers', function () {
    $issues = (new McpAnalyzer)->analyze(new McpOptions(path: mcpFixture([
        '.mcp.json' => json_encode([
            'mcpServers' => [
                'filesystem' => [
                    'command' => 'node',
                    'args' => ['server.js'],
                    'env' => ['ROOT' => '.'],
                ],
                'docs' => [
                    'transport' => 'http',
                    'url' => 'https://mcp.example.test',
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MCP_READY');
});

it('reports invalid json configs', function () {
    $issues = (new McpAnalyzer)->analyze(new McpOptions(path: mcpFixture([
        '.mcp.json' => '{"mcpServers":',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MCP_CONFIG_INVALID');
});

it('reports missing server sections', function () {
    $issues = (new McpAnalyzer)->analyze(new McpOptions(path: mcpFixture([
        '.cursor/mcp.json' => '{"tools":{}}',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MCP_SERVERS_MISSING');
});

it('reports invalid server shapes and unsupported transports', function () {
    $issues = (new McpAnalyzer)->analyze(new McpOptions(path: mcpFixture([
        'mcp.json' => json_encode([
            'servers' => [
                'bad-shape' => 'node server.js',
                'bad-transport' => ['transport' => 'websocket', 'url' => 'ws://example.test'],
            ],
        ], JSON_THROW_ON_ERROR),
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_MCP_SERVER_INVALID')
        ->and($codes)->toContain('DD_MCP_TRANSPORT_UNKNOWN');
});

it('reports missing stdio command and remote url', function () {
    $issues = (new McpAnalyzer)->analyze(new McpOptions(path: mcpFixture([
        '.vscode/mcp.json' => json_encode([
            'mcpServers' => [
                'stdio' => ['transport' => 'stdio'],
                'remote' => ['transport' => 'sse'],
            ],
        ], JSON_THROW_ON_ERROR),
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_MCP_STDIO_COMMAND_MISSING')
        ->and($codes)->toContain('DD_MCP_REMOTE_URL_MISSING');
});

it('supports a specific config path', function () {
    $issues = (new McpAnalyzer)->analyze(new McpOptions(
        path: mcpFixture([
            '.mcp.json' => '{"mcpServers":{}}',
            'custom/mcp.json' => json_encode([
                'mcpServers' => ['demo' => ['command' => 'php']],
            ], JSON_THROW_ON_ERROR),
        ]),
        config: 'custom/mcp.json',
    ));

    expect($issues->all()[0]->code->value)->toBe('DD_MCP_READY');
});
