<?php

it('ships valid JSON schema and Box configuration', function () {
    $root = dirname(__DIR__, 2);
    $schema = json_decode((string) file_get_contents($root.'/schemas/devdoctor-output.schema.json'), true, flags: JSON_THROW_ON_ERROR);
    $box = json_decode((string) file_get_contents($root.'/box.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['title'])->toBe('DevDoctor JSON Output')
        ->and($box['directories'])->toContain('app');
});

it('documents every issue code used by the application', function () {
    $root = dirname(__DIR__, 2);
    $documented = (string) file_get_contents($root.'/docs/issue-codes.md');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
    $codes = [];

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all('/DD_[A-Z0-9_]+/', (string) file_get_contents($file->getPathname()), $matches);
        $codes = array_merge($codes, $matches[0]);
    }

    foreach (array_unique($codes) as $code) {
        expect($documented)->toContain($code);
    }
});
