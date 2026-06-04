<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Docker;

use DevDoctor\Core\ProcessResult;
use DevDoctor\Core\ProcessRunner;

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
