<?php

use DevDoctor\Core\IssueCode;
use DevDoctor\Modules\Http\HttpAnalyzer;
use DevDoctor\Modules\Http\HttpOptions;

it('reports missing url targets as informational', function () {
    $path = sys_get_temp_dir().'/devdoctor-http-missing-'.bin2hex(random_bytes(4));
    mkdir($path);

    $issues = (new HttpAnalyzer)->analyze(new HttpOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_HTTP_URL_MISSING);
});

it('reports ready for valid http urls', function () {
    $path = sys_get_temp_dir().'/devdoctor-http-ready-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=local\nAPP_URL=http://localhost:8000\n");

    $issues = (new HttpAnalyzer)->analyze(new HttpOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_HTTP_READY);
});

it('reports invalid explicit urls', function () {
    $path = sys_get_temp_dir().'/devdoctor-http-invalid-'.bin2hex(random_bytes(4));
    mkdir($path);

    $issues = (new HttpAnalyzer)->analyze(new HttpOptions($path, urls: ['localhost']))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_HTTP_URL_INVALID);
});

it('reports insecure local production urls', function () {
    $path = sys_get_temp_dir().'/devdoctor-http-prod-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "APP_ENV=production\nAPP_URL=http://localhost\n");

    $codes = array_map(
        static fn ($issue) => $issue->code,
        (new HttpAnalyzer)->analyze(new HttpOptions($path))->all(),
    );

    expect($codes)->toContain(IssueCode::DD_HTTP_INSECURE_PRODUCTION_URL)
        ->and($codes)->toContain(IssueCode::DD_HTTP_LOCALHOST_PRODUCTION_URL);
});
