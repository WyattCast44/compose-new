<?php

declare(strict_types=1);

namespace Compose\Execution;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SymfonyProcessRunner implements ProcessRunner
{
    public function run(array $command, string $cwd, ?float $timeout = null, ?string $input = null, array $environment = []): ProcessResult
    {
        $process = new Process($command, $cwd, $environment ?: null, $input, $timeout ?? 300);

        return $this->execute($process, $command);
    }

    public function shell(string $command, string $cwd, ?float $timeout = null): ProcessResult
    {
        $process = Process::fromShellCommandline($command, $cwd, timeout: $timeout ?? 300);

        return $this->execute($process, $command);
    }

    /** @param list<string>|string $command */
    private function execute(Process $process, array|string $command): ProcessResult
    {
        $started = microtime(true);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new ProcessResult(
                $command,
                124,
                $process->getOutput(),
                'Process timed out. '.$process->getErrorOutput(),
                microtime(true) - $started,
            );
        }

        return new ProcessResult(
            $command,
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
            microtime(true) - $started,
        );
    }
}
