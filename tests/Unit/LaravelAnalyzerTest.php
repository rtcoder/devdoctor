<?php

use DevDoctor\Modules\Laravel\LaravelAnalyzer;
use DevDoctor\Modules\Laravel\LaravelOptions;

function laravelFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-laravel-'.bin2hex(random_bytes(4));
    mkdir($path);

    foreach ($files as $file => $contents) {
        $target = $path.'/'.$file;
        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        if ($contents === null) {
            mkdir($target, recursive: true);

            continue;
        }

        file_put_contents($target, $contents);
    }

    return $path;
}

it('reports non laravel projects as info', function () {
    $issues = (new LaravelAnalyzer)->analyze(new LaravelOptions(path: laravelFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_LARAVEL_NOT_PROJECT');
});

it('detects missing laravel env files and directories', function () {
    $issues = (new LaravelAnalyzer)->analyze(new LaravelOptions(path: laravelFixture([
        'artisan' => '',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_LARAVEL_ENV_MISSING')
        ->and($codes)->toContain('DD_LARAVEL_DIRECTORY_MISSING');
});

it('reports risky laravel env values', function () {
    $issues = (new LaravelAnalyzer)->analyze(new LaravelOptions(path: laravelFixture([
        'composer.json' => '{"require":{"laravel/framework":"^12.0"}}',
        '.env' => "APP_ENV=production\nAPP_DEBUG=true\nAPP_URL=http://localhost\n",
        'storage' => null,
        'bootstrap/cache' => null,
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_LARAVEL_APP_KEY_MISSING')
        ->and($codes)->toContain('DD_LARAVEL_PROD_DEBUG')
        ->and($codes)->toContain('DD_LARAVEL_APP_URL_DEFAULT');
});

it('reports cached config as informational', function () {
    $issues = (new LaravelAnalyzer)->analyze(new LaravelOptions(path: laravelFixture([
        'artisan' => '',
        '.env' => "APP_KEY=base64:abc\nAPP_ENV=local\nAPP_DEBUG=false\nAPP_URL=https://example.test\n",
        'storage' => null,
        'bootstrap/cache' => null,
        'bootstrap/cache/config.php' => '<?php return [];',
    ])));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_LARAVEL_CONFIG_CACHED');
});

it('reports ready laravel projects', function () {
    $issues = (new LaravelAnalyzer)->analyze(new LaravelOptions(path: laravelFixture([
        'artisan' => '',
        '.env' => "APP_KEY=base64:abc\nAPP_ENV=local\nAPP_DEBUG=false\nAPP_URL=https://example.test\n",
        'storage' => null,
        'bootstrap/cache' => null,
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_LARAVEL_READY');
});
