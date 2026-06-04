<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Docker;

use DevDoctor\Core\ProcessResult;

interface DockerRunnerInterface
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, string $workingDirectory): ProcessResult;
}
