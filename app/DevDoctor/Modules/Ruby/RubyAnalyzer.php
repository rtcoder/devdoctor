<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ruby;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class RubyAnalyzer
{
    public function analyze(RubyOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;

        if (! $this->isRubyProject($files)) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUBY_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Ruby or Rails project detected',
                module: ModuleName::RUBY,
            ));

            return $issues;
        }

        $this->checkLockfile($issues, $files, $options);
        $this->checkVersionConflicts($issues, $files);
        $this->checkRiskyGemSources($issues, $files);
        $this->checkRailsCredentials($issues, $files);
        $this->checkDatabaseSecrets($issues, $files);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUBY_READY,
                severity: Severity::INFO,
                message: 'Ruby diagnostics found no actionable issues.',
                module: ModuleName::RUBY,
            ));
        }

        return $issues;
    }

    private function isRubyProject(ProjectFiles $files): bool
    {
        return $files->firstExisting(['Gemfile', 'gems.rb', '.ruby-version', 'config/application.rb', 'bin/rails']) !== null;
    }

    private function checkLockfile(IssueCollection $issues, ProjectFiles $files, RubyOptions $options): void
    {
        if (! $files->exists('Gemfile') || $files->exists('Gemfile.lock')) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_RUBY_LOCK_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'Gemfile is present but Gemfile.lock is missing',
            module: ModuleName::RUBY,
            file: 'Gemfile',
            key: 'Gemfile.lock',
        ));
    }

    private function checkVersionConflicts(IssueCollection $issues, ProjectFiles $files): void
    {
        $versions = [];

        if (preg_match('/^\s*ruby\s+["\']([^"\']+)["\']/m', $files->contents('Gemfile'), $match) === 1) {
            $versions['Gemfile'] = trim($match[1]);
        }

        $rubyVersion = trim($files->contents('.ruby-version'));

        if ($rubyVersion !== '') {
            $versions['.ruby-version'] = $rubyVersion;
        }

        if (count(array_unique($versions)) <= 1) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_RUBY_VERSION_CONFLICT,
            severity: Severity::WARNING,
            message: 'Ruby version declarations disagree across project files',
            module: ModuleName::RUBY,
            file: array_key_first($versions),
            context: ['versions' => $versions],
        ));
    }

    private function checkRiskyGemSources(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (['Gemfile', 'gems.rb'] as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/source\s+["\']http:|git:\s*["\']https?:|path:\s*["\']/i', $line) === 1) {
                    $issues->add(new Issue(
                        code: IssueCode::DD_RUBY_RISKY_GEM_SOURCE,
                        severity: Severity::WARNING,
                        message: 'Ruby dependency source should be reviewed before install',
                        module: ModuleName::RUBY,
                        file: $file,
                        line: $lineNumber + 1,
                    ));
                }
            }
        }
    }

    private function checkRailsCredentials(IssueCollection $issues, ProjectFiles $files): void
    {
        if (! $this->isRailsProject($files) || $files->exists('config/master.key')) {
            return;
        }

        if ($files->exists('config/credentials.yml.enc') || $files->exists('config/credentials/production.yml.enc')) {
            $issues->add(new Issue(
                code: IssueCode::DD_RUBY_RAILS_MASTER_KEY_MISSING,
                severity: Severity::WARNING,
                message: 'Rails encrypted credentials are present but no local master key file was found',
                module: ModuleName::RUBY,
                file: 'config/credentials.yml.enc',
                key: 'RAILS_MASTER_KEY',
            ));
        }
    }

    private function isRailsProject(ProjectFiles $files): bool
    {
        return $files->contains('Gemfile', 'rails') || $files->exists('config/application.rb') || $files->exists('bin/rails');
    }

    private function checkDatabaseSecrets(IssueCollection $issues, ProjectFiles $files): void
    {
        foreach (explode("\n", $files->contents('config/database.yml')) as $lineNumber => $line) {
            if (preg_match('/^\s*(password|username):\s*(?!<%=|ENV\[|$).+/i', $line) === 1) {
                $issues->add(new Issue(
                    code: IssueCode::DD_RUBY_DATABASE_SECRET,
                    severity: Severity::WARNING,
                    message: 'Rails database config appears to contain a literal credential',
                    module: ModuleName::RUBY,
                    file: 'config/database.yml',
                    line: $lineNumber + 1,
                ));
            }
        }
    }
}
