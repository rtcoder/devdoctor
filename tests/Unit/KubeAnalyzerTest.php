<?php

use DevDoctor\Modules\Kube\KubeAnalyzer;
use DevDoctor\Modules\Kube\KubeOptions;

function kubeFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-kube-'.bin2hex(random_bytes(4));
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

it('reports non kube projects as info', function () {
    $issues = (new KubeAnalyzer)->analyze(new KubeOptions(path: kubeFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_KUBE_NOT_PROJECT');
});

it('reports ready kubernetes manifests', function () {
    $issues = (new KubeAnalyzer)->analyze(new KubeOptions(path: kubeFixture([
        'k8s/deployment.yaml' => "apiVersion: apps/v1\nkind: Deployment\nspec:\n  template:\n    spec:\n      containers:\n        - image: ghcr.io/example/app:1.2.3\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_KUBE_READY');
});

it('reports missing helm lock files', function () {
    $issues = (new KubeAnalyzer)->analyze(new KubeOptions(path: kubeFixture([
        'Chart.yaml' => "apiVersion: v2\nname: app\ndependencies:\n  - name: redis\n    version: 1.0.0\n    repository: https://charts.example.test\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_KUBE_HELM_LOCK_MISSING');
});

it('reports values secrets and risky manifest settings', function () {
    $issues = (new KubeAnalyzer)->analyze(new KubeOptions(path: kubeFixture([
        'values.yaml' => "databasePassword: super-secret\n",
        'manifests/app.yaml' => "apiVersion: apps/v1\nkind: Deployment\nspec:\n  template:\n    spec:\n      containers:\n        - image: app:latest\n          securityContext:\n            privileged: true\n      volumes:\n        - hostPath:\n            path: /var/run/docker.sock\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_KUBE_VALUES_SECRET')
        ->and($codes)->toContain('DD_KUBE_MUTABLE_IMAGE_TAG')
        ->and($codes)->toContain('DD_KUBE_PRIVILEGED_CONTAINER')
        ->and($codes)->toContain('DD_KUBE_HOST_PATH_MOUNT');
});

it('reports nodeport services as informational', function () {
    $issues = (new KubeAnalyzer)->analyze(new KubeOptions(path: kubeFixture([
        'service.yaml' => "apiVersion: v1\nkind: Service\nspec:\n  type: NodePort\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_KUBE_NODEPORT_SERVICE');
});
