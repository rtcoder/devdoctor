<?php

use Illuminate\Support\Facades\Artisan;

it('exposes the env command with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-env-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\n");

    $this->artisan('env', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"tool": "devdoctor"');
});

it('runs ports diagnostics with json output', function () {
    $this->artisan('ports', ['--port' => ['70000'], '--format' => 'json'])
        ->assertExitCode(1)
        ->expectsOutputToContain('DD_PORT_INVALID_PORT');
});

it('runs composer diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-composer-command-'.bin2hex(random_bytes(4));
    mkdir($path);

    $this->artisan('composer', ['--path' => $path, '--format' => 'json', '--no-validate' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_COMPOSER_NOT_PROJECT');
});

it('runs git diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-git-command-'.bin2hex(random_bytes(4));
    mkdir($path);

    $this->artisan('git', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_GIT_NOT_REPOSITORY');
});

it('runs docker diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-docker-command-'.bin2hex(random_bytes(4));
    mkdir($path);

    $this->artisan('docker', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_DOCKER_NO_COMPOSE_PROJECT');
});

it('runs presets diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-presets-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/package.json', '{"devDependencies":{"vite":"^7.0"}}');

    $exitCode = Artisan::call('presets', ['--path' => $path, '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(array_column($output['modules'][0]['issues'], 'key'))->toBe(['node', 'vite']);
});

it('runs default ci modules without ports', function () {
    $path = sys_get_temp_dir().'/devdoctor-ci-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\n");

    $exitCode = Artisan::call('ci', ['--path' => $path, '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(array_column($output['modules'], 'name'))->toBe(['env', 'composer', 'git', 'docker']);
});

it('supports ci module selection exclude and unknown module handling', function () {
    $path = sys_get_temp_dir().'/devdoctor-ci-select-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\n");

    $this->artisan('ci', ['--path' => $path, '--modules' => 'env,composer', '--exclude' => 'composer', '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"name": "env"')
        ->doesntExpectOutputToContain('"name": "composer"');

    $this->artisan('ci', ['--path' => $path, '--modules' => 'env,nope', '--format' => 'json'])
        ->assertExitCode(3)
        ->expectsOutputToContain('DD_CI_UNKNOWN_MODULE');

    $this->artisan('ci', ['--path' => $path, '--modules' => 'presets', '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"name": "presets"');
});

it('supports ci fail on warnings controls', function () {
    $path = sys_get_temp_dir().'/devdoctor-ci-warnings-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\nQUEUE_CONNECTION=sync\n");

    $this->artisan('ci', ['--path' => $path, '--modules' => 'env', '--format' => 'json'])
        ->assertExitCode(1)
        ->expectsOutputToContain('DD_ENV_MISSING_IN_ENV');

    $this->artisan('ci', ['--path' => $path, '--modules' => 'env', '--format' => 'json', '--no-fail-on-warnings' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_ENV_MISSING_IN_ENV');
});

it('rejects invalid output formats consistently', function () {
    $this->artisan('env', ['--format' => 'xml'])
        ->assertExitCode(3)
        ->expectsOutputToContain('Invalid --format value');
});

it('returns invalid config exit code for malformed devdoctor yaml', function () {
    $path = sys_get_temp_dir().'/devdoctor-invalid-config-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/devdoctor.yml', "modules:\n  env: [");

    $this->artisan('env', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(3)
        ->expectsOutputToContain('DD_ENV_INVALID_CONFIG');
});
