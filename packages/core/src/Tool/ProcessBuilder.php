<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\Definition\Risk;
use Compose\PendingAction;
use Compose\Step;

final readonly class ProcessBuilder
{
    public function __construct(private Step $step) {}

    /** @param list<string> $command */
    public function run(array $command): PendingAction
    {
        return $this->step->queue(
            'process:run',
            implode(' ', $command),
            ['command' => $command],
            [Risk::NonReversible],
            false,
        );
    }

    public function shell(string $command): PendingAction
    {
        return $this->step->queue(
            'process:shell',
            $command,
            ['command' => $command],
            [Risk::Shell, Risk::NonReversible],
            false,
        );
    }
}
