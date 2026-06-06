<?php

use DevDoctor\Modules\Flutter\FlutterAnalyzer;
use DevDoctor\Modules\Flutter\FlutterOptions;

function flutterFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-flutter-'.bin2hex(random_bytes(4));
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

it('reports non flutter projects as info', function () {
    $issues = (new FlutterAnalyzer)->analyze(new FlutterOptions(path: flutterFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_FLUTTER_NOT_PROJECT');
});

it('reports ready flutter projects', function () {
    $issues = (new FlutterAnalyzer)->analyze(new FlutterOptions(path: flutterFixture([
        'pubspec.yaml' => "name: demo\nenvironment:\n  sdk: ^3.8.0\ndependencies:\n  flutter:\n    sdk: flutter\n",
        'pubspec.lock' => "packages: {}\n",
        '.metadata' => "version:\n",
        'android/app/build.gradle' => "plugins {}\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_FLUTTER_READY');
});

it('reports missing lockfile and sdk constraints', function () {
    $issues = (new FlutterAnalyzer)->analyze(new FlutterOptions(path: flutterFixture([
        'pubspec.yaml' => "name: demo\ndependencies:\n  http: ^1.0.0\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_FLUTTER_LOCK_MISSING')
        ->and($codes)->toContain('DD_FLUTTER_SDK_CONSTRAINT_MISSING');
});

it('reports git and path dependency sources', function () {
    $issues = (new FlutterAnalyzer)->analyze(new FlutterOptions(path: flutterFixture([
        'pubspec.yaml' => "name: demo\nenvironment:\n  sdk: ^3.8.0\ndependencies:\n  local_pkg:\n    path: ../local_pkg\n  remote_pkg:\n    git: https://example.test/pkg.git\n",
        'pubspec.lock' => "packages: {}\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_FLUTTER_DEPENDENCY_SOURCE');
});

it('reports missing platform markers for flutter apps', function () {
    $issues = (new FlutterAnalyzer)->analyze(new FlutterOptions(path: flutterFixture([
        'pubspec.yaml' => "name: demo\nenvironment:\n  sdk: ^3.8.0\ndependencies:\n  flutter:\n    sdk: flutter\n",
        'pubspec.lock' => "packages: {}\n",
        '.metadata' => "version:\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_FLUTTER_PLATFORM_MARKERS_MISSING');
});
