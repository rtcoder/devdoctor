<?php

use DevDoctor\Modules\Rust\RustAnalyzer;
use DevDoctor\Modules\Rust\RustOptions;

function rustFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-rust-'.bin2hex(random_bytes(4));
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

it('reports non rust projects as info', function () {
    $issues = (new RustAnalyzer)->analyze(new RustOptions(path: rustFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_RUST_NOT_PROJECT');
});

it('reports ready rust library manifests', function () {
    $issues = (new RustAnalyzer)->analyze(new RustOptions(path: rustFixture([
        'Cargo.toml' => "[package]\nname = \"demo\"\nversion = \"0.1.0\"\nedition = \"2024\"\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_RUST_READY');
});

it('reports missing lockfiles for binary packages', function () {
    $issues = (new RustAnalyzer)->analyze(new RustOptions(path: rustFixture([
        'Cargo.toml' => "[package]\nname = \"demo\"\nversion = \"0.1.0\"\nedition = \"2024\"\n",
        'src/main.rs' => "fn main() {}\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_RUST_LOCK_MISSING');
});

it('reports workspace members without manifests', function () {
    $issues = (new RustAnalyzer)->analyze(new RustOptions(path: rustFixture([
        'Cargo.toml' => "[workspace]\nmembers = [\n  \"api\",\n  \"worker\"\n]\n",
        'api/Cargo.toml' => "[package]\nname = \"api\"\nversion = \"0.1.0\"\nedition = \"2024\"\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_RUST_WORKSPACE_MEMBER_MISSING');
});

it('reports path and git dependency sources', function () {
    $issues = (new RustAnalyzer)->analyze(new RustOptions(path: rustFixture([
        'Cargo.toml' => "[package]\nname = \"demo\"\nversion = \"0.1.0\"\nedition = \"2024\"\n\n[dependencies]\nlocal = { path = \"../local\" }\nremote = { git = \"https://example.test/repo.git\" }\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_RUST_PATH_DEPENDENCY')
        ->and($codes)->toContain('DD_RUST_GIT_DEPENDENCY');
});

it('reports release profile anomalies and toolchain files', function () {
    $issues = (new RustAnalyzer)->analyze(new RustOptions(path: rustFixture([
        'Cargo.toml' => "[package]\nname = \"demo\"\nversion = \"0.1.0\"\nedition = \"2024\"\n\n[profile.release]\ndebug = true\n",
        'rust-toolchain.toml' => "[toolchain]\nchannel = \"1.85.0\"\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_RUST_RELEASE_PROFILE_DEBUG')
        ->and($codes)->toContain('DD_RUST_TOOLCHAIN_DECLARED');
});
