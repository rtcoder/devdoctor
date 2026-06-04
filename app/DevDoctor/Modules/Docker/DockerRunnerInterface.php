<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Docker;

use App\DevDoctor\Core\ProcessResult;

interface DockerRunnerInterface
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, string $workingDirectory): ProcessResult;
}
