<?php

it('exposes the env command with json output', function () {
    $path = sys_get_temp_dir() . '/devdoctor-env-' . bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path . '/.env', "APP_ENV=local\n");
    file_put_contents($path . '/.env.example', "APP_ENV=local\n");

    $this->artisan('env', ['--path' => $path, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"tool": "devdoctor"');
});

it('keeps the full public command surface visible', function (string $command) {
    $this->artisan($command, ['--format' => 'json'])
        ->assertExitCode(1)
        ->expectsOutputToContain('NOT_IMPLEMENTED');
})->with([
    'ports',
    'docker',
    'composer',
    'git',
    'ci',
]);
