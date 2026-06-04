<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ports;

use DevDoctor\Core\ProcessResult;

interface PortCommandRunnerInterface
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command): ProcessResult;
}
