<?php

use DevDoctor\Modules\Mobile\MobileAnalyzer;
use DevDoctor\Modules\Mobile\MobileOptions;

function mobileFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-mobile-'.bin2hex(random_bytes(4));
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

it('reports non mobile projects as info', function () {
    $issues = (new MobileAnalyzer)->analyze(new MobileOptions(path: mobileFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_MOBILE_NOT_PROJECT');
});

it('reports ready mobile projects', function () {
    $issues = (new MobileAnalyzer)->analyze(new MobileOptions(path: mobileFixture([
        'android/app/src/main/AndroidManifest.xml' => '<manifest><application /></manifest>',
        'gradlew' => "#!/bin/sh\n",
        'Podfile' => "target 'App' do\nend\n",
        'Podfile.lock' => "PODS:\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_MOBILE_READY');
});

it('reports android wrapper and debuggable risks', function () {
    $issues = (new MobileAnalyzer)->analyze(new MobileOptions(path: mobileFixture([
        'android/app/src/main/AndroidManifest.xml' => '<manifest><application android:debuggable="true" /></manifest>',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_MOBILE_ANDROID_WRAPPER_MISSING')
        ->and($codes)->toContain('DD_MOBILE_ANDROID_DEBUGGABLE');
});

it('reports missing pod lockfiles and debug entitlements', function () {
    $issues = (new MobileAnalyzer)->analyze(new MobileOptions(path: mobileFixture([
        'Podfile' => "target 'App' do\nend\n",
        'App.entitlements' => "<plist><key>get-task-allow</key><true/></plist>\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_MOBILE_IOS_POD_LOCK_MISSING')
        ->and($codes)->toContain('DD_MOBILE_IOS_DEBUG_ENTITLEMENT');
});
