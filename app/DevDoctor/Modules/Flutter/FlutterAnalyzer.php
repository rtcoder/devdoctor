<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Flutter;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class FlutterAnalyzer
{
    public function analyze(FlutterOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;

        if (! $files->exists('pubspec.yaml') && ! $files->exists('pubspec.lock') && ! $files->exists('.metadata')) {
            $issues->add(new Issue(
                code: IssueCode::DD_FLUTTER_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Flutter or Dart project detected',
                module: ModuleName::FLUTTER,
            ));

            return $issues;
        }

        $pubspec = $files->contents('pubspec.yaml');

        $this->checkLockfile($issues, $files, $pubspec, $options);
        $this->checkSdkConstraint($issues, $files, $pubspec);
        $this->checkDependencySources($issues, $files, $pubspec);
        $this->checkPlatformMarkers($issues, $files, $pubspec);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_FLUTTER_READY,
                severity: Severity::INFO,
                message: 'Flutter and Dart diagnostics found no actionable issues.',
                module: ModuleName::FLUTTER,
            ));
        }

        return $issues;
    }

    private function checkLockfile(IssueCollection $issues, ProjectFiles $files, string $pubspec, FlutterOptions $options): void
    {
        if ($pubspec === '' || $files->exists('pubspec.lock') || ! str_contains($pubspec, 'dependencies:')) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_FLUTTER_LOCK_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'pubspec.yaml declares dependencies but pubspec.lock was not found',
            module: ModuleName::FLUTTER,
            file: 'pubspec.yaml',
            key: 'pubspec.lock',
        ));
    }

    private function checkSdkConstraint(IssueCollection $issues, ProjectFiles $files, string $pubspec): void
    {
        if ($pubspec === '' || preg_match('/^\s*sdk\s*:\s*["\']?[^"\'\n]+["\']?\s*$/m', $pubspec) === 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_FLUTTER_SDK_CONSTRAINT_MISSING,
            severity: Severity::WARNING,
            message: 'pubspec.yaml does not declare a Dart SDK constraint',
            module: ModuleName::FLUTTER,
            file: 'pubspec.yaml',
        ));
    }

    private function checkDependencySources(IssueCollection $issues, ProjectFiles $files, string $pubspec): void
    {
        foreach (explode("\n", $pubspec) as $lineNumber => $line) {
            if (preg_match('/^\s*(git|path)\s*:\s*.+$/i', $line) !== 1) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_FLUTTER_DEPENDENCY_SOURCE,
                severity: Severity::INFO,
                message: 'pubspec.yaml uses a local path or Git dependency source',
                module: ModuleName::FLUTTER,
                file: 'pubspec.yaml',
                line: $lineNumber + 1,
            ));
        }
    }

    private function checkPlatformMarkers(IssueCollection $issues, ProjectFiles $files, string $pubspec): void
    {
        $isFlutter = $files->exists('.metadata') || str_contains($pubspec, 'sdk: flutter');

        if (! $isFlutter || $files->exists('android/app/build.gradle') || $files->exists('android/app/build.gradle.kts') || $this->hasIosProject($files)) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_FLUTTER_PLATFORM_MARKERS_MISSING,
            severity: Severity::INFO,
            message: 'Flutter project metadata exists but Android and iOS platform markers were not found',
            module: ModuleName::FLUTTER,
            file: 'pubspec.yaml',
        ));
    }

    private function hasIosProject(ProjectFiles $files): bool
    {
        return $files->exists('ios/Runner.xcodeproj/project.pbxproj')
            || $files->glob('ios/*.xcodeproj/project.pbxproj') !== [];
    }
}
