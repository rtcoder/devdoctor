<?php

use App\DevDoctor\Core\ExitCode;
use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Output\JsonRenderer;
use App\DevDoctor\Core\Output\TableRenderer;
use App\DevDoctor\Core\PathResolver;
use App\DevDoctor\Core\ProcessRunner;
use App\DevDoctor\Core\Redactor;
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

it('renders table output', function () {
    $result = new ModuleResult('env', new IssueCollection([
        new Issue('DD_ENV_READY', Severity::INFO, 'ready', 'env'),
    ]));

    $table = app(TableRenderer::class)->render([$result]);

    expect($table)
        ->toContain('DevDoctor')
        ->toContain('env')
        ->toContain('passed')
        ->toContain('DD_ENV_READY');
});

it('redacts sensitive values', function () {
    $redactor = new Redactor;

    expect($redactor->redact('short'))->toBe('********')
        ->and($redactor->redact('stripe_live_example_token_abcdefghijklmnopqrstuvwxyz123456'))
        ->toStartWith('stripe_l')
        ->toEndWith('3456')
        ->not->toContain('abcdefghijklmnopqrstuvwxyz')
        ->and($redactor->redactContext(['api_key' => 'secret-value-1234567890']))
        ->toHaveKey('api_key');
});

it('normalizes displayed paths relative to the base path', function () {
    $resolver = PathResolver::fromBasePath('/tmp/devdoctor-project');

    expect($resolver->absolute('.env'))->toBe('/tmp/devdoctor-project/.env')
        ->and($resolver->display('/tmp/devdoctor-project/config/devdoctor.yml'))->toBe('config/devdoctor.yml')
        ->and($resolver->display('/elsewhere/file.txt'))->toBe('/elsewhere/file.txt');
});

it('runs processes and captures output', function () {
    $result = app(ProcessRunner::class)->run(['php', '-r', 'echo "ok";'], getcwd());

    expect($result->successful())->toBeTrue()
        ->and($result->stdout)->toBe('ok')
        ->and($result->stderr)->toBe('');
});

it('reports process failures', function () {
    $result = app(ProcessRunner::class)->run(['php', '-r', 'fwrite(STDERR, "nope"); exit(7);'], getcwd());

    expect($result->successful())->toBeFalse()
        ->and($result->exitCode)->toBe(7)
        ->and($result->stderr)->toBe('nope');
});

it('reports process timeouts', function () {
    $result = app(ProcessRunner::class)->run(['php', '-r', 'sleep(2);'], getcwd(), 1);

    expect($result->successful())->toBeFalse()
        ->and($result->timedOut)->toBeTrue();
});
