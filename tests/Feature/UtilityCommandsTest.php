<?php

use Illuminate\Support\Facades\Artisan;

it('explains issue codes as json', function () {
    $exitCode = Artisan::call('explain', ['code' => 'DD_ENV_FILE_MISSING', '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($output['issue_codes'][0]['code'])->toBe('DD_ENV_FILE_MISSING')
        ->and($output['issue_codes'][0]['module'])->toBe('env')
        ->and($output['issue_codes'][0]['hint'])->toBeString();
});

it('filters explained issue codes by module', function () {
    $exitCode = Artisan::call('explain', ['--module' => 'env', '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($output['issue_codes'])->not->toBeEmpty()
        ->and(array_unique(array_column($output['issue_codes'], 'module')))->toBe(['env']);
});

it('returns invalid config for unknown issue codes', function () {
    $exitCode = Artisan::call('explain', ['code' => 'DD_UNKNOWN', '--format' => 'json']);

    expect($exitCode)->toBe(3)
        ->and(Artisan::output())->toContain('unknown_issue_code');
});

it('prints command catalog as json', function () {
    $exitCode = Artisan::call('commands', ['--module' => 'env', '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($output['schema_version'])->toBe('1.0')
        ->and($output['commands'][0]['name'])->toBe('env')
        ->and($output['commands'][0]['read_only'])->toBeTrue();
});

it('prints inventory with detected presets', function () {
    $path = sys_get_temp_dir().'/devdoctor-inventory-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/package.json', '{"dependencies":{"vite":"^7.0.0"}}');

    $exitCode = Artisan::call('inventory', ['--path' => $path, '--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(array_column($output['presets'], 'preset'))->toContain('frontend')
        ->and($output['available_modules'])->toContain('env');
});

it('prints policy as json', function () {
    $exitCode = Artisan::call('policy', ['--format' => 'json']);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($output['policy']['read_only_by_default'])->toBeTrue()
        ->and($output['policy']['php_requirement'])->toBe('^8.5');
});

it('prints a redacted support bundle', function () {
    $path = sys_get_temp_dir().'/devdoctor-support-'.bin2hex(random_bytes(4));
    mkdir($path);
    putenv('GITHUB_TOKEN=super-secret-token-value');

    $exitCode = Artisan::call('support-bundle', ['--path' => $path]);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($output['version'])->toBeString()
        ->and($output['environment']['GITHUB_TOKEN'])->not->toBe('super-secret-token-value');

    putenv('GITHUB_TOKEN');
});
