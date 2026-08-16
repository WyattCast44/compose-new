<?php

declare(strict_types=1);

namespace Compose\Events;

use Compose\Execution\ActionResult;

final readonly class ActionFinished
{
    public function __construct(public ActionResult $result) {}
}
