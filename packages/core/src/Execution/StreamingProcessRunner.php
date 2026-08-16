<?php

declare(strict_types=1);

namespace Compose\Execution;

use Closure;

interface StreamingProcessRunner extends ProcessRunner
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     * @param  Closure(string, string): void  $onOutput
     */
    public function runStreaming(
        array $command,
        string $cwd,
        Closure $onOutput,
        ?float $timeout = null,
        ?string $input = null,
        array $environment = [],
    ): ProcessResult;
}
