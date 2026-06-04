<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ports;

interface PortProviderInterface
{
    public function available(): bool;

    /**
     * @return list<PortUsage>
     */
    public function usages(int $port): array;
}
