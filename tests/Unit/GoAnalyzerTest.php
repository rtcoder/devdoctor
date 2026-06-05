<?php

use DevDoctor\Modules\Go\GoAnalyzer;
use DevDoctor\Modules\Go\GoOptions;

function goFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-go-'.bin2hex(random_bytes(4));
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

it('reports non go projects as info', function () {
    $issues = (new GoAnalyzer)->analyze(new GoOptions(path: goFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_GO_NOT_PROJECT');
});

it('reports ready go modules with sums', function () {
    $issues = (new GoAnalyzer)->analyze(new GoOptions(path: goFixture([
        'go.mod' => "module github.com/example/app\n\ngo 1.25\nrequire example.test/pkg v1.0.0\n",
        'go.sum' => "example.test/pkg v1.0.0 h1:abc\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_GO_READY');
});

it('reports missing go sum and invalid module paths', function () {
    $issues = (new GoAnalyzer)->analyze(new GoOptions(path: goFixture([
        'go.mod' => "module ./local\n\ngo 1.25\nrequire example.test/pkg v1.0.0\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_GO_MODULE_PATH_INVALID')
        ->and($codes)->toContain('DD_GO_SUM_MISSING');
});

it('reports local replace directives and toolchain declarations', function () {
    $issues = (new GoAnalyzer)->analyze(new GoOptions(path: goFixture([
        'go.mod' => "module github.com/example/app\n\ngo 1.25\ntoolchain go1.25.1\nreplace example.test/pkg => ../pkg\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_GO_REPLACE_DIRECTIVE')
        ->and($codes)->toContain('DD_GO_TOOLCHAIN_DECLARED');
});

it('reports go work entries without a module', function () {
    $issues = (new GoAnalyzer)->analyze(new GoOptions(path: goFixture([
        'go.work' => "go 1.25\n\nuse (\n    ./api\n    ./worker\n)\n",
        'api/go.mod' => "module github.com/example/api\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_GO_WORKSPACE_MODULE_MISSING');
});

it('reports vendor metadata as info', function () {
    $issues = (new GoAnalyzer)->analyze(new GoOptions(path: goFixture([
        'go.mod' => "module github.com/example/app\n\ngo 1.25\n",
        'vendor/modules.txt' => "# vendor\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_GO_VENDOR_PRESENT');
});
