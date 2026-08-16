<?php

declare(strict_types=1);

namespace Compose\AI;

final readonly class AgentReview
{
    /** @param list<string> $changedPaths */
    public function __construct(public string $task, public string $output, public string $patch, public array $changedPaths) {}
}
