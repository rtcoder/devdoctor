<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checkOnly = in_array('--check', $argv, true);

/**
 * @param  array<string, mixed>  $entry
 */
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function moduleId(string $module): string
{
    return 'module-'.preg_replace('/[^a-z0-9]+/', '-', strtolower($module));
}

function codeId(string $code): string
{
    return strtolower(str_replace('_', '-', $code));
}

/**
 * @param  array<string, mixed>  $data
 */
function prettyJson(array $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
}

/**
 * @return array<string, mixed>
 */
function docsManifest(): array
{
    return [
        'schema_version' => '1.0',
        'site' => [
            'title' => 'DevDoctor Documentation',
            'home' => 'index.html',
        ],
        'pages' => [
            ['title' => 'Install', 'path' => 'installation.html', 'section' => 'Getting started'],
            ['title' => 'Commands', 'path' => 'commands.html', 'section' => 'Reference'],
            ['title' => 'Config', 'path' => 'config.html', 'section' => 'Reference'],
            ['title' => 'Scenarios', 'path' => 'scenarios.html', 'section' => 'Guides'],
            ['title' => 'Issue Codes', 'path' => 'issue-codes.html', 'section' => 'Public contract'],
            ['title' => 'Outputs', 'path' => 'output-formats.html', 'section' => 'Automation'],
            ['title' => 'Baseline', 'path' => 'baseline.html', 'section' => 'Automation'],
            ['title' => 'CI', 'path' => 'ci.html', 'section' => 'Automation'],
            ['title' => 'Safety', 'path' => 'safety.html', 'section' => 'Trust'],
            ['title' => 'Contracts', 'path' => 'contracts.html', 'section' => 'Trust'],
            ['title' => 'Release Verification', 'path' => 'release-verification.html', 'section' => 'Trust'],
            ['title' => 'Changelog', 'path' => 'changelog.html', 'section' => 'History'],
        ],
        'machine_readable' => [
            'commands' => 'commands.json',
            'issue_codes' => '../schemas/v1/issue-codes.json',
            'json_schema' => '../schemas/v1/devdoctor-output.schema.json',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function commandCatalog(): array
{
    return [
        'schema_version' => '1.0',
        'commands' => [
            ['name' => 'env', 'module' => 'env', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check dotenv files and configured env rules.', 'example' => 'php devdoctor env --strict'],
            ['name' => 'cache', 'module' => 'cache', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check framework and tool cache directories, size, permissions, and Laravel artifacts.', 'example' => 'php devdoctor cache --max-size=512'],
            ['name' => 'http', 'module' => 'http', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check configured HTTP URLs without making network requests.', 'example' => 'php devdoctor http --url=https://example.test'],
            ['name' => 'ports', 'module' => 'ports', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check local development port conflicts.', 'example' => 'php devdoctor ports --common'],
            ['name' => 'php', 'module' => 'php', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check PHP runtime, composer platform requirements, memory, php.ini, and Xdebug in CI.', 'example' => 'php devdoctor php --ci'],
            ['name' => 'node', 'module' => 'node', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Node.js project and package manager health.', 'example' => 'php devdoctor node'],
            ['name' => 'frontend', 'module' => 'frontend', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check frontend presets and build readiness for Vite, Next.js, Nuxt, Astro, and generic frontend projects.', 'example' => 'php devdoctor frontend'],
            ['name' => 'flutter', 'module' => 'flutter', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Flutter and Dart pubspec, lockfiles, SDK constraints, dependency sources, and platform markers.', 'example' => 'php devdoctor flutter'],
            ['name' => 'mobile', 'module' => 'mobile', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check native Android and iOS project markers, wrappers, debug flags, and lockfiles.', 'example' => 'php devdoctor mobile'],
            ['name' => 'monorepo', 'module' => 'monorepo', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check monorepo tooling, workspace lockfiles, and root package scripts.', 'example' => 'php devdoctor monorepo'],
            ['name' => 'python', 'module' => 'python', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Python manifests and dependency manager health for pip, Poetry, Pipenv, uv, and Conda.', 'example' => 'php devdoctor python'],
            ['name' => 'ruby', 'module' => 'ruby', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Ruby and Rails manifests, lockfiles, versions, credentials, database config, and gem sources.', 'example' => 'php devdoctor ruby'],
            ['name' => 'go', 'module' => 'go', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Go modules, sums, workspaces, local replace directives, toolchain directives, and vendor metadata.', 'example' => 'php devdoctor go'],
            ['name' => 'rust', 'module' => 'rust', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Cargo manifests, lockfiles, workspaces, toolchains, dependency sources, and release profiles.', 'example' => 'php devdoctor rust'],
            ['name' => 'java', 'module' => 'java', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Maven, Gradle, Ant, wrappers, Java versions, risky build scripts, and Spring profile red flags.', 'example' => 'php devdoctor java'],
            ['name' => 'iac', 'module' => 'iac', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Terraform, OpenTofu, and Terragrunt manifests, lockfiles, module refs, and secret hygiene.', 'example' => 'php devdoctor iac'],
            ['name' => 'kube', 'module' => 'kube', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Kubernetes manifests and Helm charts, locks, images, services, and secret hygiene.', 'example' => 'php devdoctor kube'],
            ['name' => 'dotnet', 'module' => 'dotnet', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check .NET solutions, projects, SDK pinning, target frameworks, lock mode, and NuGet sources.', 'example' => 'php devdoctor dotnet'],
            ['name' => 'cpp', 'module' => 'cpp', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check C/C++ build files, dependency managers, compile commands, and portability risks.', 'example' => 'php devdoctor cpp'],
            ['name' => 'web', 'module' => 'web', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check generic web entry files, asset references, public config, server hints, and port declarations.', 'example' => 'php devdoctor web'],
            ['name' => 'laravel', 'module' => 'laravel', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Laravel .env, APP_KEY, debug mode, APP_URL, runtime directories, and config cache.', 'example' => 'php devdoctor laravel'],
            ['name' => 'symfony', 'module' => 'symfony', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Symfony .env hygiene, APP_SECRET, runtime directories, recipe drift, and Composer scripts.', 'example' => 'php devdoctor symfony'],
            ['name' => 'security', 'module' => 'security', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check env examples, hard-coded secrets, risky scripts, and risky Compose settings.', 'example' => 'php devdoctor security'],
            ['name' => 'composer', 'module' => 'composer', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Composer project health without install/update.', 'example' => 'php devdoctor composer'],
            ['name' => 'deps', 'module' => 'deps', 'type' => 'aggregate', 'read_only' => true, 'summary' => 'Run Composer and Node dependency diagnostics together.', 'example' => 'php devdoctor deps --summary-only'],
            ['name' => 'db', 'module' => 'db', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check database environment configuration, with optional read-only PDO connection checks.', 'example' => 'php devdoctor db --connect'],
            ['name' => 'queue', 'module' => 'queue', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check queue environment configuration and production sync risks.', 'example' => 'php devdoctor queue --strict'],
            ['name' => 'git', 'module' => 'git', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check repository hygiene and sensitive files.', 'example' => 'php devdoctor git --require-clean'],
            ['name' => 'docker', 'module' => 'docker', 'type' => 'diagnostic', 'read_only' => true, 'summary' => 'Check Compose files, daemon state, ports, and containers.', 'example' => 'php devdoctor docker --compose-file=docker-compose.yml'],
            ['name' => 'health', 'module' => 'health', 'type' => 'aggregate', 'read_only' => true, 'summary' => 'Run a broad local project health check, with ports available through --include-ports.', 'example' => 'php devdoctor health --format=json'],
            ['name' => 'doctor', 'module' => 'health', 'type' => 'aggregate', 'read_only' => true, 'summary' => 'Alias for health with the same broad local project checks.', 'example' => 'php devdoctor doctor --summary-only'],
            ['name' => 'ci', 'module' => 'ci', 'type' => 'aggregate', 'read_only' => true, 'summary' => 'Run CI-safe diagnostics and aggregate exit codes.', 'example' => 'php devdoctor ci --modules=env,php,node,laravel,composer,db,git,docker'],
            ['name' => 'presets', 'module' => 'presets', 'type' => 'utility', 'read_only' => true, 'summary' => 'Detect supported project presets across ecosystems.', 'example' => 'php devdoctor presets --format=json'],
            ['name' => 'inventory', 'module' => 'inventory', 'type' => 'utility', 'read_only' => true, 'summary' => 'Show detected presets, available modules, and auto-selected modules.', 'example' => 'php devdoctor inventory --format=json'],
            ['name' => 'explain', 'module' => 'explain', 'type' => 'utility', 'read_only' => true, 'summary' => 'Explain issue codes and their built-in hints.', 'example' => 'php devdoctor explain DD_ENV_FILE_MISSING --format=json'],
            ['name' => 'policy', 'module' => 'policy', 'type' => 'utility', 'read_only' => true, 'summary' => 'Show DevDoctor safety and compatibility policy.', 'example' => 'php devdoctor policy --format=json'],
            ['name' => 'support-bundle', 'module' => 'support-bundle', 'type' => 'utility', 'read_only' => true, 'summary' => 'Print redacted support context without writing files.', 'example' => 'php devdoctor support-bundle'],
            ['name' => 'init', 'module' => 'config', 'type' => 'writer', 'read_only' => false, 'summary' => 'Preview and optionally write devdoctor.yml after confirmation.', 'example' => 'php devdoctor init --dry-run'],
        ],
    ];
}

/**
 * @param  array<int, array{code:string,module:string,description:string,introduced:string,status:string}>  $codes
 * @return array<string, array<int, array{code:string,module:string,description:string,introduced:string,status:string}>>
 */
function groupCodes(array $codes): array
{
    $groups = [];

    foreach ($codes as $code) {
        $groups[$code['module']][] = $code;
    }

    ksort($groups);

    return $groups;
}

function primaryNav(): string
{
    return '<nav class="primary-nav" aria-label="Primary documentation">'
        .'<a href="installation.html">Install</a>'
        .'<a href="commands.html">Commands</a>'
        .'<a href="config.html">Config</a>'
        .'<a href="scenarios.html">Scenarios</a>'
        .'<a href="issue-codes.html">Issue Codes</a>'
        .'<a href="output-formats.html">Outputs</a>'
        .'<a href="ci.html">CI</a>'
        .'<a href="safety.html">Safety</a>'
        .'<a href="changelog.html">Changelog</a>'
        .'</nav>';
}

function siteHeader(string $title, string $lead, string $eyebrow = 'DevDoctor docs'): string
{
    return '<header class="site-header">
    <div class="topbar">
        <a class="brand" href="index.html" aria-label="DevDoctor documentation home"><span class="brand-mark">DD</span><span>DevDoctor</span></a>
        '.primaryNav().'
    </div>
    <div class="hero">
        <p class="eyebrow">'.h($eyebrow).'</p>
        <h1>'.h($title).'</h1>
        <p class="lead">'.h($lead).'</p>
    </div>
</header>';
}

function siteFooter(string $middleLink = '<a href="changelog.html">Changelog</a>'): string
{
    return '<footer class="site-footer"><span>DevDoctor documentation</span><a href="index.html">Home</a>'.$middleLink.'<a href="release-verification.html">Release verification</a></footer>';
}

/**
 * @param  array<string, mixed>  $catalog
 */
function renderIssueCodesHtml(array $catalog): string
{
    /** @var array<int, array{code:string,module:string,description:string,introduced:string,status:string}> $codes */
    $codes = $catalog['codes'] ?? [];
    $groups = groupCodes($codes);
    $schemaVersion = (string) ($catalog['schema_version'] ?? '1.0');

    $moduleLinks = '';
    foreach ($groups as $module => $items) {
        $moduleLinks .= '        <a href="#'.moduleId($module).'" data-module-link="'.h($module).'"><span>'.h($module).'</span><strong>'.count($items).'</strong></a>
';
    }

    $sections = '';
    foreach ($groups as $module => $items) {
        $cards = '';

        foreach ($items as $item) {
            $cards .= '                <section class="code-card" id="'.codeId($item['code']).'" data-code-card data-code="'.h($item['code']).'" data-module="'.h($module).'" data-description="'.h($item['description']).'">
                    <div class="code-top"><code>'.h($item['code']).'</code><button class="copy-code" type="button" data-copy-code="'.h($item['code']).'">Copy</button><span class="status-pill">'.h($item['status']).'</span></div>
                    <p>'.h($item['description']).'</p>
                    <dl><div><dt>Module</dt><dd>'.h($module).'</dd></div><div><dt>Introduced</dt><dd>'.h($item['introduced']).'</dd></div></dl>
                </section>
';
        }

        $sections .= '        <article class="code-group" id="'.moduleId($module).'" data-code-group data-module="'.h($module).'">
            <div class="group-heading"><h2>'.h($module).'</h2><span>'.count($items).' codes</span></div>
            <div class="code-grid">
'.$cards.'            </div>
        </article>
';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Issue Codes - DevDoctor</title>
    <link rel="stylesheet" href="styles.css">
    <script src="docs.js" defer></script>
    <script src="issue-codes.js" defer></script>
</head>
<body>
'.siteHeader('Issue Codes', 'Stable identifiers for automation, baselines, SARIF rules, and human troubleshooting.', 'Public contract').'
<main>
    <section class="feature-band">
        <div>
            <p class="eyebrow">Catalog v'.h($schemaVersion).'</p>
            <h2>Browse by module, copy the code, automate against the identifier.</h2>
            <p>Messages can become clearer over time, but issue codes are the stable contract. Use these identifiers in CI policy, baselines, SARIF consumers, and custom dashboards.</p>
        </div>
        <div class="metric-row">
            <div class="metric"><strong>'.count($codes).'</strong><span>codes</span></div>
            <div class="metric"><strong>'.count($groups).'</strong><span>modules</span></div>
            <div class="metric"><strong>v1</strong><span>schema</span></div>
        </div>
    </section>
    <div class="catalog-layout">
    <aside class="module-index" aria-label="Issue code modules">
        <div class="module-index-heading">
            <span>Categories</span>
            <strong>'.count($groups).'</strong>
        </div>
        <label class="search-box">
            <span>Filter codes</span>
            <input type="search" id="issue-code-search" placeholder="DD_ENV, docker, lock..." autocomplete="off">
        </label>
'.$moduleLinks.'    </aside>
    <section class="code-groups" aria-live="polite">
        <p class="catalog-result-count" id="issue-code-result-count">'.count($codes).' codes shown</p>
'.$sections.'    </section>
    </div>
</main>
'.siteFooter('<a href="../schemas/v1/issue-codes.json">Machine-readable catalog</a><a href="contracts.html">Contracts</a>').'
</body>
</html>
';
}

/**
 * @param  array<string, mixed>  $catalog
 */
function renderIssueCodesMarkdown(array $catalog): string
{
    /** @var array<int, array{code:string,module:string,description:string,introduced:string,status:string}> $codes */
    $codes = $catalog['codes'] ?? [];
    $groups = groupCodes($codes);
    $markdown = "# DevDoctor Issue Codes\n\n";
    $markdown .= "Issue codes are stable identifiers intended for CI parsing, baselines, and integrations. Messages may improve over time; automation should match codes.\n\n";
    $markdown .= "The human-readable catalog is available at [`docs/issue-codes.html`](issue-codes.html). The machine-readable v1 catalog is available at [`schemas/v1/issue-codes.json`](../schemas/v1/issue-codes.json).\n\n";

    foreach ($groups as $module => $items) {
        $markdown .= '## '.ucfirst($module)."\n\n";

        foreach ($items as $item) {
            $markdown .= '- `'.$item['code'].'` - '.$item['description'].' Introduced in `'.$item['introduced'].'`; status `'.$item['status']."`.\n";
        }

        $markdown .= "\n";
    }

    return rtrim($markdown)."\n";
}

/**
 * @param  array<string, string>  $outputs
 */
function writeOrCheck(array $outputs, bool $checkOnly): int
{
    $failed = false;

    foreach ($outputs as $path => $contents) {
        if ($checkOnly) {
            if (! is_file($path) || file_get_contents($path) !== $contents) {
                fwrite(STDERR, $path." is not up to date. Run php scripts/build-docs.php.\n");
                $failed = true;
            }

            continue;
        }

        file_put_contents($path, $contents);
    }

    return $failed ? 1 : 0;
}

$catalog = json_decode((string) file_get_contents($root.'/schemas/v1/issue-codes.json'), true, flags: JSON_THROW_ON_ERROR);

exit(writeOrCheck([
    $root.'/docs/issue-codes.html' => renderIssueCodesHtml($catalog),
    $root.'/docs/issue-codes.md' => renderIssueCodesMarkdown($catalog),
    $root.'/docs/manifest.json' => prettyJson(docsManifest()),
    $root.'/docs/commands.json' => prettyJson(commandCatalog()),
], $checkOnly));
