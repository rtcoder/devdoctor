<?php

use DevDoctor\Core\IssueCode;
use DevDoctor\Modules\Database\DatabaseAnalyzer;
use DevDoctor\Modules\Database\DatabaseOptions;

it('reports ready for sqlite memory configuration', function () {
    $path = sys_get_temp_dir().'/devdoctor-db-ready-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "DB_CONNECTION=sqlite\nDB_DATABASE=:memory:\n");

    $issues = (new DatabaseAnalyzer)->analyze(new DatabaseOptions($path));

    expect($issues->all())->toHaveCount(1)
        ->and($issues->all()[0]->code)->toBe(IssueCode::DD_DB_READY);
});

it('reports missing database keys for server connections', function () {
    $path = sys_get_temp_dir().'/devdoctor-db-missing-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\n");

    $codes = array_map(
        static fn ($issue) => $issue->code,
        (new DatabaseAnalyzer)->analyze(new DatabaseOptions($path))->all(),
    );

    expect($codes)->toContain(IssueCode::DD_DB_REQUIRED_KEY_MISSING);
});

it('reports invalid database port', function () {
    $path = sys_get_temp_dir().'/devdoctor-db-port-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=70000\nDB_DATABASE=app\nDB_USERNAME=root\n");

    $issues = (new DatabaseAnalyzer)->analyze(new DatabaseOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_DB_PORT_INVALID);
});

it('does not create sqlite files during static analysis', function () {
    $path = sys_get_temp_dir().'/devdoctor-db-sqlite-'.bin2hex(random_bytes(4));
    mkdir($path);
    file_put_contents($path.'/.env', "DB_CONNECTION=sqlite\nDB_DATABASE=database/database.sqlite\n");

    $issues = (new DatabaseAnalyzer)->analyze(new DatabaseOptions($path))->all();

    expect($issues[0]->code)->toBe(IssueCode::DD_DB_SQLITE_FILE_MISSING)
        ->and(is_file($path.'/database/database.sqlite'))->toBeFalse();
});
