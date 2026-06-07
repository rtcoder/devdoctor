<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

use DevDoctor\Core\CommandAvailability;
use DevDoctor\Core\CommandAvailabilityInterface;
use DevDoctor\Core\ProcessRunner;
use Phar;

final readonly class InstallationDetector
{
    public function __construct(
        private CommandAvailabilityInterface $commands = new CommandAvailability,
        private ProcessRunner $processRunner = new ProcessRunner,
    ) {}

    public function updateInstruction(string $latestVersion): UpdateInstruction
    {
        $override = getenv('DEVDOCTOR_UPDATE_COMMAND');

        if (is_string($override) && trim($override) !== '') {
            return new UpdateInstruction('custom', trim($override));
        }

        if ($this->looksLikeHomebrew()) {
            return new UpdateInstruction(
                method: 'homebrew',
                displayCommand: 'brew upgrade rtcoder/tap/devdoctor',
                command: ['brew', 'upgrade', 'rtcoder/tap/devdoctor'],
                runnable: true,
            );
        }

        if ($this->looksLikeComposerLocal()) {
            return new UpdateInstruction(
                method: 'composer-local',
                displayCommand: 'composer update rtcoder/devdoctor --with-dependencies',
                command: ['composer', 'update', 'rtcoder/devdoctor', '--with-dependencies'],
                runnable: true,
            );
        }

        if ($this->looksLikeComposerGlobal()) {
            return new UpdateInstruction(
                method: 'composer-global',
                displayCommand: 'composer global update rtcoder/devdoctor',
                command: ['composer', 'global', 'update', 'rtcoder/devdoctor'],
                runnable: true,
            );
        }

        if (Phar::running(false) !== '') {
            return new UpdateInstruction(
                method: 'phar',
                displayCommand: 'Download https://github.com/rtcoder/devdoctor/releases/download/v'.$latestVersion.'/devdoctor.phar and replace your current devdoctor.phar',
            );
        }

        return new UpdateInstruction('manual', 'devdoctor self-update');
    }

    private function looksLikeHomebrew(): bool
    {
        $binary = $this->binaryPath();

        if (str_contains($binary, '/Cellar/devdoctor/')) {
            return true;
        }

        if (! $this->commands->available('brew')) {
            return false;
        }

        $result = $this->processRunner->run(['brew', 'list', '--versions', 'devdoctor'], getcwd() ?: '.', 3);

        return $result->successful() && trim($result->stdout) !== '';
    }

    private function looksLikeComposerLocal(): bool
    {
        $binary = $this->binaryPath();

        return str_contains($binary, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'devdoctor')
            && ! $this->looksLikeComposerGlobal();
    }

    private function looksLikeComposerGlobal(): bool
    {
        $binary = $this->binaryPath();

        return str_contains($binary, DIRECTORY_SEPARATOR.'.composer'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'devdoctor')
            || str_contains($binary, DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'devdoctor');
    }

    private function binaryPath(): string
    {
        $argvZero = $_SERVER['argv'][0] ?? '';
        $path = is_string($argvZero) ? $argvZero : '';
        $resolved = $path !== '' ? realpath($path) : false;

        return $resolved !== false ? $resolved : $path;
    }
}
