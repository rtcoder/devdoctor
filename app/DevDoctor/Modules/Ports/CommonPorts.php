<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ports;

final class CommonPorts
{
    /**
     * @return list<int>
     */
    public function all(): array
    {
        return [80, 443, 3000, 3001, 4200, 5173, 5174, 8000, 8080, 9000, 5432, 3306, 6379, 27017];
    }
}
