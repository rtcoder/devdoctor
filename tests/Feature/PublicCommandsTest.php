<?php

use Illuminate\Support\Facades\Artisan;

it('exposes the env command with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-env-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nQUEUE_CONNECTION=sync\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nQUEUE_CONNECTION=sync\n");

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

it('runs php diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-php-command-'.bin2hex(random_bytes(4));
    mkdir($path);

    $this->artisan('php', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"name": "php"');
});

it('runs cache diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-cache-command-'.bin2hex(random_bytes(4));
    mkdir($path.'/bootstrap/cache', recursive: true);

    $this->artisan('cache', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_CACHE_READY');
});

it('runs database diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-db-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "DB_CONNECTION=sqlite\nDB_DATABASE=:memory:\n");

    $this->artisan('db', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_DB_READY');
});

it('runs queue diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-queue-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\nQUEUE_CONNECTION=sync\n");

    $this->artisan('queue', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_QUEUE_READY');
});

it('runs node diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-node-command-'.bin2hex(random_bytes(4));
    mkdir($path);

    $this->artisan('node', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_NODE_NOT_PROJECT');
});

it('runs laravel diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-laravel-command-'.bin2hex(random_bytes(4));
    mkdir($path);

    $this->artisan('laravel', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_LARAVEL_NOT_PROJECT');
});

it('runs security diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-security-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.gitignore', ".env\n");

    $this->artisan('security', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('DD_SECURITY_READY');
});

it('runs health diagnostics with json output', function () {
    $path = sys_get_temp_dir().'/devdoctor-health-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nQUEUE_CONNECTION=sync\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nQUEUE_CONNECTION=sync\n");
    file_put_contents($path.'/.gitignore', ".env\n");

    $exitCode = Artisan::call('health', ['--path' => $path, '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(array_column($output['modules'], 'name'))->toBe(['presets', 'env', 'cache', 'php', 'node', 'laravel', 'composer', 'db', 'queue', 'git', 'docker', 'security']);
});

it('supports health module selection ports opt in and unknown modules', function () {
    $path = sys_get_temp_dir().'/devdoctor-health-select-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\n");
    file_put_contents($path.'/.gitignore', ".env\n");

    $exitCode = Artisan::call('health', ['--path' => $path, '--include-ports' => true, '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBeGreaterThanOrEqual(0)
        ->and(array_column($output['modules'], 'name'))->toContain('ports');

    $this->artisan('health', ['--path' => $path, '--modules' => 'env,security', '--exclude' => 'security', '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"name": "env"')
        ->doesntExpectOutputToContain('"name": "security"');

    $this->artisan('health', ['--path' => $path, '--modules' => 'env,nope', '--format' => 'json'])
        ->assertExitCode(3)
        ->expectsOutputToContain('DD_HEALTH_UNKNOWN_MODULE');
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

it('prints config wizard output in dry run mode', function () {
    $path = sys_get_temp_dir().'/devdoctor-init-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env.example', "APP_DEBUG=false\n");

    $exitCode = Artisan::call('init', ['--path' => $path, '--dry-run' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('modules:')
        ->and($output)->toContain('APP_DEBUG:')
        ->and(is_file($path.'/devdoctor.yml'))->toBeFalse();
});

it('requires dry run for non interactive config generation', function () {
    $path = sys_get_temp_dir().'/devdoctor-init-non-interactive-'.bin2hex(random_bytes(4));
    mkdir($path);

    $this->artisan('init', ['--path' => $path, '--ci' => true])
        ->assertExitCode(3)
        ->expectsOutputToContain('Writing config requires an interactive confirmation');
});

it('does not overwrite config without force', function () {
    $path = sys_get_temp_dir().'/devdoctor-init-existing-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/devdoctor.yml', "existing: true\n");

    $this->artisan('init', ['--path' => $path])
        ->assertExitCode(3)
        ->expectsOutputToContain('already exists');

    expect(file_get_contents($path.'/devdoctor.yml'))->toBe("existing: true\n");
});

it('runs default ci modules without ports', function () {
    $path = sys_get_temp_dir().'/devdoctor-ci-command-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\n");

    $exitCode = Artisan::call('ci', ['--path' => $path, '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(array_column($output['modules'], 'name'))->toBe(['env', 'php', 'node', 'laravel', 'composer', 'git', 'docker']);
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

    $this->artisan('ci', ['--path' => $path, '--modules' => 'security', '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"name": "security"');

    $this->artisan('ci', ['--path' => $path, '--modules' => 'db', '--format' => 'json'])
        ->assertExitCode(1)
        ->expectsOutputToContain('"name": "db"');

    $this->artisan('ci', ['--path' => $path, '--modules' => 'cache', '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"name": "cache"');

    $this->artisan('ci', ['--path' => $path, '--modules' => 'queue', '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"name": "queue"');
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

it('renders ci diagnostics as sarif', function () {
    $path = sys_get_temp_dir().'/devdoctor-ci-sarif-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\nQUEUE_CONNECTION=sync\n");

    $exitCode = Artisan::call('ci', ['--path' => $path, '--modules' => 'env', '--format' => 'sarif']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($output['version'])->toBe('2.1.0')
        ->and($output['runs'][0]['results'][0]['ruleId'])->toBe('DD_ENV_MISSING_IN_ENV');
});

it('writes and applies ci baselines without hiding findings', function () {
    $path = sys_get_temp_dir().'/devdoctor-ci-baseline-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");
    file_put_contents($path.'/.env.example', "APP_ENV=local\nQUEUE_CONNECTION=sync\n");

    $writeExitCode = Artisan::call('ci', [
        '--path' => $path,
        '--modules' => 'env',
        '--format' => 'json',
        '--write-baseline' => 'devdoctor-baseline.json',
    ]);

    expect($writeExitCode)->toBe(1)
        ->and(is_file($path.'/devdoctor-baseline.json'))->toBeTrue();

    $exitCode = Artisan::call('ci', [
        '--path' => $path,
        '--modules' => 'env',
        '--format' => 'json',
        '--baseline' => 'devdoctor-baseline.json',
    ]);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($output['summary']['suppressed'])->toBe(1)
        ->and($output['modules'][0]['issues'][0]['suppressed'])->toBeTrue();
});

it('reports missing and invalid ci baselines', function () {
    $path = sys_get_temp_dir().'/devdoctor-ci-invalid-baseline-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/invalid.json', '{}');

    $this->artisan('ci', ['--path' => $path, '--baseline' => 'missing.json', '--format' => 'json'])
        ->assertExitCode(4)
        ->expectsOutputToContain('DD_CI_BASELINE_MISSING');

    $this->artisan('ci', ['--path' => $path, '--baseline' => 'invalid.json', '--format' => 'json'])
        ->assertExitCode(3)
        ->expectsOutputToContain('DD_CI_BASELINE_INVALID');
});

it('returns invalid config exit code for malformed devdoctor yaml', function () {
    $path = sys_get_temp_dir().'/devdoctor-invalid-config-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/devdoctor.yml', "modules:\n  env: [");

    $this->artisan('env', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(3)
        ->expectsOutputToContain('DD_ENV_INVALID_CONFIG');
});
