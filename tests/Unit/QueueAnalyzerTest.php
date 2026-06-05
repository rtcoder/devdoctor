<?php

use DevDoctor\Core\IssueCode;
use DevDoctor\Modules\Queue\QueueAnalyzer;
use DevDoctor\Modules\Queue\QueueOptions;

it('reports missing queue connection as informational', function () {
    $path = sys_get_temp_dir().'/devdoctor-queue-missing-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\n");

    $issues = (new QueueAnalyzer)->analyze(new QueueOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_QUEUE_CONNECTION_MISSING);
});

it('reports sync queues in production', function () {
    $path = sys_get_temp_dir().'/devdoctor-queue-sync-prod-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=production\nQUEUE_CONNECTION=sync\n");

    $issues = (new QueueAnalyzer)->analyze(new QueueOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_QUEUE_SYNC_IN_PRODUCTION);
});

it('reports required keys for async queue drivers', function () {
    $path = sys_get_temp_dir().'/devdoctor-queue-redis-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\nQUEUE_CONNECTION=redis\n");

    $issues = (new QueueAnalyzer)->analyze(new QueueOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_QUEUE_REQUIRED_KEY_MISSING)
        ->and($issues[0]->key)->toBe('REDIS_HOST');
});

it('reports ready queue configuration', function () {
    $path = sys_get_temp_dir().'/devdoctor-queue-ready-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\nQUEUE_CONNECTION=redis\nREDIS_HOST=127.0.0.1\n");

    $issues = (new QueueAnalyzer)->analyze(new QueueOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_QUEUE_READY);
});
