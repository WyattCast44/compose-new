<?php

declare(strict_types=1);

namespace Compose\AI;

final readonly class AgentRequest
{
    public function __construct(
        public string $task,
        public string $prompt,
        public string $cwd,
        public ?string $model = null,
    ) {}
}
