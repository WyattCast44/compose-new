<?php

declare(strict_types=1);

namespace Compose\Events;

use Compose\Execution\StepResult;

final readonly class StepFinished
{
    public function __construct(public StepResult $result) {}
}
