<?php

declare(strict_types=1);

namespace Compose\Execution;

interface ProcessRunner
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    public function run(
        array $command,
        string $cwd,
        ?float $timeout = null,
        ?string $input = null,
        array $environment = [],
    ): ProcessResult;

    public function shell(string $command, string $cwd, ?float $timeout = null): ProcessResult;
}
