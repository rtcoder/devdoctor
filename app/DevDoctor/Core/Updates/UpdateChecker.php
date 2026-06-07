<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

final readonly class UpdateChecker
{
    public function __construct(
        private ReleaseClientInterface $releases = new GitHubReleaseClient,
        private InstallationDetector $installation = new InstallationDetector,
    ) {}

    public function check(string $currentVersion): ?UpdateInfo
    {
        $currentVersion = $this->normalizeVersion($currentVersion);
        $latest = $this->releases->latest();

        if ($latest === null || ! $this->isNewer($latest->version, $currentVersion)) {
            return null;
        }

        return new UpdateInfo(
            currentVersion: $currentVersion,
            latestRelease: $latest,
            instruction: $this->installation->updateInstruction($latest->version),
        );
    }

    private function isNewer(string $latestVersion, string $currentVersion): bool
    {
        return version_compare($this->normalizeVersion($latestVersion), $currentVersion, '>');
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
