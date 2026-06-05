<?php

use DevDoctor\Core\ExitCode;
use DevDoctor\Core\FixSuggestion;
use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\Output\JsonRenderer;
use DevDoctor\Core\Output\SarifRenderer;
use DevDoctor\Core\Output\TableRenderer;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Platform;
use DevDoctor\Core\ProcessRunner;
use DevDoctor\Core\Redactor;
use DevDoctor\Core\Severity;

it('summarizes issues and maps exit codes', function () {
    $issues = new IssueCollection([
        new Issue(IssueCode::DD_ENV_MISSING_IN_ENV, Severity::WARNING, 'warning'),
        new Issue(IssueCode::DD_ENV_FILE_MISSING, Severity::ERROR, 'error'),
        new Issue(IssueCode::DD_ENV_READY, Severity::INFO, 'info'),
    ]);

    expect($issues->summary())->toBe([
        'errors' => 1,
        'warnings' => 1,
        'info' => 1,
        'suppressed' => 0,
    ])->and(ExitCode::fromIssues($issues))->toBe(ExitCode::ERRORS);
});

it('excludes suppressed issues from status and exit code summaries', function () {
    $issues = new IssueCollection([
        (new Issue(IssueCode::DD_ENV_MISSING_IN_ENV, Severity::WARNING, 'warning'))->withSuppressed(),
    ]);

    expect($issues->summary())->toBe([
        'errors' => 0,
        'warnings' => 0,
        'info' => 0,
        'suppressed' => 1,
    ])->and(ExitCode::fromIssues($issues))->toBe(ExitCode::OK);
});

it('renders valid json output', function () {
    $result = new ModuleResult(ModuleName::ENV, new IssueCollection([
        new Issue(
            IssueCode::DD_ENV_MISSING_IN_ENV,
            Severity::WARNING,
            'missing',
            ModuleName::ENV,
            hint: 'Add it.',
            fix: new FixSuggestion('Add the key.', 'printf token=secret-value'),
        ),
    ]));

    $json = app(JsonRenderer::class)->render([$result]);
    $decoded = json_decode($json, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['tool'])->toBe('devdoctor')
        ->and($decoded['schema_version'])->toBe('1.0')
        ->and($decoded['modules'][0]['name'])->toBe(ModuleName::ENV->value)
        ->and($decoded['modules'][0]['issues'][0]['hint'])->toBe('Add it.')
        ->and($decoded['modules'][0]['issues'][0]['fix']['command'])->not->toContain('secret-value');
});

it('renders table output', function () {
    $result = new ModuleResult(ModuleName::ENV, new IssueCollection([
        new Issue(
            IssueCode::DD_ENV_MISSING_IN_ENV,
            Severity::WARNING,
            'missing',
            ModuleName::ENV,
            hint: 'Add the missing key.',
            fix: new FixSuggestion('Add the key.', 'edit .env'),
        ),
    ]));

    $table = app(TableRenderer::class)->render([$result]);

    expect($table)
        ->toContain('DevDoctor')
        ->toContain(ModuleName::ENV->value)
        ->toContain('warning')
        ->toContain(IssueCode::DD_ENV_MISSING_IN_ENV->value)
        ->toContain('Hint: Add the missing key.')
        ->toContain('Suggested command: edit .env');
});

it('renders deterministic sarif output with locations and fingerprints', function () {
    $result = new ModuleResult(ModuleName::ENV, new IssueCollection([
        new Issue(
            IssueCode::DD_ENV_MISSING_IN_ENV,
            Severity::WARNING,
            'missing',
            ModuleName::ENV,
            '.env.example',
            3,
            'APP_KEY',
        ),
    ]));

    $sarif = json_decode(app(SarifRenderer::class)->render([$result]), true, flags: JSON_THROW_ON_ERROR);
    $finding = $sarif['runs'][0]['results'][0];

    expect($sarif['version'])->toBe('2.1.0')
        ->and($finding['ruleId'])->toBe(IssueCode::DD_ENV_MISSING_IN_ENV->value)
        ->and($finding['level'])->toBe('warning')
        ->and($finding['locations'][0]['physicalLocation']['artifactLocation']['uri'])->toBe('.env.example')
        ->and($finding['locations'][0]['physicalLocation']['region']['startLine'])->toBe(3)
        ->and($finding['partialFingerprints'])->toHaveKey('devdoctorFingerprint/v1');
});

it('provides catalog suggestions for actionable issues', function () {
    $issue = new Issue(
        IssueCode::DD_PORT_IN_USE,
        Severity::WARNING,
        'occupied',
        ModuleName::PORTS,
        context: ['suggested_command' => 'kill -TERM 123'],
    );

    expect($issue->hint)->not->toBeNull()
        ->and($issue->fix?->command)->toBe('kill -TERM 123')
        ->and($issue->fix?->destructive)->toBeTrue();
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
    $basePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'devdoctor-project';
    $resolver = PathResolver::fromBasePath($basePath);

    expect($resolver->absolute('.env'))->toBe($basePath.DIRECTORY_SEPARATOR.'.env')
        ->and($resolver->display($basePath.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'devdoctor.yml'))->toBe('config/devdoctor.yml')
        ->and($resolver->display(DIRECTORY_SEPARATOR.'elsewhere'.DIRECTORY_SEPARATOR.'file.txt'))->toBe(DIRECTORY_SEPARATOR.'elsewhere'.DIRECTORY_SEPARATOR.'file.txt');
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

it('maps operating system families to platforms', function () {
    expect(Platform::fromOsFamily('Linux'))->toBe(Platform::LINUX)
        ->and(Platform::fromOsFamily('Darwin'))->toBe(Platform::MACOS)
        ->and(Platform::fromOsFamily('Windows'))->toBe(Platform::WINDOWS)
        ->and(Platform::fromOsFamily('SomethingElse'))->toBe(Platform::OTHER);
});

it('runs processes from working directories with spaces', function () {
    $path = sys_get_temp_dir().'/devdoctor path '.bin2hex(random_bytes(4));
    mkdir($path);

    $result = app(ProcessRunner::class)->run(['php', '-r', 'echo getcwd();'], $path);

    expect($result->successful())->toBeTrue()
        ->and(realpath($result->stdout))->toBe(realpath($path));
});
