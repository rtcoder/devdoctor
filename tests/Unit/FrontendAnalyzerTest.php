<?php

use DevDoctor\Modules\Frontend\FrontendAnalyzer;
use DevDoctor\Modules\Frontend\FrontendOptions;

function frontendFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-frontend-'.bin2hex(random_bytes(4));
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

it('reports non frontend projects as info', function () {
    $issues = (new FrontendAnalyzer)->analyze(new FrontendOptions(path: frontendFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_FRONTEND_NOT_PROJECT');
});

it('detects frontend presets and ready state', function () {
    $issues = (new FrontendAnalyzer)->analyze(new FrontendOptions(path: frontendFixture([
        'package.json' => '{"dependencies":{"next":"^15.0.0"},"scripts":{"build":"next build"}}',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());
    $keys = array_filter(array_map(static fn ($issue): ?string => $issue->key, $issues->all()));

    expect($codes)->toContain('DD_FRONTEND_PRESET_DETECTED')
        ->and($codes)->toContain('DD_FRONTEND_READY')
        ->and($keys)->toContain('frontend')
        ->and($keys)->toContain('nextjs');
});

it('reports missing frontend build scripts', function () {
    $issues = (new FrontendAnalyzer)->analyze(new FrontendOptions(path: frontendFixture([
        'package.json' => '{"dependencies":{"vite":"^7.0.0"}}',
    ])));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_FRONTEND_BUILD_SCRIPT_MISSING');
});
