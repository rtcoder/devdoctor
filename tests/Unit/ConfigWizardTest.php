<?php

use App\DevDoctor\Core\Config\ConfigWizard;

function wizardFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-wizard-'.bin2hex(random_bytes(4));
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

it('generates env config with conservative inferred rules', function () {
    $yaml = (new ConfigWizard)->generate(wizardFixture([
        '.env.example' => "APP_DEBUG=false\nPORT=8000\nAPI_URL=https://example.com\nNAME=example\n",
        'artisan' => '',
    ]));

    expect($yaml)
        ->toContain('detected presets: laravel')
        ->toContain('APP_DEBUG:')
        ->toContain('type: bool')
        ->toContain('type: int')
        ->toContain('type: url')
        ->toContain('required: true');
});

it('never copies environment values into generated config', function () {
    $yaml = (new ConfigWizard)->generate(wizardFixture([
        '.env' => "API_TOKEN=super-secret-value\nAPP_DEBUG=true\n",
    ]));

    expect($yaml)
        ->not->toContain('super-secret-value')
        ->not->toContain('API_TOKEN')
        ->toContain('APP_DEBUG:')
        ->toContain('type: bool');
});
