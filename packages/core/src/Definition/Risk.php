<?php

declare(strict_types=1);

namespace Compose\Definition;

enum Risk: string
{
    case Network = 'network';
    case Destructive = 'destructive';
    case Shell = 'shell';
    case Ai = 'ai';
    case NonReversible = 'non-reversible';
}
