<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ports;

use DevDoctor\Core\ProcessResult;
use DevDoctor\Core\ProcessRunner;

final readonly class ProcessPortCommandRunner implements PortCommandRunnerInterface
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
    ) {}

    public function run(array $command): ProcessResult
    {
        return $this->processRunner->run($command, getcwd());
    }
}
