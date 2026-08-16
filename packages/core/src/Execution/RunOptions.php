<?php

declare(strict_types=1);

namespace Compose\Execution;

use Closure;
use Compose\AI\AgentReviewer;

final readonly class RunOptions
{
    /** @param null|Closure(string): void $onAgentOutput */
    public function __construct(
        public string $root,
        public string $agent = 'codex',
        public ?string $model = null,
        public bool $rebake = false,
        public bool $acceptAi = false,
        public ?AgentReviewer $reviewer = null,
        public ?Closure $onAgentOutput = null,
    ) {}
}
