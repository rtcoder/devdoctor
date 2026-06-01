<?php

declare(strict_types=1);

namespace App\DevDoctor\Core\Config;

final readonly class DevDoctorConfig
{
    /**
     * @param  array<string, array<string, mixed>>  $envRules
     * @param  list<string>  $ignoreMissingInEnv
     * @param  list<string>  $ignoreMissingInExample
     */
    public function __construct(
        public string $envFile = '.env',
        public string $exampleFile = '.env.example',
        public array $envRules = [],
        public array $ignoreMissingInEnv = [],
        public array $ignoreMissingInExample = [],
    ) {}
}
