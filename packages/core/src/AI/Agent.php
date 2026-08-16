<?php

declare(strict_types=1);

namespace Compose\AI;

enum Agent: string
{
    case Codex = 'codex';
    case Claude = 'claude';
}
