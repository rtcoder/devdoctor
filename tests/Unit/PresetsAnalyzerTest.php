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

    expect(array_map(static fn ($issue): ?string => $issue->key, $issues->all()))
        ->toBe(['nextjs', 'node', 'vite']);
});

it('detects file based framework and tooling presets', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([
        'artisan' => '',
        'bin/console' => '',
        'vite.config.ts' => '',
        'compose.yaml' => 'services: {}',
    ]));

    expect(array_map(static fn ($issue): ?string => $issue->key, $issues->all()))
        ->toBe(['laravel', 'symfony', 'docker-compose', 'vite']);
});

it('ignores invalid json while retaining file based node detection', function () {
    $issues = (new PresetsAnalyzer)->analyze(presetsFixture([
        'package.json' => '{',
    ]));

    expect(array_map(static fn ($issue): ?string => $issue->key, $issues->all()))
        ->toBe(['node']);
});
