<?php

declare(strict_types=1);

namespace Tests\Support;

use DevDoctor\Core\ProcessResult;
use DevDoctor\Modules\Ports\PortCommandRunnerInterface;

final readonly class FakePortCommandRunner implements PortCommandRunnerInterface
{
    /**
     * @param  array<string, ProcessResult>  $responses
     */
    public function __construct(
        private array $responses = [],
    ) {}

    public function run(array $command): ProcessResult
    {
        return $this->responses[implode(' ', $command)] ?? new ProcessResult(1, '', 'missing fake response');
    }
}
