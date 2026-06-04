<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Git;

use App\DevDoctor\Core\ProcessResult;

interface GitRunnerInterface
{
    /**
     * @param  list<string>  $arguments
     */
    public function run(array $arguments, string $workingDirectory): ProcessResult;
}
