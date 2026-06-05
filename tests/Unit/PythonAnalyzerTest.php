<?php

use DevDoctor\Modules\Python\PythonAnalyzer;
use DevDoctor\Modules\Python\PythonOptions;

function pythonFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-python-'.bin2hex(random_bytes(4));
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

it('reports non python projects as info', function () {
    $issues = (new PythonAnalyzer)->analyze(new PythonOptions(path: pythonFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_PYTHON_NOT_PROJECT');
});

it('reports ready python projects with a virtual environment marker', function () {
    $issues = (new PythonAnalyzer)->analyze(new PythonOptions(path: pythonFixture([
        'requirements.txt' => "pytest\n",
        '.venv/pyvenv.cfg' => "home = /tmp\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_PYTHON_READY');
});

it('reports mixed managers and missing lockfiles', function () {
    $issues = (new PythonAnalyzer)->analyze(new PythonOptions(path: pythonFixture([
        'pyproject.toml' => "[tool.poetry]\nname = \"demo\"\n",
        'requirements.txt' => "pytest\n",
        'Pipfile' => "[packages]\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_PYTHON_MIXED_MANAGERS')
        ->and($codes)->toContain('DD_PYTHON_LOCK_MISSING');
});

it('reports suspicious dependency sources and version conflicts', function () {
    $issues = (new PythonAnalyzer)->analyze(new PythonOptions(path: pythonFixture([
        'pyproject.toml' => "requires-python = \">=3.12\"\n",
        'Pipfile' => "[requires]\npython_version = \"3.11\"\n",
        'requirements.txt' => "git+https://example.test/pkg.git\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_PYTHON_SUSPICIOUS_SOURCE')
        ->and($codes)->toContain('DD_PYTHON_VERSION_CONFLICT');
});

it('reports missing virtual environments as info by default', function () {
    $issues = (new PythonAnalyzer)->analyze(new PythonOptions(path: pythonFixture([
        'requirements.txt' => "pytest\n",
    ])));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_PYTHON_VENV_MISSING');
});
