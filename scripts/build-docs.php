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
], $checkOnly));
