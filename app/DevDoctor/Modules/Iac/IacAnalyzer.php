<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Iac;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class IacAnalyzer
{
    public function analyze(IacOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $terraformFiles = $this->terraformFiles($files);

        if ($terraformFiles === [] && ! $files->exists('terragrunt.hcl') && ! $files->exists('.terraform.lock.hcl') && ! $files->exists('tofu.lock.hcl')) {
            $issues->add(new Issue(
                code: IssueCode::DD_IAC_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Terraform, OpenTofu, or Terragrunt project detected',
                module: ModuleName::IAC,
            ));

            return $issues;
        }

        $this->checkLockfile($issues, $files, $terraformFiles, $options);
        $this->checkTerraformFiles($issues, $files, $terraformFiles);
        $this->checkTerragrunt($issues, $files);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_IAC_READY,
                severity: Severity::INFO,
                message: 'IaC diagnostics found no actionable issues.',
                module: ModuleName::IAC,
            ));
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function terraformFiles(ProjectFiles $files): array
    {
        return array_values(array_unique([...$files->glob('*.tf'), ...$files->glob('*.tfvars')]));
    }

    /**
     * @param  list<string>  $terraformFiles
     */
    private function checkLockfile(IssueCollection $issues, ProjectFiles $files, array $terraformFiles, IacOptions $options): void
    {
        if ($terraformFiles === [] || $files->exists('.terraform.lock.hcl') || $files->exists('tofu.lock.hcl')) {
            return;
        }

        $providerDeclared = false;

        foreach ($terraformFiles as $file) {
            $providerDeclared = $providerDeclared || str_contains($files->contents($file), 'required_providers');
        }

        if (! $providerDeclared) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_IAC_LOCK_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'Provider requirements are declared but no Terraform/OpenTofu lockfile was found',
            module: ModuleName::IAC,
            file: $terraformFiles[0],
            key: '.terraform.lock.hcl',
        ));
    }

    /**
     * @param  list<string>  $terraformFiles
     */
    private function checkTerraformFiles(IssueCollection $issues, ProjectFiles $files, array $terraformFiles): void
    {
        foreach ($terraformFiles as $file) {
            $contents = $files->contents($file);

            foreach (explode("\n", $contents) as $lineNumber => $line) {
                $this->checkLine($issues, $file, $line, $lineNumber + 1);
            }

            $this->checkRemoteModuleSources($issues, $file, $contents, 'Remote Terraform module source appears to be unpinned');
        }
    }

    private function checkLine(IssueCollection $issues, string $file, string $line, int $lineNumber): void
    {
        if (preg_match('/^\s*(access_key|secret_key|token)\s*=\s*["\'][^"\']+["\']/i', $line) === 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_IAC_BACKEND_SECRET,
                severity: Severity::WARNING,
                message: 'Terraform backend or provider config appears to contain a literal secret',
                module: ModuleName::IAC,
                file: $file,
                line: $lineNumber,
            ));
        }

        if (preg_match('/^\s*default\s*=\s*["\'][^"\']+["\']/i', $line) === 1 && preg_match('/(secret|token|password|key)/i', $line) === 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_IAC_SECRET_DEFAULT,
                severity: Severity::WARNING,
                message: 'Terraform variable default appears to contain a secret-like value',
                module: ModuleName::IAC,
                file: $file,
                line: $lineNumber,
            ));
        }

        if (preg_match('/version\s*=\s*["\'](?:\*|>=\s*0|>=\s*[0-9.]+)["\']/i', $line) === 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_IAC_WILDCARD_PROVIDER_VERSION,
                severity: Severity::WARNING,
                message: 'Terraform provider version constraint is too broad',
                module: ModuleName::IAC,
                file: $file,
                line: $lineNumber,
            ));
        }
    }

    private function checkTerragrunt(IssueCollection $issues, ProjectFiles $files): void
    {
        if (! $files->exists('terragrunt.hcl')) {
            return;
        }

        foreach (explode("\n", $files->contents('terragrunt.hcl')) as $lineNumber => $line) {
            if ($this->isUnpinnedRemoteSource($line)) {
                $issues->add(new Issue(
                    code: IssueCode::DD_IAC_REMOTE_MODULE_UNPINNED,
                    severity: Severity::WARNING,
                    message: 'Terragrunt source appears to reference an unpinned remote module',
                    module: ModuleName::IAC,
                    file: 'terragrunt.hcl',
                    line: $lineNumber + 1,
                ));
            }
        }
    }

    private function checkRemoteModuleSources(IssueCollection $issues, string $file, string $contents, string $message): void
    {
        foreach (explode("\n", $contents) as $lineNumber => $line) {
            if (! $this->isUnpinnedRemoteSource($line)) {
                continue;
            }

            $issues->add(new Issue(
                code: IssueCode::DD_IAC_REMOTE_MODULE_UNPINNED,
                severity: Severity::WARNING,
                message: $message,
                module: ModuleName::IAC,
                file: $file,
                line: $lineNumber + 1,
            ));
        }
    }

    private function isUnpinnedRemoteSource(string $line): bool
    {
        return preg_match('/source\s*=\s*["\']git::[^"\']+["\']/i', $line) === 1
            && ! str_contains($line, 'ref=');
    }
}
