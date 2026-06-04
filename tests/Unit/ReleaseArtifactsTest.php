<?php

use Symfony\Component\Yaml\Yaml;

it('ships valid JSON schema and Box configuration', function () {
    $root = dirname(__DIR__, 2);
    $schema = json_decode((string) file_get_contents($root.'/schemas/devdoctor-output.schema.json'), true, flags: JSON_THROW_ON_ERROR);
    $versionedSchema = json_decode((string) file_get_contents($root.'/schemas/v1/devdoctor-output.schema.json'), true, flags: JSON_THROW_ON_ERROR);
    $box = json_decode((string) file_get_contents($root.'/box.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['title'])->toBe('DevDoctor JSON Output')
        ->and($schema)->toBe($versionedSchema)
        ->and($schema['properties']['schema_version']['const'])->toBe('1.0')
        ->and($box['directories'])->toContain('app');
});

it('ships release workflow and composite action metadata', function () {
    $root = dirname(__DIR__, 2);
    $action = Yaml::parseFile($root.'/action.yml');
    $release = Yaml::parseFile($root.'/.github/workflows/release.yml');
    $pages = Yaml::parseFile($root.'/.github/workflows/pages.yml');

    expect($action['runs']['using'])->toBe('composite')
        ->and($action['inputs'])->toHaveKey('version')
        ->and($release['permissions']['contents'])->toBe('write')
        ->and($release['permissions']['id-token'])->toBe('write')
        ->and($pages['permissions']['pages'])->toBe('write')
        ->and(file_get_contents($root.'/.github/scripts/update-homebrew-tap.sh'))->toContain('rtcoder/homebrew-tap');
});

it('ships static documentation and pinned CI examples', function () {
    $root = dirname(__DIR__, 2);

    foreach ([
        'docs/index.html',
        'docs/installation.html',
        'docs/commands.html',
        'docs/config.html',
        'docs/output-formats.html',
        'docs/baseline.html',
        'docs/safety.html',
        'docs/contracts.html',
        'docs/release-verification.html',
        'docs/ci.html',
        'docs/examples/github-actions.yml',
        'docs/examples/gitlab-ci.yml',
        'docs/examples/bitbucket-pipelines.yml',
    ] as $path) {
        expect(is_file($root.'/'.$path))->toBeTrue($path);
    }

    expect(file_get_contents($root.'/docs/examples/github-actions.yml'))->toContain('v0.14.0')
        ->and(file_get_contents($root.'/docs/examples/gitlab-ci.yml'))->toContain('v0.14.0')
        ->and(file_get_contents($root.'/docs/examples/bitbucket-pipelines.yml'))->toContain('v0.14.0');
});

it('catalogs every issue code used by the application', function () {
    $root = dirname(__DIR__, 2);
    $documented = (string) file_get_contents($root.'/docs/issue-codes.md');
    $catalog = json_decode((string) file_get_contents($root.'/schemas/v1/issue-codes.json'), true, flags: JSON_THROW_ON_ERROR);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
    $codes = [];

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all('/DD_[A-Z0-9_]+/', (string) file_get_contents($file->getPathname()), $matches);
        $codes = array_merge($codes, $matches[0]);
    }

    $applicationCodes = array_values(array_unique($codes));
    sort($applicationCodes);
    $catalogCodes = array_column($catalog['codes'], 'code');
    $uniqueCatalogCodes = array_values(array_unique($catalogCodes));
    sort($catalogCodes);
    sort($uniqueCatalogCodes);

    expect($catalog['schema_version'])->toBe('1.0')
        ->and($catalogCodes)->toBe($uniqueCatalogCodes)
        ->and($catalogCodes)->toBe($applicationCodes);

    foreach ($catalog['codes'] as $entry) {
        expect($entry)->toHaveKeys(['code', 'module', 'description', 'introduced', 'status'])
            ->and($entry['status'])->toBeIn(['active', 'deprecated']);
    }

    foreach ($applicationCodes as $code) {
        expect($documented)->toContain($code);
    }
});
