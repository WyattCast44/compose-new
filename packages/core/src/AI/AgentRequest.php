<?php

declare(strict_types=1);

namespace Compose\AI;

use Closure;

final readonly class AgentRequest
{
    /** @param null|Closure(string): void $onOutput */
    public function __construct(
        public string $task,
        public string $prompt,
        public string $cwd,
        public ?string $model = null,
        public ?Closure $onOutput = null,
    ) {}
}
