<?php

use App\DevDoctor\Core\ExitCode;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Output\JsonRenderer;
use App\DevDoctor\Core\Severity;

it('summarizes issues and maps exit codes', function () {
    $issues = new IssueCollection([
        new Issue('DD_TEST_WARNING', Severity::WARNING, 'warning'),
        new Issue('DD_TEST_ERROR', Severity::ERROR, 'error'),
        new Issue('DD_TEST_INFO', Severity::INFO, 'info'),
    ]);

    expect($issues->summary())->toBe([
        'errors' => 1,
        'warnings' => 1,
        'info' => 1,
    ])->and(ExitCode::fromIssues($issues))->toBe(ExitCode::ERRORS);
});

it('renders valid json output', function () {
    $result = new ModuleResult('env', new IssueCollection([
        new Issue('DD_ENV_READY', Severity::INFO, 'ready', 'env'),
    ]));

    $json = app(JsonRenderer::class)->render([$result]);
    $decoded = json_decode($json, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['tool'])->toBe('devdoctor')
        ->and($decoded['modules'][0]['name'])->toBe('env');
});
