<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Cpp;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class CppAnalyzer
{
    public function analyze(CppOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;

        if (! $this->isCppProject($files)) {
            $issues->add(new Issue(
                code: IssueCode::DD_CPP_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No C/C++ build files detected',
                module: ModuleName::CPP,
            ));

            return $issues;
        }

        $this->checkDependencyManagers($issues, $files);
        $this->checkCompileCommands($issues, $files);
        $this->checkBuildDirHygiene($issues, $files);
        $this->checkRiskyCompilerFlags($issues, $files);
        $this->checkGeneratorHints($issues, $files);
        $this->checkShellAssumptions($issues, $files);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_CPP_READY,
                severity: Severity::INFO,
                message: 'C/C++ diagnostics found no actionable issues.',
                module: ModuleName::CPP,
            ));
        }

        return $issues;
    }

    private function isCppProject(ProjectFiles $files): bool
    {
        return $files->firstExisting([
            'CMakeLists.txt',
            'Makefile',
            'meson.build',
            'configure.ac',
            'vcpkg.json',
            'conanfile.txt',
            'conanfile.py',
        ]) !== null;
    }

    private function checkDependencyManagers(IssueCollection $issues, ProjectFiles $files): void
    {
        $managers = [];

        if ($files->exists('vcpkg.json')) {
            $managers[] = 'vcpkg';
        }

        if ($files->exists('conanfile.txt') || $files->exists('conanfile.py')) {
            $managers[] = 'conan';
        }

        if (count($managers) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_CPP_MIXED_DEPENDENCY_MANAGERS,
            severity: Severity::WARNING,
            message: 'Multiple C/C++ dependency managers detected',
            module: ModuleName::CPP,
            file: 'vcpkg.json',
            context: ['managers' => $managers],
        ));
    }

    private function checkCompileCommands(IssueCollection $issues, ProjectFiles $files): void
    {
        if ($files->exists('CMakeLists.txt') && ! $files->exists('compile_commands.json')) {
            $issues->add(new Issue(
                code: IssueCode::DD_CPP_COMPILE_COMMANDS_MISSING,
                severity: Severity::INFO,
                message: 'CMake project does not expose compile_commands.json',
                module: ModuleName::CPP,
                file: 'CMakeLists.txt',
                key: 'compile_commands.json',
            ));
        }
    }

    private function checkBuildDirHygiene(IssueCollection $issues, ProjectFiles $files): void
    {
        if ($files->exists('CMakeCache.txt')) {
            $issues->add(new Issue(
                code: IssueCode::DD_CPP_IN_SOURCE_BUILD,
                severity: Severity::WARNING,
                message: 'CMake cache appears in the source root',
                module: ModuleName::CPP,
                file: 'CMakeCache.txt',
            ));
        }
    }

    private function checkRiskyCompilerFlags(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach ($this->buildFiles() as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/(^|[\s(])(-w|-fpermissive|-Wno-everything|-fno-stack-protector)([\s)]|$)/', $line) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_CPP_RISKY_COMPILER_FLAGS,
                        severity: Severity::WARNING,
                        message: 'C/C++ build file contains compiler flags that can hide important diagnostics',
                        module: ModuleName::CPP,
                        file: $file,
                        line: $lineNumber + 1,
                    ));
                }
            }
        }
    }

    private function checkGeneratorHints(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (explode("\n", $files->contents('CMakeLists.txt')) as $lineNumber => $line) {
            if (preg_match('/Unix Makefiles|Visual Studio|Ninja/i', $line) === 1) {
                $issues->add(new Issue(
                    code: IssueCode::DD_CPP_GENERATOR_ASSUMPTION,
                    severity: Severity::WARNING,
                    message: 'CMake file appears to hard-code a generator assumption',
                    module: ModuleName::CPP,
                    file: 'CMakeLists.txt',
                    line: $lineNumber + 1,
                ));
            }
        }
    }

    private function checkShellAssumptions(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach ($this->buildFiles() as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/\/bin\/bash|\bbash\s+-c\b|\brm\s+-rf\b|\bcp\s+-R\b/', $line) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_CPP_SHELL_ASSUMPTION,
                        severity: Severity::WARNING,
                        message: 'C/C++ build file contains a Unix shell assumption',
                        module: ModuleName::CPP,
                        file: $file,
                        line: $lineNumber + 1,
                    ));
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function buildFiles(): array
    {
        return ['CMakeLists.txt', 'Makefile', 'meson.build', 'configure.ac', 'conanfile.py'];
    }
}
