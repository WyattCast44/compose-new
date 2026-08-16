<?php

declare(strict_types=1);

namespace Compose\AI;

enum ReviewDecision: string
{
    case Continue = 'continue';
    case Rollback = 'rollback';
    case Steer = 'steer';
}
