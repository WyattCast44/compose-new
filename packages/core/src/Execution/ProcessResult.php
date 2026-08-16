<?php

declare(strict_types=1);

namespace Compose\Execution;

final readonly class ProcessResult
{
    public bool $successful;

    /** @param list<string>|string $command */
    public function __construct(
        public array|string $command,
        public int $exitCode,
        public string $output = '',
        public string $errorOutput = '',
        public float $duration = 0,
    ) {
        $this->successful = $this->exitCode === 0;
    }
}
