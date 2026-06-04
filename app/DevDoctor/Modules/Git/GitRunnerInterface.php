<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Git;

use DevDoctor\Core\ProcessResult;

interface GitRunnerInterface
{
    /**
     * @param  list<string>  $arguments
     */
    public function run(array $arguments, string $workingDirectory): ProcessResult;
}
