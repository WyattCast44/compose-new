<?php

declare(strict_types=1);

namespace Compose\Execution;

final readonly class GitCheckpoint
{
    public function __construct(
        public string $root,
        public string $head,
        public string $worktree,
        public string $index,
    ) {}
}
