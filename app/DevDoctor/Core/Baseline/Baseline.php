<?php

declare(strict_types=1);

namespace App\DevDoctor\Core\Baseline;

final readonly class Baseline
{
    /**
     * @param  list<string>  $fingerprints
     */
    public function __construct(
        public array $fingerprints,
    ) {}

    public function contains(string $fingerprint): bool
    {
        return in_array($fingerprint, $this->fingerprints, true);
    }
}
