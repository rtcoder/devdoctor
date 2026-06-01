<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Env;

final readonly class EnvAnalysisOptions
{
    /**
     * @param  array<string, array<string, mixed>>  $rules
     * @param  list<string>  $ignoreMissingInEnv
     * @param  list<string>  $ignoreMissingInExample
     */
    public function __construct(
        public string $path,
        public string $envFile = '.env',
        public string $exampleFile = '.env.example',
        public bool $strict = false,
        public bool $scanSecrets = true,
        public array $rules = [],
        public array $ignoreMissingInEnv = [],
        public array $ignoreMissingInExample = [],
    ) {}
}
