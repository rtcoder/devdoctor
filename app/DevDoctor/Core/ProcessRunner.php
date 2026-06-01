<?php

declare(strict_types=1);

namespace App\DevDoctor\Core;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, string $workingDirectory, int $timeout = 10): ProcessResult
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout($timeout);

        try {
            $process->run();

            return new ProcessResult(
                exitCode: $process->getExitCode() ?? ExitCode::INTERNAL_ERROR->value,
                stdout: $process->getOutput(),
                stderr: $process->getErrorOutput(),
            );
        } catch (ProcessTimedOutException) {
            return new ProcessResult(
                exitCode: ExitCode::INTERNAL_ERROR->value,
                stdout: $process->getOutput(),
                stderr: $process->getErrorOutput(),
                timedOut: true,
            );
        } catch (Throwable $exception) {
            return new ProcessResult(
                exitCode: ExitCode::INTERNAL_ERROR->value,
                stdout: '',
                stderr: $exception->getMessage(),
            );
        }
    }
}
