<?php

declare(strict_types=1);

namespace Compose\Tool;

enum NodeManager: string
{
    case Npm = 'npm';
    case Pnpm = 'pnpm';
    case Yarn = 'yarn';
    case Bun = 'bun';
}
