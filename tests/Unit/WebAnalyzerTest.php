<?php

use DevDoctor\Modules\Web\WebAnalyzer;
use DevDoctor\Modules\Web\WebOptions;

function webFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-web-'.bin2hex(random_bytes(4));
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

it('reports non web projects as info', function () {
    $issues = (new WebAnalyzer)->analyze(new WebOptions(path: webFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_WEB_NOT_PROJECT');
});

it('reports ready static web projects', function () {
    $issues = (new WebAnalyzer)->analyze(new WebOptions(path: webFixture([
        'index.html' => '<link rel="stylesheet" href="assets/app.css">',
        'assets/app.css' => 'body { color: black; }',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_WEB_READY');
});

it('reports missing build output when a build script exists', function () {
    $issues = (new WebAnalyzer)->analyze(new WebOptions(path: webFixture([
        'package.json' => '{"scripts":{"build":"vite build"}}',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_WEB_BUILD_OUTPUT_MISSING');
});

it('reports public secret-like config', function () {
    $issues = (new WebAnalyzer)->analyze(new WebOptions(path: webFixture([
        'index.html' => '<script src="config.js"></script>',
        'config.js' => 'window.API_TOKEN = "secret";',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_WEB_PUBLIC_SECRET');
});

it('reports missing local asset references', function () {
    $issues = (new WebAnalyzer)->analyze(new WebOptions(path: webFixture([
        'index.html' => '<script src="/assets/app.js?v=1"></script>',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_WEB_ASSET_REFERENCE_MISSING');
});

it('reports insecure server config and port conflicts', function () {
    $issues = (new WebAnalyzer)->analyze(new WebOptions(path: webFixture([
        'index.html' => '<h1>Demo</h1>',
        '.env' => "PORT=5173\n",
        'package.json' => '{"scripts":{"dev":"vite --port 3000"}}',
        'nginx.conf' => "server {\n    listen 80;\n}\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_WEB_INSECURE_DEFAULT_CONFIG')
        ->and($codes)->toContain('DD_WEB_PORT_CONFIG_CONFLICT');
});
