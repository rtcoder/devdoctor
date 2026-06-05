<?php

use DevDoctor\Modules\Dotnet\DotnetAnalyzer;
use DevDoctor\Modules\Dotnet\DotnetOptions;

function dotnetFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-dotnet-'.bin2hex(random_bytes(4));
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

it('reports non dotnet projects as info', function () {
    $issues = (new DotnetAnalyzer)->analyze(new DotnetOptions(path: dotnetFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_DOTNET_NOT_PROJECT');
});

it('reports ready dotnet projects with pinned sdk', function () {
    $issues = (new DotnetAnalyzer)->analyze(new DotnetOptions(path: dotnetFixture([
        'global.json' => "{\"sdk\":{\"version\":\"9.0.100\"}}\n",
        'App.csproj' => "<Project><PropertyGroup><TargetFramework>net9.0</TargetFramework></PropertyGroup></Project>\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_DOTNET_READY');
});

it('reports missing sdk pinning', function () {
    $issues = (new DotnetAnalyzer)->analyze(new DotnetOptions(path: dotnetFixture([
        'App.csproj' => "<Project><PropertyGroup><TargetFramework>net9.0</TargetFramework></PropertyGroup></Project>\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_DOTNET_SDK_NOT_PINNED');
});

it('reports target framework mismatches', function () {
    $issues = (new DotnetAnalyzer)->analyze(new DotnetOptions(path: dotnetFixture([
        'global.json' => "{\"sdk\":{\"version\":\"9.0.100\"}}\n",
        'App.csproj' => "<Project><PropertyGroup><TargetFramework>net8.0</TargetFramework></PropertyGroup></Project>\n",
        'Worker.csproj' => "<Project><PropertyGroup><TargetFramework>net9.0</TargetFramework></PropertyGroup></Project>\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_DOTNET_TARGET_FRAMEWORK_MISMATCH');
});

it('reports missing lockfiles when restore lock mode is enabled', function () {
    $issues = (new DotnetAnalyzer)->analyze(new DotnetOptions(path: dotnetFixture([
        'global.json' => "{\"sdk\":{\"version\":\"9.0.100\"}}\n",
        'App.csproj' => "<Project><PropertyGroup><TargetFramework>net9.0</TargetFramework><RestorePackagesWithLockFile>true</RestorePackagesWithLockFile></PropertyGroup></Project>\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_DOTNET_LOCK_MISSING');
});

it('reports multiple solutions and insecure nuget sources', function () {
    $issues = (new DotnetAnalyzer)->analyze(new DotnetOptions(path: dotnetFixture([
        'global.json' => "{\"sdk\":{\"version\":\"9.0.100\"}}\n",
        'App.sln' => "\n",
        'Tools.sln' => "\n",
        'NuGet.config' => "<configuration><packageSources><add key=\"internal\" value=\"http://nuget.local\" /></packageSources></configuration>\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_DOTNET_MIXED_SOLUTION_STATE')
        ->and($codes)->toContain('DD_DOTNET_RISKY_NUGET_SOURCE');
});
