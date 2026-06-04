<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

use App\DevDoctor\Core\ProcessResult;

interface PortCommandRunnerInterface
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command): ProcessResult;
}
