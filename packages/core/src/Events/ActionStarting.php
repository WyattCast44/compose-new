<?php

declare(strict_types=1);

namespace Compose\Events;

use Compose\Definition\ActionDefinition;

final readonly class ActionStarting
{
    public function __construct(public ActionDefinition $action, public int $attempt) {}
}
