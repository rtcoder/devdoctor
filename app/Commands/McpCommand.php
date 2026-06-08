<?php

declare(strict_types=1);

namespace DevDoctor\Commands;

use DevDoctor\Commands\Concerns\RendersDiagnostics;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Modules\Mcp\McpAnalyzer;
use DevDoctor\Modules\Mcp\McpOptions;
use LaravelZero\Framework\Commands\Command;

final class McpCommand extends Command
{
    use RendersDiagnostics;

    protected $signature = 'mcp
        {--path=. : Project path to inspect}
        {--format=table : Output format: table, json, or sarif}
        {--ci : Use CI-safe behavior}
        {--strict : Treat warnings as errors where supported}
        {--config= : Specific MCP config file to inspect}
        {--allow-command= : Comma-separated stdio command names allowed by project policy}
        {--deny-command= : Comma-separated stdio command names denied by project policy}
        {--disallow-remote : Report remote MCP servers as policy violations}
        {--only= : Comma-separated severities to render: error, warning, info}
        {--summary-only : Render module summaries without issue details}
        {--no-hints : Hide hints and suggested fixes from output}';

    protected $description = 'Check MCP server configuration files for agent tooling without starting servers.';

    public function handle(): int
    {
        return $this->renderDiagnostics([
            new ModuleResult(ModuleName::MCP, app(McpAnalyzer::class)->analyze(new McpOptions(
                path: (string) $this->option('path'),
                config: (string) ($this->option('config') ?: ''),
                strict: (bool) $this->option('strict'),
                allowedCommands: $this->stringListOption('allow-command'),
                deniedCommands: $this->stringListOption('deny-command'),
                disallowRemote: (bool) $this->option('disallow-remote'),
            ))),
        ]);
    }

    /**
     * @return list<string>
     */
    private function stringListOption(string $name): array
    {
        return array_values(array_filter(
            array_map(
                static fn (string $value): string => strtolower(trim($value)),
                explode(',', (string) ($this->option($name) ?: '')),
            ),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
