<?php

use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvAnalysisOptions;
use DevDoctor\Modules\Env\EnvAnalyzer;

function envFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-env-analyzer-'.bin2hex(random_bytes(4));
    mkdir($path);

    foreach ($files as $file => $contents) {
        file_put_contents($path.'/'.$file, $contents);
    }

    return $path;
}

it('reports missing env files', function () {
    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(path: envFixture([])));

    expect($issues->summary())->toMatchArray(['errors' => 1, 'warnings' => 1])
        ->and($issues->all()[0]->code)->toBe('DD_ENV_FILE_MISSING');
});

it('detects key drift between env and example files', function () {
    $path = envFixture([
        '.env' => "APP_ENV=local\nLOCAL_ONLY=value\n",
        '.env.example' => "APP_ENV=local\nDATABASE_URL=\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(path: $path));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_ENV_MISSING_IN_ENV')
        ->and($codes)->toContain('DD_ENV_MISSING_IN_EXAMPLE');
});

it('treats key drift as errors in strict mode', function () {
    $path = envFixture([
        '.env' => "APP_ENV=local\n",
        '.env.example' => "APP_ENV=local\nDATABASE_URL=mysql://localhost\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(path: $path, strict: true));

    expect($issues->all()[0]->severity)->toBe(Severity::ERROR);
});

it('detects duplicate keys invalid names empty values and production debug', function () {
    $path = envFixture([
        '.env' => "APP_ENV=production\nAPP_DEBUG=true\nAPP_ENV=local\nbad-key=value\nMAIL_HOST=\n",
        '.env.example' => "APP_ENV=local\nAPP_DEBUG=false\nbad-key=value\nMAIL_HOST=smtp\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(path: $path));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_ENV_DUPLICATE_KEY')
        ->and($codes)->toContain('DD_ENV_INVALID_KEY_NAME')
        ->and($codes)->toContain('DD_ENV_EMPTY_VALUE')
        ->and($codes)->toContain('DD_ENV_PROD_DEBUG');
});

it('detects likely malformed url values', function () {
    $path = envFixture([
        '.env' => "DATABASE_URL=not-a-url\n",
        '.env.example' => "DATABASE_URL=https://example.test\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(path: $path));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_ENV_INVALID_TYPE');
});

it('detects suspicious secrets in example files and redacts values', function () {
    $path = envFixture([
        '.env' => "APP_ENV=local\n",
        '.env.example' => "APP_ENV=local\nAPI_TOKEN=secret_token_value_1234567890\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(path: $path));
    $secretIssue = collect($issues->all())->firstWhere('code', 'DD_ENV_SECRET_IN_EXAMPLE');

    expect($secretIssue)->not->toBeNull()
        ->and($secretIssue->message)->not->toContain('secret_token_value_1234567890')
        ->and($secretIssue->context['redacted_value'])->not->toBe('secret_token_value_1234567890');
});

it('ignores placeholder secrets in example files', function () {
    $path = envFixture([
        '.env' => "APP_ENV=local\nAPI_TOKEN=real-local-token\n",
        '.env.example' => "APP_ENV=local\nAPI_TOKEN=your-key-here\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(path: $path));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->not->toContain('DD_ENV_SECRET_IN_EXAMPLE');
});

it('applies configured required type allowed and conditional rules', function () {
    $path = envFixture([
        '.env' => "APP_ENV=prod\nAPP_DEBUG=true\nMAIL_MAILER=smtp\nDATABASE_URL=not-a-url\nAGE=nope\n",
        '.env.example' => "APP_ENV=local\nAPP_DEBUG=false\nMAIL_MAILER=smtp\nMAIL_HOST=smtp\nDATABASE_URL=https://example.test\nAGE=1\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(
        path: $path,
        rules: [
            'APP_ENV' => ['allowed' => ['local', 'production']],
            'APP_DEBUG' => ['type' => 'bool', 'forbidden_when' => ['APP_ENV' => 'prod']],
            'MAIL_HOST' => ['required_when' => ['MAIL_MAILER' => 'smtp']],
            'DATABASE_URL' => ['type' => 'url'],
            'AGE' => ['type' => 'int'],
        ],
    ));

    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_ENV_INVALID_ALLOWED_VALUE')
        ->and($codes)->toContain('DD_ENV_FORBIDDEN_WHEN_PRESENT')
        ->and($codes)->toContain('DD_ENV_REQUIRED_WHEN_MISSING')
        ->and($codes)->toContain('DD_ENV_INVALID_TYPE');
});

it('respects configured key diff ignores', function () {
    $path = envFixture([
        '.env' => "APP_ENV=local\nLOCAL_ONLY=value\n",
        '.env.example' => "APP_ENV=local\nOPTIONAL_KEY=value\n",
    ]);

    $issues = (new EnvAnalyzer)->analyze(new EnvAnalysisOptions(
        path: $path,
        ignoreMissingInEnv: ['OPTIONAL_KEY'],
        ignoreMissingInExample: ['LOCAL_ONLY'],
    ));

    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->not->toContain('DD_ENV_MISSING_IN_ENV')
        ->and($codes)->not->toContain('DD_ENV_MISSING_IN_EXAMPLE');
});
