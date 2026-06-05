<?php

use DevDoctor\Core\IssueCode;
use DevDoctor\Modules\Cache\CacheAnalyzer;
use DevDoctor\Modules\Cache\CacheOptions;

it('reports when supported cache paths are not detected', function () {
    $path = sys_get_temp_dir().'/devdoctor-cache-none-'.bin2hex(random_bytes(4));
    mkdir($path);

    $issues = (new CacheAnalyzer)->analyze(new CacheOptions($path))->all();

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe(IssueCode::DD_CACHE_NOT_DETECTED);
});

it('reports ready for writable cache directories below threshold', function () {
    $path = sys_get_temp_dir().'/devdoctor-cache-ready-'.bin2hex(random_bytes(4));
    mkdir($path.'/bootstrap/cache', recursive: true);

    $issues = (new CacheAnalyzer)->analyze(new CacheOptions($path))->all();

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe(IssueCode::DD_CACHE_READY);
});

it('reports large cache directories', function () {
    $path = sys_get_temp_dir().'/devdoctor-cache-large-'.bin2hex(random_bytes(4));
    mkdir($path.'/.next/cache', recursive: true);
    file_put_contents($path.'/.next/cache/blob', str_repeat('x', 1024 * 1024 + 1));

    $issues = (new CacheAnalyzer)->analyze(new CacheOptions($path, maxSizeMb: 1))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_CACHE_DIRECTORY_LARGE);
});

it('reports laravel cache artifacts with a suggested command', function () {
    $path = sys_get_temp_dir().'/devdoctor-cache-laravel-'.bin2hex(random_bytes(4));
    mkdir($path.'/bootstrap/cache', recursive: true);
    file_put_contents($path.'/bootstrap/cache/config.php', '<?php return [];');

    $issues = (new CacheAnalyzer)->analyze(new CacheOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_CACHE_LARAVEL_ARTIFACT)
        ->and($issues[0]->fix?->command)->toBe('php artisan optimize:clear');
});
