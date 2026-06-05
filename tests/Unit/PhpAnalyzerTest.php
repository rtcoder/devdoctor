<?php

use DevDoctor\Modules\Php\PhpAnalyzer;
use DevDoctor\Modules\Php\PhpOptions;
use DevDoctor\Modules\Php\PhpRuntimeInterface;
use Tests\Support\FakeCommandAvailability;

final readonly class FakePhpRuntime implements PhpRuntimeInterface
{
    /**
     * @param  list<string>  $extensions
     */
    public function __construct(
        private string $version = '8.5.0',
        private array $extensions = ['json'],
        private string|false $memoryLimit = '256M',
        private string|false $iniFile = '/tmp/php.ini',
        private bool $xdebugEnabled = false,
    ) {}

    public function version(): string
    {
        return $this->version;
    }

    public function loadedExtensions(): array
    {
        return $this->extensions;
    }

    public function iniValue(string $key): string|false
    {
        return $key === 'memory_limit' ? $this->memoryLimit : false;
    }

    public function iniFile(): string|false
    {
        return $this->iniFile;
    }

    public function xdebugEnabled(): bool
    {
        return $this->xdebugEnabled;
    }
}

function phpFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-php-'.bin2hex(random_bytes(4));
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

it('reports ready php runtimes', function () {
    $issues = (new PhpAnalyzer(runtime: new FakePhpRuntime))->analyze(new PhpOptions(path: phpFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_PHP_READY');
});

it('reports php version mismatch and missing extensions from composer json', function () {
    $issues = (new PhpAnalyzer(runtime: new FakePhpRuntime(version: '8.4.0', extensions: ['json'])))->analyze(new PhpOptions(path: phpFixture([
        'composer.json' => '{"require":{"php":"^8.5","ext-curl":"*"}}',
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_PHP_VERSION_MISMATCH')
        ->and($codes)->toContain('DD_PHP_EXTENSION_MISSING');
});

it('reports invalid composer json for php diagnostics', function () {
    $issues = (new PhpAnalyzer(runtime: new FakePhpRuntime))->analyze(new PhpOptions(path: phpFixture([
        'composer.json' => '{',
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_PHP_COMPOSER_JSON_INVALID');
});

it('reports low memory limit and missing php ini', function () {
    $issues = (new PhpAnalyzer(runtime: new FakePhpRuntime(memoryLimit: '64M', iniFile: false)))->analyze(new PhpOptions(path: phpFixture([])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_PHP_MEMORY_LIMIT_LOW')
        ->and($codes)->toContain('DD_PHP_INI_MISSING');
});

it('reports xdebug in ci mode', function () {
    $issues = (new PhpAnalyzer(runtime: new FakePhpRuntime(xdebugEnabled: true)))->analyze(new PhpOptions(
        path: phpFixture([]),
        ci: true,
    ));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_PHP_XDEBUG_ENABLED_IN_CI');
});

it('reports missing php binary', function () {
    $issues = (new PhpAnalyzer(
        runtime: new FakePhpRuntime,
        commands: new FakeCommandAvailability,
    ))->analyze(new PhpOptions(path: phpFixture([])));

    expect(array_map(static fn ($issue): string => $issue->code->value, $issues->all()))
        ->toContain('DD_PHP_BINARY_MISSING');
});
