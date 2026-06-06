<?php

use DevDoctor\Core\IssueCode;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProcessRunner;
use Symfony\Component\Yaml\Yaml;

it('uses the public Composer package identity and namespace', function () {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('rtcoder/devdoctor')
        ->and($composer['homepage'])->toBe('https://github.com/rtcoder/devdoctor')
        ->and($composer['autoload']['psr-4'])->toHaveKey('DevDoctor\\')
        ->and($composer['autoload']['psr-4']['DevDoctor\\'])->toBe('app/')
        ->and($composer['autoload']['psr-4'])->toHaveKey('DevDoctor\\Core\\')
        ->and($composer['autoload']['psr-4'])->toHaveKey('DevDoctor\\Modules\\')
        ->and($composer['autoload']['psr-4'])->not->toHaveKey('App\\');
});

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

it('can be installed from a local path repository', function () {
    $root = dirname(__DIR__, 2);
    $workdir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'devdoctor-install-'.bin2hex(random_bytes(4));
    mkdir($workdir);

    file_put_contents($workdir.DIRECTORY_SEPARATOR.'composer.json', json_encode([
        'repositories' => [
            'packagist.org' => false,
            [
                'type' => 'path',
                'url' => $root,
                'options' => ['symlink' => false],
            ],
        ],
        'require' => [
            'rtcoder/devdoctor' => '*',
        ],
        'replace' => [
            'laravel-zero/framework' => '*',
            'symfony/yaml' => '*',
        ],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = (new ProcessRunner)->run([
        'composer',
        'install',
        '--no-interaction',
        '--no-scripts',
        '--ignore-platform-req=php',
    ], $workdir, 120);

    expect($result->successful())->toBeTrue($result->stderr)
        ->and(is_file($workdir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'devdoctor'))->toBeTrue();
});

it('ships release workflow and composite action metadata', function () {
    $root = dirname(__DIR__, 2);
    $action = Yaml::parseFile($root.'/action.yml');
    $release = Yaml::parseFile($root.'/.github/workflows/release.yml');
    $pages = Yaml::parseFile($root.'/.github/workflows/pages.yml');
    $releaseWorkflow = (string) file_get_contents($root.'/.github/workflows/release.yml');

    expect($action['runs']['using'])->toBe('composite')
        ->and($action['inputs'])->toHaveKey('version')
        ->and($release['permissions']['contents'])->toBe('write')
        ->and($release['permissions']['id-token'])->toBe('write')
        ->and($pages['permissions']['pages'])->toBe('write')
        ->and(file_get_contents($root.'/.github/scripts/update-homebrew-tap.sh'))->toContain('rtcoder/homebrew-tap')
        ->and($releaseWorkflow)->toContain('./vendor/bin/phpacker build')
        ->and($releaseWorkflow)->toContain('devdoctor-linux-x64')
        ->and($releaseWorkflow)->toContain('devdoctor-linux-arm64')
        ->and($releaseWorkflow)->toContain('devdoctor-macos-x64')
        ->and($releaseWorkflow)->toContain('devdoctor-macos-arm64')
        ->and($releaseWorkflow)->toContain('devdoctor-windows-x64.exe')
        ->and($releaseWorkflow)->toContain('release/*');
});

it('ships static documentation and pinned CI examples', function () {
    $root = dirname(__DIR__, 2);

    foreach ([
        'docs/index.html',
        'docs/installation.html',
        'docs/commands.html',
        'docs/config.html',
        'docs/scenarios.html',
        'docs/docs.js',
        'docs/manifest.json',
        'docs/commands.json',
        'docs/output-formats.html',
        'docs/issue-codes.html',
        'docs/issue-codes.js',
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

    $docsCheck = (new ProcessRunner)->run(['php', 'scripts/build-docs.php', '--check'], $root, 30);

    expect($docsCheck->successful())->toBeTrue($docsCheck->stderr)
        ->and(file_get_contents($root.'/docs/issue-codes.html'))->toContain('id="issue-code-search"')
        ->and(file_get_contents($root.'/docs/issue-codes.html'))->toContain('data-copy-code')
        ->and(file_get_contents($root.'/docs/docs.js'))->toContain('copy-snippet')
        ->and(file_get_contents($root.'/docs/manifest.json'))->toContain('"commands": "commands.json"')
        ->and(file_get_contents($root.'/docs/commands.json'))->toContain('"name": "ci"')
        ->and(file_get_contents($root.'/docs/commands.html'))->toContain('docs/commands.json')
        ->and(file_get_contents($root.'/docs/scenarios.html'))->toContain('Kubernetes / Helm')
        ->and(file_get_contents($root.'/docs/examples/github-actions.yml'))->toContain('v1.33.0')
        ->and(file_get_contents($root.'/docs/examples/gitlab-ci.yml'))->toContain('v1.33.0')
        ->and(file_get_contents($root.'/docs/examples/bitbucket-pipelines.yml'))->toContain('v1.33.0')
        ->and(file_get_contents($root.'/README.md'))->toContain('devdoctor-linux-x64')
        ->and(file_get_contents($root.'/docs/installation.html'))->toContain('Standalone Release Binary')
        ->and(file_get_contents($root.'/docs/release-verification.html'))->toContain('devdoctor.sha256');
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
    $enumCodes = array_map(static fn (IssueCode $code): string => $code->value, IssueCode::cases());
    $enumModules = array_map(static fn (ModuleName $module): string => $module->value, ModuleName::cases());
    $uniqueCatalogCodes = array_values(array_unique($catalogCodes));
    sort($catalogCodes);
    sort($enumCodes);
    sort($enumModules);
    sort($uniqueCatalogCodes);

    expect($catalog['schema_version'])->toBe('1.0')
        ->and($catalogCodes)->toBe($uniqueCatalogCodes)
        ->and($catalogCodes)->toBe($applicationCodes)
        ->and($catalogCodes)->toBe($enumCodes);

    foreach ($catalog['codes'] as $entry) {
        expect($entry)->toHaveKeys(['code', 'module', 'description', 'introduced', 'status'])
            ->and($entry['module'])->toBeIn($enumModules)
            ->and($entry['status'])->toBeIn(['active', 'deprecated']);
    }

    foreach ($applicationCodes as $code) {
        expect($documented)->toContain($code);
    }
});
