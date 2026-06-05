<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Http;

use DevDoctor\Core\Issue;
use DevDoctor\Core\IssueCode;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ModuleName;
use DevDoctor\Core\PathResolver;
use DevDoctor\Core\Severity;
use DevDoctor\Modules\Env\EnvEntry;
use DevDoctor\Modules\Env\EnvParser;

final readonly class HttpAnalyzer
{
    /**
     * @var list<string>
     */
    private const ENV_URL_KEYS = ['APP_URL', 'FRONTEND_URL', 'API_URL'];

    public function __construct(
        private EnvParser $parser = new EnvParser,
    ) {}

    public function analyze(HttpOptions $options): IssueCollection
    {
        $paths = PathResolver::fromBasePath($options->path);
        $issues = new IssueCollection;
        $env = $this->parser->parseFile($paths->absolute('.env'), '.env');
        $entries = $env->exists ? $this->entriesByKey($env->entries) : [];
        $appEnv = strtolower(trim($entries['APP_ENV']->value ?? ''));
        $targets = $this->targets($entries, $options);

        if ($targets === []) {
            $issues->add(new Issue(
                code: IssueCode::DD_HTTP_URL_MISSING,
                severity: Severity::INFO,
                message: 'No HTTP URL targets were found.',
                module: ModuleName::HTTP,
            ));

            return $issues;
        }

        foreach ($targets as $target) {
            $this->checkUrl($issues, $target, $appEnv, $options);
        }

        if ($issues->isEmpty()) {
            $issues->add(new Issue(
                code: IssueCode::DD_HTTP_READY,
                severity: Severity::INFO,
                message: 'HTTP URL diagnostics found no actionable issues.',
                module: ModuleName::HTTP,
            ));
        }

        return $issues;
    }

    /**
     * @param  list<EnvEntry>  $entries
     * @return array<string, EnvEntry>
     */
    private function entriesByKey(array $entries): array
    {
        $indexed = [];

        foreach ($entries as $entry) {
            $indexed[$entry->key] = $entry;
        }

        return $indexed;
    }

    /**
     * @param  array<string, EnvEntry>  $entries
     * @return list<array{url: string, key: string|null, file: string|null, line: int|null}>
     */
    private function targets(array $entries, HttpOptions $options): array
    {
        $targets = [];

        foreach (self::ENV_URL_KEYS as $key) {
            $value = trim($entries[$key]->value ?? '');

            if ($value === '') {
                continue;
            }

            $targets[] = [
                'url' => $value,
                'key' => $key,
                'file' => '.env',
                'line' => $entries[$key]->line ?? null,
            ];
        }

        foreach ($options->urls as $url) {
            $url = trim($url);

            if ($url === '') {
                continue;
            }

            $targets[] = [
                'url' => $url,
                'key' => 'url',
                'file' => null,
                'line' => null,
            ];
        }

        return $targets;
    }

    /**
     * @param  array{url: string, key: string|null, file: string|null, line: int|null}  $target
     */
    private function checkUrl(IssueCollection $issues, array $target, string $appEnv, HttpOptions $options): void
    {
        $parts = parse_url($target['url']);

        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || ! is_string($parts['host'] ?? null)) {
            $issues->add(new Issue(
                code: IssueCode::DD_HTTP_URL_INVALID,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'HTTP URL is invalid: '.$target['url'],
                module: ModuleName::HTTP,
                file: $target['file'],
                line: $target['line'],
                key: $target['key'],
            ));

            return;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        if ($appEnv === 'production' && $scheme !== 'https') {
            $issues->add(new Issue(
                code: IssueCode::DD_HTTP_INSECURE_PRODUCTION_URL,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'Production URL should use HTTPS: '.$target['url'],
                module: ModuleName::HTTP,
                file: $target['file'],
                line: $target['line'],
                key: $target['key'],
            ));
        }

        if ($appEnv === 'production' && in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            $issues->add(new Issue(
                code: IssueCode::DD_HTTP_LOCALHOST_PRODUCTION_URL,
                severity: $options->strict ? Severity::ERROR : Severity::WARNING,
                message: 'Production URL points to a local host: '.$target['url'],
                module: ModuleName::HTTP,
                file: $target['file'],
                line: $target['line'],
                key: $target['key'],
            ));
        }
    }
}
