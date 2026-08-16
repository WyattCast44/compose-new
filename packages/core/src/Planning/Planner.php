<?php

declare(strict_types=1);

namespace Compose\Planning;

use Compose\Composition;

final class Planner
{
    public function plan(Composition $composition): Plan
    {
        return new Plan($composition->name, $composition->steps());
    }
}
