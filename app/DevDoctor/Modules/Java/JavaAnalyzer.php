<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Java;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class JavaAnalyzer
{
    public function analyze(JavaOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $systems = $this->buildSystems($files);

        if ($systems === []) {
            $issues->add(new Issue(
                code: IssueCode::DD_JAVA_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Java/JVM build files detected',
                module: ModuleName::JAVA,
            ));

            return $issues;
        }

        $this->checkMixedBuildSystems($issues, $systems);
        $this->checkWrappers($issues, $files, $systems);
        $this->checkJavaVersions($issues, $files);
        $this->checkRiskyBuildScripts($issues, $files);
        $this->checkSpringRedFlags($issues, $files);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_JAVA_READY,
                severity: Severity::INFO,
                message: 'Java diagnostics found no actionable issues.',
                module: ModuleName::JAVA,
            ));
        }

        return $issues;
    }

    /**
     * @return array<string, string>
     */
    private function buildSystems(ProjectFiles $files): array
    {
        $systems = [];

        if ($files->exists('pom.xml')) {
            $systems['maven'] = 'pom.xml';
        }

        $gradle = $files->firstExisting(['build.gradle', 'build.gradle.kts', 'settings.gradle', 'settings.gradle.kts']);

        if ($gradle !== null) {
            $systems['gradle'] = $gradle;
        }

        if ($files->exists('build.xml')) {
            $systems['ant'] = 'build.xml';
        }

        return $systems;
    }

    /**
     * @param  array<string, string>  $systems
     */
    private function checkMixedBuildSystems(IssueCollection $issues, array $systems): void
    {
        if (count($systems) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_JAVA_MIXED_BUILD_SYSTEMS,
            severity: Severity::WARNING,
            message: 'Multiple Java build systems detected: '.implode(', ', array_keys($systems)),
            module: ModuleName::JAVA,
            file: reset($systems) ?: null,
            context: ['build_systems' => array_keys($systems)],
        ));
    }

    /**
     * @param  array<string, string>  $systems
     */
    private function checkWrappers(IssueCollection $issues, ProjectFiles $files, array $systems): void
    {
        if (array_key_exists('maven', $systems) && ! $files->exists('mvnw') && ! $files->exists('mvnw.cmd')) {
            $issues->add($this->missingWrapper('Maven wrapper is missing', 'pom.xml', 'mvnw'));
        }

        if (array_key_exists('gradle', $systems) && ! $files->exists('gradlew') && ! $files->exists('gradlew.bat')) {
            $issues->add($this->missingWrapper('Gradle wrapper is missing', $systems['gradle'], 'gradlew'));
        }
    }

    private function missingWrapper(string $message, string $file, string $key): Issue
    {
        return new Issue(
            code: IssueCode::DD_JAVA_WRAPPER_MISSING,
            severity: Severity::WARNING,
            message: $message,
            module: ModuleName::JAVA,
            file: $file,
            key: $key,
        );
    }

    private function checkJavaVersions(IssueCollection $issues, ProjectFiles $files): void
    {
        $versions = array_filter([
            'pom.xml' => $this->mavenJavaVersion($files->contents('pom.xml')),
            'build.gradle' => $this->gradleJavaVersion($files->contents('build.gradle')),
            'build.gradle.kts' => $this->gradleJavaVersion($files->contents('build.gradle.kts')),
        ]);

        if (count(array_unique($versions)) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_JAVA_VERSION_MISMATCH,
            severity: Severity::WARNING,
            message: 'Java version declarations disagree across build files',
            module: ModuleName::JAVA,
            file: array_key_first($versions),
            context: ['versions' => $versions],
        ));
    }

    private function mavenJavaVersion(string $contents): ?string
    {
        foreach ([
            '/<maven\.compiler\.release>\s*([^<]+)\s*<\/maven\.compiler\.release>/',
            '/<maven\.compiler\.target>\s*([^<]+)\s*<\/maven\.compiler\.target>/',
            '/<java\.version>\s*([^<]+)\s*<\/java\.version>/',
        ] as $pattern) {
            if (preg_match($pattern, $contents, $match) === 1) {
                return trim($match[1]);
            }
        }

        return null;
    }

    private function gradleJavaVersion(string $contents): ?string
    {
        foreach ([
            '/sourceCompatibility\s*=\s*(?:JavaVersion\.VERSION_)?["\']?([0-9_]+)["\']?/',
            '/targetCompatibility\s*=\s*(?:JavaVersion\.VERSION_)?["\']?([0-9_]+)["\']?/',
            '/languageVersion\s*=\s*JavaLanguageVersion\.of\((\d+)\)/',
        ] as $pattern) {
            if (preg_match($pattern, $contents, $match) === 1) {
                return str_replace('_', '.', trim($match[1]));
            }
        }

        return null;
    }

    private function checkRiskyBuildScripts(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (['pom.xml', 'build.gradle', 'build.gradle.kts', 'build.xml'] as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/curl\s+.*\|\s*(sh|bash)|wget\s+.*\|\s*(sh|bash)|\b(bash|sh)\s+-c\b|\bexec\s*\{|\bexec\s*\(/i', $line) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_JAVA_RISKY_BUILD_SCRIPT,
                        severity: Severity::WARNING,
                        message: 'Java build file contains shell execution that should be reviewed',
                        module: ModuleName::JAVA,
                        file: $file,
                        line: $lineNumber + 1,
                    ));
                }
            }
        }
    }

    private function checkSpringRedFlags(IssueCollection $issues, ProjectFiles $files): void
    {
        if (! $this->looksLikeSpring($files)) {
            return;
        }

        foreach ([
            'src/main/resources/application.properties',
            'src/main/resources/application-prod.properties',
            'src/main/resources/application.yml',
            'src/main/resources/application-prod.yml',
        ] as $file) {
            $contents = $files->contents($file);

            if ($contents === '') {
                continue;
            }

            if (
                preg_match('/spring\.profiles\.active\s*[:=]\s*prod/i', $contents) === 1
                && (preg_match('/debug\s*[:=]\s*true/i', $contents) === 1 || preg_match('/logging\.level\.root\s*[:=]\s*DEBUG/i', $contents) === 1)
            ) {
                $issues->add(new Issue(
                    code: IssueCode::DD_JAVA_SPRING_PROD_DEBUG,
                    severity: Severity::WARNING,
                    message: 'Spring production profile appears to enable debug logging',
                    module: ModuleName::JAVA,
                    file: $file,
                ));
            }
        }
    }

    private function looksLikeSpring(ProjectFiles $files): bool
    {
        return $files->contains('pom.xml', 'spring-boot')
            || $files->contains('build.gradle', 'spring-boot')
            || $files->contains('build.gradle.kts', 'spring-boot');
    }
}
