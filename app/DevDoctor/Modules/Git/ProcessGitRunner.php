<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Git;

use App\DevDoctor\Core\ProcessResult;
use App\DevDoctor\Core\ProcessRunner;

final readonly class ProcessGitRunner implements GitRunnerInterface
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
    )
    {
    }

    public function run(array $arguments, string $workingDirectory): ProcessResult
    {
        return $this->processRunner->run(array_merge(['git'], $arguments), $workingDirectory);
    }
}
