<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final readonly class DiagnosticRunOptions
{
    public function __construct(
        public string $path = '.',
        public bool $strict = false,
        public bool $ci = false,
        public string $configFile = 'devdoctor.yml',
        public bool $portsCommon = false,
        public bool $gitRequireClean = false,
        public bool $gitRequireUpstream = false,
        public bool $gitScanSensitive = true,
        public bool $gitScanLargeFiles = false,
    ) {}
}
