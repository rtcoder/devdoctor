<?php

use DevDoctor\Modules\Presets\PresetsAnalyzer;

function presetsFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-presets-'.bin2hex(random_bytes(4));
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

it('reports when no supported presets are detected', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([]));

    expect($issues->all()[0]->code->value)->toBe('DD_PRESET_NONE_DETECTED');
});

it('detects composer framework presets', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([
        'composer.json' => '{"require":{"laravel/framework":"^12.0","symfony/framework-bundle":"^7.0"}}',
    ]));

    expect(array_map(static fn ($issue): ?string => $issue->key, $issues->all()))
        ->toBe(['laravel', 'symfony']);
});

it('detects node vite and nextjs presets from package json', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([
        'package.json' => '{"dependencies":{"next":"^15.0"},"devDependencies":{"vite":"^7.0"}}',
    ]));

    $keys = array_map(static fn ($issue): ?string => $issue->key, $issues->all());
    sort($keys);

    expect($keys)->toBe(['frontend', 'nextjs', 'node', 'vite', 'web']);
});

it('detects file based framework and tooling presets', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([
        'artisan' => '',
        'bin/console' => '',
        'vite.config.ts' => '',
        'compose.yaml' => 'services: {}',
    ]));

    $keys = array_map(static fn ($issue): ?string => $issue->key, $issues->all());
    sort($keys);

    expect($keys)->toBe(['docker-compose', 'frontend', 'laravel', 'symfony', 'vite', 'web']);
});

it('ignores invalid json while retaining file based node detection', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([
        'package.json' => '{',
    ]));

    expect(array_map(static fn ($issue): ?string => $issue->key, $issues->all()))
        ->toBe(['node']);
});

it('detects multi stack ecosystem presets without running tools', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([
        'pyproject.toml' => '[project]',
        'requirements-dev.txt' => 'pytest',
        'poetry.lock' => '',
        'go.mod' => 'module example.test/app',
        'Cargo.toml' => '[package]',
        'pom.xml' => '<project><artifactId>spring-boot-starter</artifactId></project>',
        'build.xml' => '<project />',
        '.mcp.json' => '{"mcpServers":{"demo":{"command":"php"}}}',
        'CMakeLists.txt' => 'cmake_minimum_required(VERSION 3.25)',
        'App.csproj' => '<Project Sdk="Microsoft.NET.Sdk" />',
        'public/index.html' => '<!doctype html>',
    ]));

    $keys = array_map(static fn ($issue): ?string => $issue->key, $issues->all());
    sort($keys);

    expect($keys)->toBe([
        'ant',
        'cmake',
        'cpp',
        'dotnet',
        'go',
        'java',
        'maven',
        'mcp',
        'pip',
        'poetry',
        'python',
        'rust',
        'spring',
        'web',
    ]);
});
