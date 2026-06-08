<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

use Throwable;

final class GitHubReleaseClient implements ReleaseClientInterface
{
    private const string LATEST_RELEASE_URL = 'https://api.github.com/repos/rtcoder/devdoctor/releases/latest';

    public function latest(): ?ReleaseInfo
    {
        $forcedVersion = getenv('DEVDOCTOR_LATEST_VERSION');

        if (is_string($forcedVersion) && trim($forcedVersion) !== '') {
            $version = $this->normalizeVersion($forcedVersion);

            return new ReleaseInfo($version, 'https://github.com/rtcoder/devdoctor/releases/tag/v'.$version);
        }

        if ($cached = $this->cached()) {
            return $cached;
        }

        try {
            $payload = $this->fetchLatestRelease();
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $tag = $payload['tag_name'] ?? null;

        if (! is_string($tag) || trim($tag) === '') {
            return null;
        }

        $version = $this->normalizeVersion($tag);
        $release = new ReleaseInfo(
            $version,
            is_string($payload['html_url'] ?? null) ? $payload['html_url'] : 'https://github.com/rtcoder/devdoctor/releases/tag/v'.$version,
        );

        $this->writeCache($release);

        return $release;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchLatestRelease(): ?array
    {
        $url = getenv('DEVDOCTOR_UPDATE_CHECK_URL');
        $url = is_string($url) && $url !== '' ? $url : self::LATEST_RELEASE_URL;
        $context = stream_context_create([
            'http' => [
                'header' => implode("\n", [
                    'Accept: application/vnd.github+json',
                    'User-Agent: DevDoctor update checker',
                ]),
                'ignore_errors' => true,
                'timeout' => 1.5,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if (! is_string($body) || trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function cached(): ?ReleaseInfo
    {
        $path = $this->cachePath();

        if (! is_file($path) || filemtime($path) < time() - 86400) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        if (! is_array($decoded) || ! is_string($decoded['version'] ?? null) || ! is_string($decoded['url'] ?? null)) {
            return null;
        }

        return new ReleaseInfo($decoded['version'], $decoded['url']);
    }

    private function writeCache(ReleaseInfo $release): void
    {
        @file_put_contents($this->cachePath(), json_encode([
            'version' => $release->version,
            'url' => $release->url,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function cachePath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'devdoctor-latest-release.json';
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
