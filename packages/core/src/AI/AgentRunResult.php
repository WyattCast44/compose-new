<?php

declare(strict_types=1);

namespace Compose\AI;

final readonly class AgentRunResult
{
    public function __construct(
        public bool $successful,
        public string $output = '',
        public string $error = '',
        public ?string $sessionId = null,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?float $cost = null,
    ) {}
}
