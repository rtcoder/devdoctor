<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Mobile;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class MobileAnalyzer
{
    public function analyze(MobileOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $androidFiles = $this->androidManifestFiles($files);
        $hasAndroid = $androidFiles !== [] || $files->exists('android/app/build.gradle') || $files->exists('android/app/build.gradle.kts');
        $hasIos = $files->exists('Podfile') || $files->exists('Podfile.lock') || $this->hasIosProject($files);

        if (! $hasAndroid && ! $hasIos) {
            $issues->add(new Issue(
                code: IssueCode::DD_MOBILE_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No native Android or iOS project detected',
                module: ModuleName::MOBILE,
            ));

            return $issues;
        }

        $this->checkAndroid($issues, $files, $androidFiles, $hasAndroid, $options);
        $this->checkIos($issues, $files, $hasIos, $options);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_MOBILE_READY,
                severity: Severity::INFO,
                message: 'Mobile diagnostics found no actionable issues.',
                module: ModuleName::MOBILE,
            ));
        }

        return $issues;
    }

    /**
     * @param  list<string>  $androidFiles
     */
    private function checkAndroid(IssueCollection $issues, ProjectFiles $files, array $androidFiles, bool $hasAndroid, MobileOptions $options): void
    {
        if (! $hasAndroid) {
            return;
        }

        if (! $files->exists('gradlew') && ! $files->exists('gradlew.bat') && ! $files->exists('android/gradlew') && ! $files->exists('android/gradlew.bat')) {
            $issues->add(new Issue(
                code: IssueCode::DD_MOBILE_ANDROID_WRAPPER_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'Android project does not include a Gradle wrapper',
                module: ModuleName::MOBILE,
                key: 'gradlew',
            ));
        }

        foreach ($androidFiles as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/android:debuggable\s*=\s*["\']true["\']/i', $line) !== 1) {
                    continue;
                }

                $issues->add(new Issue(
                    code: IssueCode::DD_MOBILE_ANDROID_DEBUGGABLE,
                    severity: Severity::WARNING,
                    message: 'Android manifest enables debuggable mode',
                    module: ModuleName::MOBILE,
                    file: $file,
                    line: $lineNumber + 1,
                ));
            }
        }
    }

    private function checkIos(IssueCollection $issues, ProjectFiles $files, bool $hasIos, MobileOptions $options): void
    {
        if (! $hasIos) {
            return;
        }

        if ($files->exists('Podfile') && ! $files->exists('Podfile.lock')) {
            $issues->add(new Issue(
                code: IssueCode::DD_MOBILE_IOS_POD_LOCK_MISSING,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'Podfile exists but Podfile.lock was not found',
                module: ModuleName::MOBILE,
                file: 'Podfile',
                key: 'Podfile.lock',
            ));
        }

        foreach ($files->glob('*.entitlements') as $file) {
            if (! str_contains($files->contents($file), 'get-task-allow')) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_MOBILE_IOS_DEBUG_ENTITLEMENT,
                severity: Severity::WARNING,
                message: 'iOS entitlements appear to allow debug task access',
                module: ModuleName::MOBILE,
                file: $file,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function androidManifestFiles(ProjectFiles $files): array
    {
        return array_values(array_unique([
            ...$files->existing(['AndroidManifest.xml', 'app/src/main/AndroidManifest.xml', 'android/app/src/main/AndroidManifest.xml']),
            ...$files->glob('*/src/main/AndroidManifest.xml'),
        ]));
    }

    private function hasIosProject(ProjectFiles $files): bool
    {
        return $files->exists('ios/Runner.xcodeproj/project.pbxproj')
            || $files->glob('*.xcodeproj/project.pbxproj') !== []
            || $files->glob('ios/*.xcodeproj/project.pbxproj') !== [];
    }
}
