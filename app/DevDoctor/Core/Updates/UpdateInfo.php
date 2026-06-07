<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

final readonly class UpdateInfo
{
    public function __construct(
        public string $currentVersion,
        public ReleaseInfo $latestRelease,
        public UpdateInstruction $instruction,
    ) {}
}
