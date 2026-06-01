<?php

use App\DevDoctor\Core\Config\ConfigLoader;
use App\DevDoctor\Core\Config\InvalidDevDoctorConfig;

it('loads env config from devdoctor yaml', function () {
    $path = sys_get_temp_dir().'/devdoctor-config-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/devdoctor.yml', <<<'YAML'
version: 1
modules:
  env:
    files:
      env: .env.local
      example: .env.example
    rules:
      APP_ENV:
        required: true
        allowed: [local, production]
    ignore:
      missing_in_env:
        - OPTIONAL_KEY
      missing_in_example:
        - LOCAL_ONLY
YAML);

    $config = (new ConfigLoader)->load($path.'/devdoctor.yml');

    expect($config->envFile)->toBe('.env.local')
        ->and($config->exampleFile)->toBe('.env.example')
        ->and($config->envRules)->toHaveKey('APP_ENV')
        ->and($config->ignoreMissingInEnv)->toBe(['OPTIONAL_KEY'])
        ->and($config->ignoreMissingInExample)->toBe(['LOCAL_ONLY']);
});

it('returns defaults when config is missing', function () {
    $config = (new ConfigLoader)->load('/tmp/devdoctor-missing-config.yml');

    expect($config->envFile)->toBe('.env')
        ->and($config->exampleFile)->toBe('.env.example')
        ->and($config->envRules)->toBe([]);
});

it('throws for invalid yaml', function () {
    $path = sys_get_temp_dir().'/devdoctor-config-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/devdoctor.yml', "modules:\n  env: [");

    expect(fn () => (new ConfigLoader)->load($path.'/devdoctor.yml'))
        ->toThrow(InvalidDevDoctorConfig::class);
});
