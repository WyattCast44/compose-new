<?php

declare(strict_types=1);

namespace Compose\Events;

use Compose\Definition\StepDefinition;

final readonly class StepStarting
{
    public function __construct(public StepDefinition $step) {}
}
