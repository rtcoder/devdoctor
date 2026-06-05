<?php

use DevDoctor\Modules\Iac\IacAnalyzer;
use DevDoctor\Modules\Iac\IacOptions;

function iacFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-iac-'.bin2hex(random_bytes(4));
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

it('reports non iac projects as info', function () {
    $issues = (new IacAnalyzer)->analyze(new IacOptions(path: iacFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_IAC_NOT_PROJECT');
});

it('reports ready terraform projects', function () {
    $issues = (new IacAnalyzer)->analyze(new IacOptions(path: iacFixture([
        'main.tf' => "terraform {\n  required_providers {\n    aws = { version = \"~> 5.0\" }\n  }\n}\n",
        '.terraform.lock.hcl' => "provider \"registry.terraform.io/hashicorp/aws\" {}\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_IAC_READY');
});

it('reports missing lockfiles and broad provider versions', function () {
    $issues = (new IacAnalyzer)->analyze(new IacOptions(path: iacFixture([
        'main.tf' => "terraform {\n  required_providers {\n    aws = { version = \">= 0\" }\n  }\n}\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_IAC_LOCK_MISSING')
        ->and($codes)->toContain('DD_IAC_WILDCARD_PROVIDER_VERSION');
});

it('reports secrets and unpinned remote modules', function () {
    $issues = (new IacAnalyzer)->analyze(new IacOptions(path: iacFixture([
        'main.tf' => "module \"app\" {\n  source = \"git::https://example.test/mod.git\"\n}\nvariable \"api_token\" {\n  default = \"secret\"\n}\nprovider \"aws\" {\n  secret_key = \"abc\"\n}\n",
        '.terraform.lock.hcl' => "provider \"registry.terraform.io/hashicorp/aws\" {}\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_IAC_REMOTE_MODULE_UNPINNED')
        ->and($codes)->toContain('DD_IAC_SECRET_DEFAULT')
        ->and($codes)->toContain('DD_IAC_BACKEND_SECRET');
});

it('reports unpinned terragrunt sources', function () {
    $issues = (new IacAnalyzer)->analyze(new IacOptions(path: iacFixture([
        'terragrunt.hcl' => "terraform {\n  source = \"git::https://example.test/live.git\"\n}\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_IAC_REMOTE_MODULE_UNPINNED');
});
