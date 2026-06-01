<?php

use App\DevDoctor\Modules\Env\EnvParser;

it('parses simple key value pairs', function () {
    $file = (new EnvParser)->parse("APP_ENV=local\nAPP_DEBUG=true\n", '.env');

    expect($file->entries)->toHaveCount(2)
        ->and($file->get('APP_ENV')?->value)->toBe('local')
        ->and($file->get('APP_DEBUG')?->value)->toBe('true');
});

it('parses quoted values and empty values', function () {
    $file = (new EnvParser)->parse("APP_NAME=\"Demo App\"\nMAIL_HOST=\nSINGLE='quoted value'\n", '.env');

    expect($file->get('APP_NAME')?->value)->toBe('Demo App')
        ->and($file->get('APP_NAME')?->quoted)->toBeTrue()
        ->and($file->get('MAIL_HOST')?->value)->toBe('')
        ->and($file->get('SINGLE')?->value)->toBe('quoted value')
        ->and($file->get('SINGLE')?->quoted)->toBeTrue();
});

it('ignores empty lines and comments', function () {
    $file = (new EnvParser)->parse("# comment\n\nAPP_ENV=local\n", '.env');

    expect($file->entries)->toHaveCount(1)
        ->and($file->get('APP_ENV')?->line)->toBe(3);
});

it('handles export declarations and spaced assignments', function () {
    $file = (new EnvParser)->parse("export APP_ENV=local\nSPACED = value\n", '.env');

    expect($file->get('APP_ENV')?->exported)->toBeTrue()
        ->and($file->get('SPACED')?->value)->toBe('value');
});

it('records raw lines, file names, line numbers, and duplicate keys', function () {
    $file = (new EnvParser)->parse("APP_ENV=local\nAPP_ENV=production\n", '.env');

    expect($file->get('APP_ENV')?->rawLine)->toBe('APP_ENV=local')
        ->and($file->get('APP_ENV')?->file)->toBe('.env')
        ->and($file->duplicates())->toHaveKey('APP_ENV')
        ->and($file->duplicates()['APP_ENV'])->toHaveCount(2);
});
