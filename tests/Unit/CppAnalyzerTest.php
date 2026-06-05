<?php

use DevDoctor\Modules\Cpp\CppAnalyzer;
use DevDoctor\Modules\Cpp\CppOptions;

function cppFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-cpp-'.bin2hex(random_bytes(4));
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

it('reports non cpp projects as info', function () {
    $issues = (new CppAnalyzer)->analyze(new CppOptions(path: cppFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_CPP_NOT_PROJECT');
});

it('reports ready make projects', function () {
    $issues = (new CppAnalyzer)->analyze(new CppOptions(path: cppFixture([
        'Makefile' => "all:\n\tcc main.c\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_CPP_READY');
});

it('reports mixed dependency managers and missing compile commands', function () {
    $issues = (new CppAnalyzer)->analyze(new CppOptions(path: cppFixture([
        'CMakeLists.txt' => "cmake_minimum_required(VERSION 3.25)\nproject(demo)\n",
        'vcpkg.json' => "{}\n",
        'conanfile.txt' => "[requires]\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_CPP_MIXED_DEPENDENCY_MANAGERS')
        ->and($codes)->toContain('DD_CPP_COMPILE_COMMANDS_MISSING');
});

it('reports in source cmake builds', function () {
    $issues = (new CppAnalyzer)->analyze(new CppOptions(path: cppFixture([
        'CMakeLists.txt' => "project(demo)\n",
        'CMakeCache.txt' => "# cache\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_CPP_IN_SOURCE_BUILD');
});

it('reports risky compiler flags and generator assumptions', function () {
    $issues = (new CppAnalyzer)->analyze(new CppOptions(path: cppFixture([
        'CMakeLists.txt' => "set(CMAKE_GENERATOR \"Unix Makefiles\")\nadd_compile_options(-w -fno-stack-protector)\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_CPP_RISKY_COMPILER_FLAGS')
        ->and($codes)->toContain('DD_CPP_GENERATOR_ASSUMPTION');
});

it('reports unix shell assumptions', function () {
    $issues = (new CppAnalyzer)->analyze(new CppOptions(path: cppFixture([
        'Makefile' => "clean:\n\trm -rf build\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_CPP_SHELL_ASSUMPTION');
});
