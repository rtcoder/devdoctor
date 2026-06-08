<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Kube;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\ProjectFiles;
use DevDoctor\Core\Severity;

final readonly class KubeAnalyzer
{
    /**
     * @var list<string>
     */
    private const array VALUES_FILES = [
        'values.yaml',
        'values.yml',
        'values.local.yaml',
        'values.local.yml',
        'values.production.yaml',
        'values.production.yml',
    ];

    public function analyze(KubeOptions $options): IssueCollection
    {
        $files = new ProjectFiles($options->path);
        $issues = new IssueCollection;
        $manifestFiles = $this->manifestFiles($files);
        $valuesFiles = $files->existing(self::VALUES_FILES);
        $chartFile = $files->firstExisting(['Chart.yaml', 'Chart.yml']);
        $helmEvidence = $chartFile ?? $files->firstExisting(['helmfile.yaml', 'helmfile.yml', ...self::VALUES_FILES]);

        if ($manifestFiles === [] && $helmEvidence === null && ! $files->exists('kustomization.yaml') && ! $files->exists('kustomization.yml')) {
            $issues->add(new Issue(
                code: IssueCode::DD_KUBE_NOT_PROJECT,
                severity: Severity::INFO,
                message: 'No Kubernetes or Helm project detected',
                module: ModuleName::KUBE,
            ));

            return $issues;
        }

        $this->checkHelmLock($issues, $files, $chartFile, $options);
        $this->checkValuesSecrets($issues, $files, $valuesFiles);
        $this->checkManifests($issues, $files, $manifestFiles);

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_KUBE_READY,
                severity: Severity::INFO,
                message: 'Kubernetes and Helm diagnostics found no actionable issues.',
                module: ModuleName::KUBE,
            ));
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function manifestFiles(ProjectFiles $files): array
    {
        $candidates = [
            ...$files->glob('*.yaml'),
            ...$files->glob('*.yml'),
            ...$files->glob('k8s/*.yaml'),
            ...$files->glob('k8s/*.yml'),
            ...$files->glob('manifests/*.yaml'),
            ...$files->glob('manifests/*.yml'),
        ];

        return array_values(array_filter(array_unique($candidates), function (string $file) use ($files): bool {
            $contents = $files->contents($file);

            return preg_match('/^\s*apiVersion\s*:/m', $contents) === 1
                && preg_match('/^\s*kind\s*:/m', $contents) === 1;
        }));
    }

    private function checkHelmLock(IssueCollection $issues, ProjectFiles $files, ?string $chartFile, KubeOptions $options): void
    {
        if ($chartFile === null || $files->exists('Chart.lock') || ! $files->contains($chartFile, 'dependencies:')) {
            return;
        }

        $issues->add(new Issue(
            code: IssueCode::DD_KUBE_HELM_LOCK_MISSING,
            severity: $options->strict ? Severity::ERROR : Severity::WARNING,
            message: 'Helm chart declares dependencies but Chart.lock was not found',
            module: ModuleName::KUBE,
            file: $chartFile,
            key: 'Chart.lock',
        ));
    }

    /**
     * @param  list<string>  $valuesFiles
     */
    private function checkValuesSecrets(IssueCollection $issues, ProjectFiles $files, array $valuesFiles): void
    {
        foreach ($valuesFiles as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                if (preg_match('/^\s*[\w.-]*(password|secret|token|api[_-]?key)[\w.-]*\s*:\s*(?!["\']?(?:changeme|example|placeholder|dummy|false|true|null)?["\']?\s*$).+/i', $line) !== 1) {
                    continue;
                }

                $issues->add(new Issue(
                    code: IssueCode::DD_KUBE_VALUES_SECRET,
                    severity: Severity::WARNING,
                    message: 'Helm values file appears to contain a literal secret',
                    module: ModuleName::KUBE,
                    file: $file,
                    line: $lineNumber + 1,
                ));
            }
        }
    }

    /**
     * @param  list<string>  $manifestFiles
     */
    private function checkManifests(IssueCollection $issues, ProjectFiles $files, array $manifestFiles): void
    {
        foreach ($manifestFiles as $file) {
            foreach (explode("\n", $files->contents($file)) as $lineNumber => $line) {
                $this->checkManifestLine($issues, $file, $line, $lineNumber + 1);
            }
        }
    }

    private function checkManifestLine(IssueCollection $issues, string $file, string $line, int $lineNumber): void
    {
        if (preg_match('/^\s*privileged\s*:\s*true\s*$/i', $line) === 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_KUBE_PRIVILEGED_CONTAINER,
                severity: Severity::WARNING,
                message: 'Kubernetes manifest enables a privileged container',
                module: ModuleName::KUBE,
                file: $file,
                line: $lineNumber,
            ));
        }

        if (preg_match('/^\s*-?\s*hostPath\s*:\s*$/i', $line) === 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_KUBE_HOST_PATH_MOUNT,
                severity: Severity::WARNING,
                message: 'Kubernetes manifest mounts a hostPath volume',
                module: ModuleName::KUBE,
                file: $file,
                line: $lineNumber,
            ));
        }

        if (preg_match('/^\s*-?\s*image\s*:\s*["\']?([^"\'\s]+)["\']?\s*$/i', $line, $matches) === 1 && $this->isMutableImage((string) $matches[1])) {
            $issues->add(new Issue(
                code: IssueCode::DD_KUBE_MUTABLE_IMAGE_TAG,
                severity: Severity::WARNING,
                message: 'Kubernetes manifest uses a mutable or implicit image tag',
                module: ModuleName::KUBE,
                file: $file,
                line: $lineNumber,
            ));
        }

        if (preg_match('/^\s*type\s*:\s*NodePort\s*$/i', $line) === 1) {
            $issues->add(new Issue(
                code: IssueCode::DD_KUBE_NODEPORT_SERVICE,
                severity: Severity::INFO,
                message: 'Kubernetes service exposes a NodePort',
                module: ModuleName::KUBE,
                file: $file,
                line: $lineNumber,
            ));
        }
    }

    private function isMutableImage(string $image): bool
    {
        if (str_ends_with($image, ':latest')) {
            return true;
        }

        $lastSlash = strrpos($image, '/');
        $lastColon = strrpos($image, ':');

        return $lastColon === false || ($lastSlash !== false && $lastColon < $lastSlash);
    }
}
