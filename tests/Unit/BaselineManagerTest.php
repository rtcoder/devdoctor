<?php

use DevDoctor\Core\Baseline\BaselineManager;
use DevDoctor\Core\Baseline\InvalidBaseline;
use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ModuleResult;
use DevDoctor\Core\Severity;

it('writes and applies warning and error fingerprints', function () {
    $path = sys_get_temp_dir().'/devdoctor-baseline-'.bin2hex(random_bytes(4)).'.json';
    $results = [
        new ModuleResult(ModuleName::ENV, new IssueCollection([
            new Issue('DD_ENV_MISSING_IN_ENV', Severity::WARNING, 'missing', 'env', '.env.example', 3, 'APP_KEY'),
            new Issue('DD_ENV_READY', Severity::INFO, 'ready', 'env'),
        ])),
    ];
    $manager = new BaselineManager;

    $manager->write($path, $results);
    $applied = $manager->apply($manager->load($path), $results);

    expect($applied[0]->issues->summary())->toBe([
        'errors' => 0,
        'warnings' => 0,
        'info' => 1,
        'suppressed' => 1,
    ])->and($applied[0]->issues->all()[0]->suppressed)->toBeTrue();
});

it('rejects invalid baseline files', function () {
    $path = sys_get_temp_dir().'/devdoctor-invalid-baseline-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, '{}');

    expect(fn () => (new BaselineManager)->load($path))->toThrow(InvalidBaseline::class);
});
