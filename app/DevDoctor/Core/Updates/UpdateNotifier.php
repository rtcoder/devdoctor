<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateNotifier
{
    private bool $checked = false;

    public function __construct(
        private readonly UpdateChecker $checker = new UpdateChecker,
    ) {}

    public function notifyIfAvailable(InputInterface $input, OutputInterface $output, string $currentVersion): void
    {
        if (! $this->shouldCheck($input)) {
            return;
        }

        $this->checked = true;
        $update = $this->checker->check($currentVersion);

        if ($update === null) {
            return;
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>Update available:</comment> you are using DevDoctor %s, latest is %s. Update with: <info>%s</info>',
            $update->currentVersion,
            $update->latestRelease->version,
            $update->instruction->displayCommand,
        ));
    }

    private function shouldCheck(InputInterface $input): bool
    {
        if ($this->checked || $this->truthy(getenv('DEVDOCTOR_NO_UPDATE_CHECK'))) {
            return false;
        }

        if ($this->truthy(getenv('CI')) && ! $this->truthy(getenv('DEVDOCTOR_FORCE_UPDATE_CHECK'))) {
            return false;
        }

        if (! $this->truthy(getenv('DEVDOCTOR_FORCE_UPDATE_CHECK')) && function_exists('stream_isatty') && ! @stream_isatty(STDOUT)) {
            return false;
        }

        $command = $input->getFirstArgument();

        if (in_array($command, [null, '', 'self-update', 'test', 'app:build', 'list', 'help'], true)) {
            return false;
        }

        $format = $input->getParameterOption('--format', 'table');

        return ! in_array($format, ['json', 'sarif'], true);
    }

    private function truthy(mixed $value): bool
    {
        return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
