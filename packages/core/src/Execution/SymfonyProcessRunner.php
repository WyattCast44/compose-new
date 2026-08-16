<?php

declare(strict_types=1);

namespace Compose\Execution;

use Closure;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SymfonyProcessRunner implements StreamingProcessRunner
{
    public function run(array $command, string $cwd, ?float $timeout = null, ?string $input = null, array $environment = []): ProcessResult
    {
        $process = new Process($command, $cwd, $environment ?: null, $input, $timeout ?? 300);

        return $this->execute($process, $command);
    }

    public function runStreaming(array $command, string $cwd, Closure $onOutput, ?float $timeout = null, ?string $input = null, array $environment = []): ProcessResult
    {
        $process = new Process($command, $cwd, $environment ?: null, $input, $timeout ?? 300);

        return $this->execute($process, $command, $onOutput);
    }

    public function shell(string $command, string $cwd, ?float $timeout = null): ProcessResult
    {
        $process = Process::fromShellCommandline($command, $cwd, timeout: $timeout ?? 300);

        return $this->execute($process, $command);
    }

    /** @param list<string>|string $command */
    private function execute(Process $process, array|string $command, ?Closure $onOutput = null): ProcessResult
    {
        $started = microtime(true);

        try {
            $process->run($onOutput);
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
