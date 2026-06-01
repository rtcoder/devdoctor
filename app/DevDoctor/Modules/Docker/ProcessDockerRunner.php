<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Docker;

use App\DevDoctor\Core\ProcessResult;
use App\DevDoctor\Core\ProcessRunner;

final readonly class ProcessDockerRunner implements DockerRunnerInterface
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
    ) {}

    public function run(array $command, string $workingDirectory): ProcessResult
    {
        return $this->processRunner->run($command, $workingDirectory);
    }
}
